<?php

namespace adrem;

class Ruleset
{
    public static array $supportedRuleOperators = [
        '<',
        '<=',
        '>',
        '>=',
        '=',
        '!=',
    ];

    public static array $supportedRuleGroupOperators = [
        'any',
        'all',
    ];

    protected array $ruleset;

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
            $results['errors'][] = ['INVALID_JSON'];
        } else {
            $ruleset = $value;

            if (!is_array($ruleset)) {
                $results['errors'][] = ['RULESET_NOT_AN_ARRAY'];
            } else {
                foreach ($ruleset as $contentType => $contentTypeRuleset) {
                    if (!\adrem\contentTypeExists($contentType)) {
                        $results['warnings'][] = ['CONTENT_TYPE_NOT_FOUND', compact('contentType')];

                        $contentTypeExists = false;
                    } else {
                        $contentTypeExists = true;

                        if (!\adrem\contentTypeDiscoverable($contentType)) {
                            $results['warnings'][] = ['CONTENT_TYPE_NOT_DISCOVERABLE', compact('contentType')];
                        }
                    }

                    foreach ($contentTypeRuleset as $conditional) {
                        if (isset($conditional['events']) && !is_array($conditional['events'])) {
                            $results['errors'][] = ['EVENT_LIST_NOT_AN_ARRAY', compact('contentType')];
                        }

                        if (!isset($conditional['rules']) || !is_array($conditional['rules'])) {
                            $results['errors'][] = ['NO_RULES_ARRAY_IN_CONDITIONAL', compact('contentType')];
                        } else {
                            $results = array_merge_recursive(
                                $results,
                                static::getRuleArrayValidationResults($conditional['rules'], $contentType, true)
                            );
                        }

                        if (!isset($conditional['actions']) || !is_array($conditional['actions'])) {
                            $results['errors'][] = ['NO_ACTIONS_ARRAY_IN_CONDITIONAL', compact('contentType')];
                        } elseif ($contentTypeExists) {
                            foreach ($conditional['actions'] as $action) {
                                if (substr_count($action, ':') == 1) {
                                    [$actionContentType, $actionName] = explode(':', $action);
                                } else {
                                    $actionContentType = $contentType;
                                    $actionName = $action;
                                }

                                if (
                                    !\adrem\contentTypeExists($actionContentType) ||
                                    !\adrem\contentTypeActionExists($actionContentType, $actionName)
                                ) {
                                    $results['warnings'][] = ['ACTION_NOT_FOUND', compact('contentType', 'actionContentType', 'actionName')];
                                } else {
                                    if (
                                        $contentTypeExists &&
                                        $actionContentType != $contentType &&
                                        !\adrem\contentTypeContextPassable($contentType, $actionContentType)
                                    ) {
                                        $results['warnings'][] = ['CONTENT_TYPE_CONTEXT_NOT_PASSABLE', compact('contentType', 'actionContentType', 'actionName')];
                                    }
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

    public function getRuleAssessmentAttributesForContentType(string $contentType, array $events = []): array
    {
        $assessmentAttributes = [];

        if (isset($this->ruleset[$contentType])) {
            foreach ($this->ruleset[$contentType] as $contentTypeRuleset) {
                if (!isset($contentTypeRuleset['events']) || array_intersect($contentTypeRuleset['events'], $events)) {
                    $iterator = new \RecursiveIteratorIterator(
                        new RecursiveRuleArrayIterator($contentTypeRuleset['rules'])
                    );

                    $rules = iterator_to_array($iterator, false);

                    foreach ($rules as $rule) {
                        extract(self::getRuleFromSimpleArray($rule));
                        /**
                         * @var string $assessmentName
                         * @var string $attributeName
                         * @var string $operator
                         * @var string $referenceValue
                         * @var array $flags
                         */

                        $revisions = [
                            getContentEntity($contentType)->getDefaultRevision(),
                        ];

                        if (in_array('change', $flags)) {
                            $revisions[] = 'previous';
                        }

                        foreach ($revisions as $revision) {
                            if (!isset($assessmentAttributes[$revision][$assessmentName])) {
                                $assessmentAttributes[$revision][$assessmentName] = [];
                            }

                            if (!in_array($attributeName, $assessmentAttributes[$revision][$assessmentName])) {
                                $assessmentAttributes[$revision][$assessmentName][] = $attributeName;
                            }
                        }
                    }
                }
            }
        }

        return $assessmentAttributes;
    }

    public function getContentTypeActionsByAssessmentAttributeValues(string $contentType, array $events = [], array $assessmentResults = []): array
    {
        $attributeValues = [];

        foreach ($assessmentResults as $revision => $assessmentAttributeValues) {
            foreach ($assessmentAttributeValues as $assessmentName => $assessmentAttributeValueSet) {
                foreach ($assessmentAttributeValueSet as $attributeName => $value) {
                    $attributeValues[$revision][$assessmentName . ':' . $attributeName] = $value;
                }
            }
        }

        return $this->getContentTypeActions($contentType, $events, null, $attributeValues);
    }

    public function getContentTypeActions(string $contentType, array $events = [], ?Inspection $inspection = null, ?array $ruleAttributeValues = null): array
    {
        $actions = [];

        try {
            if (isset($this->ruleset[$contentType])) {
                foreach ($this->ruleset[$contentType] as $contentTypeRuleset) {
                    if (!isset($contentTypeRuleset['events']) || array_intersect($contentTypeRuleset['events'], $events)) {
                        $result = $this->getRuleArrayResult($contentTypeRuleset['rules'], null, $inspection, $ruleAttributeValues);

                        if ($result === true) {
                            $actions = array_merge($actions, $contentTypeRuleset['actions']);
                        }
                    }
                }
            }

            return array_unique($actions);
        } catch (\Exception $e) {
            if (isset($GLOBALS['error_handler'])) {
                $GLOBALS['error_handler']->error(E_USER_ERROR, $e->getMessage(), $e->getFile(), $e->getLine());
            }

            return [];
        }
    }

    protected static function getRuleArrayValidationResults(array $ruleArray, string $contentType, bool $rootLevel = false): array
    {
        $results = [
            'errors' => [],
            'warnings' => [],
        ];

        if ($rootLevel == true && count($ruleArray) > 1) {
            $results['errors'][] = ['NO_ROOT_RULE_GROUP', compact('contentType')];
        } else {
            foreach ($ruleArray as $item) {
                if (!is_array($item)) {
                    $results['errors'][] = ['NOT_AN_ARRAY', compact('contentType')];
                } else {
                    if (count($item) === 3 && is_string($item[0]) && is_string($item[1]) && is_string($item[2])) {
                        if ($rootLevel) {
                            $results['errors'][] = ['NO_ROOT_RULE_GROUP', compact('contentType')];
                        } else {
                            try {
                                extract(self::getRuleFromSimpleArray($item));
                                /**
                                 * @var string $assessmentName
                                 * @var string $attributeName
                                 * @var string $operator
                                 * @var string $referenceValue
                                 * @var array $flags
                                 */

                                if (!\adrem\assessmentExists($assessmentName)) {
                                    $results['warnings'][] = ['ASSESSMENT_NOT_FOUND',  compact('assessmentName')];
                                } else {
                                    if (!\adrem\assessmentProvidesAttribute($assessmentName, $attributeName)) {
                                        $results['warnings'][] = ['ASSESSMENT_ATTRIBUTE_NOT_PROVIDED', compact('assessmentName', 'attributeName')];
                                    }
                                }

                                if (!in_array($operator, static::$supportedRuleOperators)) {
                                    $results['errors'][] = ['RULE_OPERATOR_NOT_SUPPORTED', compact('operator')];
                                }
                            } catch (\UnexpectedValueException $e) {
                                $results['errors'][] = [$e->getMessage(), compact('contentType')];
                            }
                        }
                    } elseif (count($item) == 1 && is_array(current($item))) {
                        $operator = array_keys($item)[0];

                        if (!in_array($operator, static::$supportedRuleGroupOperators)) {
                            $results['errors'][] = ['RULE_GROUP_OPERATOR_INVALID', compact('operator')];
                        } else {
                            $results = array_merge_recursive(
                                $results,
                                static::getRuleArrayValidationResults(current($item), $contentType)
                            );
                        }
                    } else {
                        $results['errors'][] = ['NOT_RULE_OR_GROUP', compact('contentType')];
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @throws \Exception
     */
    protected static function getRuleArrayResult(array $ruleArray, ?string $ruleGroupOperator, ?Inspection $inspection = null, ?array $ruleAttributeValues = null): bool
    {
        $result = null;

        $itemResults = [
            'operands' => 0,
            'failed' => 0,
            'passed' => 0,
        ];

        $rulesCount = count($ruleArray);

        foreach ($ruleArray as $item) {
            if (is_array(current($item))) {
                $localRoleGroupOperator = array_keys($item)[0];

                $itemResult = static::getRuleArrayResult(current($item), $localRoleGroupOperator, $inspection, $ruleAttributeValues);
            } else {
                $rule = static::getRuleFromSimpleArray($item);

                if ($inspection) {
                    $attributeValue = $inspection->getAssessmentAttributeValue($rule['assessmentName'], $rule['attributeName']);
                } else {
                    $revision = 'current';

                    $attributeValue = $ruleAttributeValues[$revision][$rule['attributeName']] ?? null;
                }

                if ($attributeValue === null) {
                    throw new \Exception('Attempting to use non-provided `' . $rule['attributeName'] . '` attribute');
                }

                if (in_array('change', $rule['flags'])) {
                    $revision = 'previous';

                    if ($inspection) {
                        $previousAttributeValue = $inspection->getAssessmentAttributeValue($rule['assessmentName'], $rule['attributeName'], $revision);
                    } else {
                        $previousAttributeValue = $ruleAttributeValues[$revision][$rule['attributeName']] ?? null;
                    }

                    if ($previousAttributeValue === null) {
                        throw new \Exception('Attempting to use non-provided `' . $rule['attributeName'] . '` attribute (revision: `' . $revision . '`)');
                    }

                    $attributeValue -= $previousAttributeValue;
                }

                $itemResult = static::getRuleResult($attributeValue, $rule['referenceValue'], $rule['operator']);
            }

            $itemResults['operands']++;
            $itemResults[$itemResult ? 'passed' : 'failed']++;

            if ($ruleGroupOperator !== null) {
                $shortCircuitResult = static::getRuleGroupResult($itemResults, $ruleGroupOperator, $rulesCount);

                if ($shortCircuitResult !== null) {
                    $result = $shortCircuitResult;
                    break;
                }
            }
        }

        if ($ruleGroupOperator === null) {
            $result = $itemResults['passed'] == 1;
        }

        return $result;
    }

    protected static function getRuleGroupResult(array $itemResults, string $operator, ?int $rulesCount): ?bool
    {
        $result = null;

        switch ($operator) {
            case 'any':
                if ($itemResults['passed'] > 0) {
                    $result = true;
                } elseif ($rulesCount === null || $itemResults['operands'] === $rulesCount) {
                    $result = false;
                }

                break;
            case 'all':
                if ($itemResults['failed'] > 0) {
                    $result = false;
                } elseif ($rulesCount === null || $itemResults['operands'] === $rulesCount) {
                    $result = $itemResults['failed'] == 0;
                }

                break;
        }

        return $result;
    }

    /**
     * @throws \Exception
     */
    protected static function getRuleResult(string $attributeValue, string $referenceValue, string $operator): bool
    {
        switch ($operator) {
            case '<':
                $result = $attributeValue < $referenceValue;
                break;
            case '<=':
                $result = $attributeValue <= $referenceValue;
                break;
            case '>':
                $result = $attributeValue > $referenceValue;
                break;
            case '>=':
                $result = $attributeValue >= $referenceValue;
                break;
            case '=':
                $result = $attributeValue == $referenceValue;
                break;
            case '!=':
                $result = $attributeValue != $referenceValue;
                break;
            default:
                throw new \Exception('Attempting to use unsupported rule operator `' . $operator . '`');
        }

        return $result;
    }

    protected static function getRuleFromSimpleArray(array $array): array
    {
        [$attribute, $operator, $referenceValue] = $array;
        $flags = [];

        if (mb_substr($attribute, 0, 1) === "\u{0394}") { // Δ
            $flags[] = 'change';
            $attribute = mb_substr($attribute, 1);
        }

        if (substr_count($attribute, ':') !== 1) {
            throw new \UnexpectedValueException('RULE_ATTRIBUTE_INVALID');
        } else {
            [$assessmentName, $attributeName] = explode(':', $attribute);

            return [
                'assessmentName' => $assessmentName,
                'attributeName' => $attributeName,
                'operator' => $operator,
                'referenceValue' => $referenceValue,
                'flags' => $flags,
            ];
        }
    }

    protected function getContentTypeActionsInRuleset(string $contentType, array $events = []): array
    {
        $actions = [];

        $contentTypeRulesetArray = $this->ruleset[$contentType];

        if (!empty($contentTypeRulesetArray)) {
            foreach ($contentTypeRulesetArray as $contentTypeRuleset) {
                if (!isset($contentTypeRuleset['events']) || array_intersect($contentTypeRuleset['events'], $events)) {
                    $actions[] = array_merge($actions, $contentTypeRuleset['actions']);
                }
            }
        }

        return $actions;
    }

    protected function contentTypeActiveInRuleset(string $contentType): bool
    {
        return !empty($this->getContentTypeActionsInRuleset($contentType));
    }
}
