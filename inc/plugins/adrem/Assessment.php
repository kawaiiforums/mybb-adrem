<?php

namespace adrem;

class Assessment
{
    protected $id;
    /**
     * @var ContentEntity
     */
    protected $contentEntity;
    protected $inspectionId;
    protected $requestedAttributes = [];
    protected $attributeValues = [];
    protected $suggestedAttributeValues = [];
    protected $duration;
    protected $resultData;

    const VERSION = '1';

    public static function getName()
    {
        return lcfirst(
            end(explode('\\', get_called_class()))
        );
    }

    public static function getProvidedAttributes(): array
    {
        static $providedAttributes = null;

        if ($providedAttributes === null) {
            $providedAttributes = array_map('lcfirst', \adrem\getClassMethodsNamesMatching(
                get_called_class(),
                '/^get(.+)Attribute$/'
            ));
        }

        return $providedAttributes;
    }

    public function __construct(?int $id = null)
    {
        if ($id !== null) {
            $this->id = $id;
        }
    }

    public function setContentEntity(ContentEntity $contentEntity): void
    {
        $this->contentEntity = $contentEntity;
    }

    public function setInspectionId(int $inspectionId): void
    {
        $this->inspectionId = $inspectionId;
    }

    public function setRequestedAttributes(array $attributeNames): bool
    {
        if (!empty(array_diff($attributeNames, $this->getProvidedAttributes()))) {
            return false;
        } else {
            $this->requestedAttributes = $attributeNames;

            return true;
        }
    }

    public function setSuggestedAttributeValues(array $attributeValues): bool
    {
        if (array_diff(array_keys($attributeValues), static::getProvidedAttributes())) {
            return false;
        } else {
            $this->suggestedAttributeValues = $attributeValues;

            return true;
        }
    }

    public function submitSuggestedAttributeValues(): bool
    {
        return false;
    }

    public static function supportsAttributeValueSuggestions(string $version): bool
    {
        return false;
    }

    public function getResultData(): ?array
    {
        return $this->resultData;
    }

    public function setResultData(array $resultData): void
    {
        $this->resultData = $resultData;
    }

    public function getCost(): int
    {
        return 1;
    }

    public function getAttributeValues(): array
    {
        if (!$this->attributeValues) {
            $timeStart = microtime(true);

            if (method_exists($this, 'loadAttributeValues')) {
                $this->loadAttributeValues();
            } else {
                foreach ($this->requestedAttributes as $attributeName) {
                    $this->attributeValues[$attributeName] = $this->{'get' . ucfirst($attributeName) . 'Attribute'}();
                }
            }

            $timeEnd = microtime(true);

            $this->duration = $timeEnd - $timeStart;
        }

        return $this->attributeValues;
    }

    public function persist(\DB_Base $db): int
    {
        $data = [];

        if ($this->duration !== null) {
            $data['duration'] = (float)$this->duration;
        }

        if ($this->attributeValues) {
            $data['attribute_values'] = $db->escape_string(json_encode($this->attributeValues));
        }

        if ($this->getResultData()) {
            $data['result_data'] = $db->escape_string(json_encode($this->getResultData()));
        }

        if ($this->suggestedAttributeValues) {
            $data['suggested_attribute_values'] = $db->escape_string(json_encode($this->suggestedAttributeValues));
        }

        if ($this->id) {
            $db->update_query('adrem_assessments', $data, 'id = ' . (int)$this->id);
        } else {
            $data['inspection_id'] = (int)$this->inspectionId;
            $data['name'] = $db->escape_string(static::getName());
            $data['version'] = $db->escape_string(static::VERSION);
            $data['date_completed'] = \TIME_NOW;

            $this->id = $db->insert_query('adrem_assessments', $data);
        }

        return $this->id;
    }
}
