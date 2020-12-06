<?php

namespace adrem;

class Inspection
{
    protected $id;
    protected $completed = false;
    protected $dateCompleted;
    protected $contentTypeActions;
    protected $declaredRevisionsAssessmentsAttributes = [];
    protected $revisionsAssessmentsAttributeValues;
    protected $eventNames = [];

    /** @var Ruleset */
    protected $ruleset;

    /** @var ContentEntity */
    protected $contentEntity;

    /** @var \DB_Base */
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

    public function setEventName(?string $eventName): void
    {
        $this->eventNames = [$eventName];
    }

    public function run(): void
    {
        $this->declaredRevisionsAssessmentsAttributes = $this->ruleset->getRuleAssessmentAttributesForContentType(
            $this->contentEntity::getName(),
            $this->eventNames
        );

        $this->contentTypeActions = $this->ruleset->getContentTypeActions(
            $this->contentEntity::getName(),
            $this->eventNames,
            $this
        );

        $this->setCompleted(true);

        if ($this->db) {
            $this->persist($this->db);
        }
    }

    /**
     * @throws \Exception
     */
    public function getAssessmentAttributeValue(string $assessmentName, string $attributeName, ?string $contentEntityRevision = null)
    {
        if ($contentEntityRevision === null) {
            $contentEntityRevision = $this->contentEntity->getDefaultRevision();
        }

        if (!isset($this->declaredRevisionsAssessmentsAttributes[$contentEntityRevision][$assessmentName])) {
            throw new \Exception('Attempting to use undeclared assessment `' . $assessmentName . '`');
        }

        if (!in_array($attributeName, $this->declaredRevisionsAssessmentsAttributes[$contentEntityRevision][$assessmentName])) {
            throw new \Exception('Attempting to use undeclared attribute `' . $assessmentName . ':' . $attributeName . '`');
        }

        if (!isset($this->revisionsAssessmentsAttributeValues[$contentEntityRevision][$assessmentName])) {
            $this->runAssessment(
                $assessmentName,
                $this->declaredRevisionsAssessmentsAttributes[$contentEntityRevision][$assessmentName],
                $contentEntityRevision
            );
        }

        return $this->revisionsAssessmentsAttributeValues[$contentEntityRevision][$assessmentName][$attributeName] ?? null;
    }

    public function runAssessment(string $assessmentName, array $attributeNames, ?string $contentEntityRevision = null): ?array
    {
        $callable = '\adrem\Assessment\\' . ucfirst($assessmentName);

        /** @var \adrem\Assessment $assessment */
        $assessment = new $callable();

        if ($contentEntityRevision === null) {
            $contentEntityRevision = $this->contentEntity->getDefaultRevision();
        }

        $assessment->setContentEntity($this->contentEntity);
        $assessment->setContentEntityRevision($contentEntityRevision);

        $availableAttributes = $assessment->setRequestedAttributes($attributeNames);

        if ($availableAttributes) {
            $values = $assessment->getAttributeValues();

            if (
                $contentEntityRevision === $this->contentEntity->getDefaultRevision() &&
                \adrem\assessmentPersisted($assessmentName) &&
                $this->getId() &&
                $this->db
            ) {
                $assessment->setInspectionId($this->getId());
                $assessment->persist($this->db);
            }
        } else {
            $values = [];
        }

        $this->revisionsAssessmentsAttributeValues[$contentEntityRevision][$assessmentName] = array_merge(
            $this->revisionsAssessmentsAttributeValues[$contentEntityRevision][$assessmentName] ?? [],
            $values
        );

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
