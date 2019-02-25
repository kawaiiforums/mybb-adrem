<?php

namespace adrem;

class Assessment
{
    /**
     * @var ContentEntity
     */
    protected $contentEntity;
    protected $inspectionId;
    protected $requestedAttributes = [];
    protected $attributeValues = [];
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

    public function __construct(ContentEntity $contentEntity)
    {
        $this->setContentEntity($contentEntity);
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
        $data = [
            'inspection_id' => (int)$this->inspectionId,
            'name' => $db->escape_string(self::getName()),
            'version' => $db->escape_string(self::VERSION),
            'date_completed' => time(),
            'duration' => (float)$this->duration,
            'attribute_values' => $db->escape_string(json_encode($this->getAttributeValues())),
        ];

        if ($this->getResultData()) {
            $data['result_data'] = $db->escape_string(json_encode($this->getResultData()));
        }

        return $db->insert_query('adrem_assessments', $data);
    }
}
