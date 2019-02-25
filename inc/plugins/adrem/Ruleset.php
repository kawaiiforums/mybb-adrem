<?php

namespace adrem;

class Ruleset
{
    protected $ruleset;

    public static function getParsedRuleset(string $value): array
    {
        if (!empty($value)) {
            $array = json_decode($value, true);

            if ($array) {
                return $array;
            } else {
                throw new \UnexpectedValueException('The JSON could not be decoded');
            }
        } else {
            return [];
        }
    }

    public static function getValidationResults(string $json): array
    {
        $results = [
            'errors' => [],
            'warnings' => [],
        ];

        $value = json_decode($json, true);

        if ($value === null) {
            $results['errors'][] = 'INVALID_JSON';
        } else {
            $ruleset = $value;

            if (!is_array($ruleset)) {
                $results['errors'][] = 'RULESET_NOT_AN_ARRAY';
            } else {
                foreach ($ruleset as $contentType => $contentTypeRuleset) {
                    if (!\adrem\contentTypeExists($contentType)) {
                        $results['warnings'][] = 'CONTENT_TYPE_NOT_FOUND (' . $contentType . ')';

                        $contentTypeExists = false;
                    } else {
                        $contentTypeExists = true;
                    }

                    foreach ($contentTypeRuleset as $conditional) {
                        if (!isset($conditional['rules']) || !is_array($conditional['rules'])) {
                            $results['errors'][] = 'NO_RULES_ARRAY_IN_CONDITIONAL (' . $contentType . ')';
                        } else {
                            $results = array_merge_recursive(
                                $results,
                                self::getRuleArrayValidationResults($conditional['rules'], $contentType, true)
                            );
                        }

                        if (!isset($conditional['actions']) || !is_array($conditional['actions'])) {
                            $results['errors'][] = 'NO_ACTIONS_ARRAY_IN_CONDITIONAL (' . $contentType . ')';
                        } elseif ($contentTypeExists) {
                            foreach ($conditional['actions'] as $action) {
                                if (!\adrem\contentTypeActionExists($contentType, $action)) {
                                    $results['warnings'][] = 'ACTION_NOT_FOUND (' . $contentType . ':' . $action . ')';
                                }
                            }
                        }
                    }
                }
            }
        }

        $results = array_map('array_unique', $results);

        return $results;
    }

    public function __construct(array $ruleset = [])
    {
        $this->ruleset = $ruleset;
    }

    public function getRuleAssessmentAttributesForContentType(string $contentType): array
    {
        $assessmentAttributes = [];

        if (isset($this->ruleset[$contentType])) {
            foreach ($this->ruleset[$contentType] as $contentTypeRuleset) {
                $iterator = new \RecursiveIteratorIterator(
                    new RecursiveRuleArrayIterator($contentTypeRuleset['rules'])
                );

                $ruleAttributes = array_column(iterator_to_array($iterator), 0);

                foreach ($ruleAttributes as $ruleAttribute) {
                    [$assessmentName, $attributeName] = explode(':', $ruleAttribute);

                    if (!isset($assessmentAttributes[$assessmentName])) {
                        $assessmentAttributes[$assessmentName] = [];
                    }

                    if (!in_array($attributeName, $assessmentAttributes[$assessmentName])) {
                        $assessmentAttributes[$assessmentName][] = $attributeName;
                    }
                }
            }
        }

        return $assessmentAttributes;
    }

    public function getContentTypeActionsByAssessmentAttributeValues(string $contentType, array $assessmentAttributeValues): array
    {
        $attributeValues = [];

        foreach ($assessmentAttributeValues as $assessment => $attributeValues) {
            foreach ($attributeValues as $attributeName => $value) {
                $attributeValues[$assessment . ':' . $attributeName] = $value;
            }
        }

        return $this->getContentTypeActionsByRuleAttributeValues($contentType, $attributeValues);
    }

