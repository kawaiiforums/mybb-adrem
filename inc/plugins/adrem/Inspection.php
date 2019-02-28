<?php

namespace adrem;

class Inspection
{
    protected $id;
    protected $completed = false;
    protected $dateCompleted;
    protected $contentTypeActions;
    /**
     * @var Ruleset
     */
    protected $ruleset;
    /**
     * @var ContentEntity
     */
    protected $contentEntity;
    /**
     * @var \DB_Base
     */
    protected $db;

    public function __construct(?int $id = null)
    {
        if ($id !== null) {
            $this->id = $id;
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $status): void
    {
        $this->completed = $status;
        $this->dateCompleted = time();
    }

    public function getContentTypeActions(): ?array
    {
        return $this->contentTypeActions;
    }

    public function setRuleset(Ruleset $ruleset): void
    {
        $this->ruleset = $ruleset;
    }

    public function setContentEntity(ContentEntity $contentEntity): void
    {
        $this->contentEntity = $contentEntity;
    }

    public function run(): void
    {
        $assessmentAttributes = $this->getAssessmentAttributeValues(
            $this->ruleset->getRuleAssessmentAttributesForContentType($this->contentEntity::getName())
        );

        $this->contentTypeActions = $this->ruleset->getContentTypeActionsByAssessmentAttributeValues(
            $this->contentEntity::getName(),
            $assessmentAttributes
        );

        $this->setCompleted(true);

        if ($this->db) {
            $this->persist($this->db);
        }
    }

    public function getAssessmentAttributeValues(array $assessmentAttributes): array
    {
        $values = [];

        foreach ($assessmentAttributes as $assessmentName => $attributeNames) {
            $callable = '\adrem\Assessment\\' . ucfirst($assessmentName);

            /** @var \adrem\Assessment $assessment */
            $assessment = new $callable();

            $assessment->setContentEntity($this->contentEntity);

            $availableAttributes = $assessment->setRequestedAttributes($attributeNames);

            if ($availableAttributes) {
                $values[$assessmentName] = $assessment->getAttributeValues();

                if ($this->getId() && $this->db) {
                    $assessment->setInspectionId($this->getId());
                    $assessment->persist($this->db);
                }
            } else {
                $values[$assessmentName] = [];
            }
        }

        return $values;
    }

    public function persist(\DB_Base $db = null, bool $continuous = false): int
    {
        if ($continuous) {
            $this->db = $db;
        }

        $data = [
            'content_type' => $db->escape_string($this->contentEntity::getName()),
            'content_entity_id' => (int)$this->contentEntity->getId(),
            'content_entity_data' => $db->escape_string(json_encode($this->contentEntity->getData())),
            'completed' => (int)$this->completed,
        ];

        if ($this->dateCompleted) {
            $data['date_completed'] = (int)$this->dateCompleted;
        }

        if ($this->contentTypeActions) {
            $data['actions'] = $db->escape_string(implode(';', $this->contentTypeActions));
        }

        if ($this->id) {
            $db->update_query('adrem_inspections', $data, 'id = ' . (int)$this->id);
        } else {
            $data['date_requested'] = \TIME_NOW;

            $this->id = $db->insert_query('adrem_inspections', $data);
        }

        return $this->id;
    }
}