    protected static function getRuleArrayValidationResults(array $ruleArray, string $contentType, bool $rootLevel = false): array
    {
        $results = [
            'errors' => [],
            'warnings' => [],
        ];


        $allowedRuleOperators = [
            '<',
            '<=',
            '>',
            '>=',
            '=',
            '!=',
        ];

        $allowedRuleGroupOperators = [
            'any',
            'all',
        ];

        if ($rootLevel == true && count($ruleArray) > 1) {
            $results['errors'][] = 'NO_ROOT_RULE_GROUP (' . $contentType . ')';
        } else {
            foreach ($ruleArray as $item) {
                if (!is_array($item)) {
                    $results['errors'][] = 'NOT_AN_ARRAY (@ ' . $contentType . ')';
                } else {
                    if (count($item) == 3 && is_string($item[0]) && is_string($item[1]) && is_string($item[2])) {
                        if ($rootLevel) {
                            $results['errors'][] = 'NO_ROOT_RULE_GROUP (' . $contentType . ')';
                        } else {
                            [$attribute, $operator] = $item;

                            if (substr_count($attribute, ':') != 1) {
                                $results['errors'][] = 'RULE_ATTRIBUTE_INVALID (' . $contentType . ':' . $attribute . ')';
                            } else {
                                $attributeElements = explode(':', $attribute);

                                if (!\adrem\assessmentExists($attributeElements[0])) {
                                    $results['warnings'][] = 'ASSESSMENT_NOT_FOUND (' . $attributeElements[0] . ')';
                                } else {
                                    if (!\adrem\assessmentProvidesAttribute($attributeElements[0], $attributeElements[1])) {
                                        $results['warnings'][] = 'ASSESSMENT_ATTRIBUTE_NOT_PROVIDED (' . $attribute . ')';
                                    }
                                }

                                if (!in_array($operator, $allowedRuleOperators)) {
                                    $results['errors'][] = 'RULE_OPERATOR_NOT_SUPPORTED (' . $operator . ')';
                                }
                            }
                        }
                    } elseif (count($item) == 1 && is_array(current($item))) {
                        if (!in_array(array_keys($item)[0], $allowedRuleGroupOperators)) {
                            $results['errors'][] = 'RULE_GROUP_OPERATOR_INVALID (' . array_keys($item)[0] . ')';
                        } else {
                            $results = array_merge_recursive(
                                $results,
                                self::getRuleArrayValidationResults(current($item), $contentType)
                            );
                        }
                    } else {
                        $results['errors'][] = 'NOT_RULE_OR_GROUP (@ ' . $contentType . ')';
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @throws \Exception
     */
    protected static function getRuleArrayResultByRuleAttributeValues(array $ruleArray, array $ruleAttributeValues, ?string $ruleGroupOperator): bool
    {
        $itemResults = [
            'operands' => 0,
            'failed' => 0,
            'passed' => 0,
        ];

        foreach ($ruleArray as $item) {
            if (is_array(current($item))) {
                $localRoleGroupOperator = array_keys($item)[0];

                $itemResult = self::getRuleArrayResultByRuleAttributeValues(current($item), $ruleAttributeValues, $localRoleGroupOperator);
            } else {
                [$attribute, $operator, $referenceValue] = $item;

                if (!isset($ruleAttributeValues[$attribute])) {
                    throw new \Exception('Attempting to use non-provided `' . $attribute . '` attribute');
                }

                $itemResult = self::getRuleResult($ruleAttributeValues[$attribute], $referenceValue, $operator);
            }

            $itemResults['operands']++;
            $itemResults[$itemResult ? 'passed' : 'failed']++;
        }

        if ($ruleGroupOperator === null) {
            return $itemResults['passed'] == 1;
        } else {
            $result = self::getRuleGroupResult($itemResults, $ruleGroupOperator);
        }

        return $result;
    }

    protected static function getRuleGroupResult(array $itemResults, string $operator): bool
    {
        $result = null;

        switch ($operator) {
            case 'any':
                $result = $itemResults['passed'] > 0;
                break;
            case 'all':
                $result = $itemResults['failed'] == 0;
                break;
        }

        return $result;
    }

    protected static function getRuleResult(string $attributeValue, string $referenceValue, string $operator): bool
    {
        return version_compare($attributeValue, $referenceValue, $operator);
    }

    protected function getContentTypeActionsInRuleset(string $contentType): array
    {
        $actions = [];

        $contentTypeRulesetArray = $this->ruleset[$contentType];

        if (!empty($contentTypeRulesetArray)) {
            foreach ($contentTypeRulesetArray as $contentTypeRuleset) {
                $actions[] = array_merge($actions, $contentTypeRuleset['actions']);
            }
        }

        return $actions;
    }

    protected function contentTypeActiveInRuleset(string $contentType): bool
    {
        return !empty($this->getContentTypeActionsInRuleset($contentType));
    }

    protected function getContentTypeActionsByRuleAttributeValues(string $contentType, array $ruleAttributeValues): array
    {
        $actions = [];

        try {
            if (isset($this->ruleset[$contentType])) {
                foreach ($this->ruleset[$contentType] as $contentTypeRuleset) {
                    $result = $this->getRuleArrayResultByRuleAttributeValues($contentTypeRuleset['rules'], $ruleAttributeValues, null);

                    if ($result) {
                        $actions = array_merge($actions, $contentTypeRuleset['actions']);
                    }
                }
            }

            return $actions;
        } catch (\Exception $e) {
            return [];
        }
    }
}
