<?php

namespace adrem\Assessment;

use adrem\Assessment;

class Perspective extends Assessment
{
    const VERSION = 'v1alpha1';

    public static function getProvidedAttributes(): array
    {
        return [
            'TOXICITY',
            'SEVERE_TOXICITY',
            'IDENTITY_ATTACK',
            'INSULT',
            'PROFANITY',
            'SEXUALLY_EXPLICIT',
            'THREAT',
            'FLIRTATION',
            'ATTACK_ON_AUTHOR',
            'ATTACK_ON_COMMENTER',
            'INCOHERENT',
            'INFLAMMATORY',
            'LIKELY_TO_REJECT',
            'OBSCENE',
            'SPAM',
            'UNSUBSTANTIAL',
        ];
    }

    public function submitSuggestedAttributeValues(): bool
    {
        if ($this->suggestedAttributeValues) {
            $attributeScores = [];

            foreach ($this->suggestedAttributeValues as $attributeName => $attributeValue) {
                $attributeScores[$attributeName] = [
                    'summaryScore' => [
                        'value' => $attributeValue,
                    ],
                ];
            }

            $requestData = [
                'comment' => [
                    'text' => \adrem\getPlaintextContent($this->contentEntity->getData()['content']),
                ],
                'languages' => [
                    'en',
                ],
                'attributeScores' => $attributeScores,
            ];

            $communityId = \adrem\getSettingValue('assessment_perspective_community_id');

            if ($communityId) {
                $requestData['communityId'] = $communityId;
            }

            $responseData = \adrem\getJsonApiResponse(static::getApiEndpointUrl('comments:suggestscore'), $requestData);

            return $responseData !== false;
        } else {
            return false;
        }
    }

    public static function supportsAttributeValueSuggestions(string $version): bool
    {
        return $version == static::VERSION;
    }

    protected static function getApiEndpointUrl(string $action): string
    {
        return 'https://commentanalyzer.googleapis.com/' . static::VERSION . '/' . $action . '?key=' . static::getApiKey();
    }

    protected static function getApiKey(): ?string
    {
        return \adrem\getSettingValue('assessment_perspective_api_key');
    }

    protected static function requestedDoNotStore(): bool
    {
        return (bool)\adrem\getSettingValue('assessment_perspective_do_not_store');
    }

    protected function loadAttributeValues(): bool
    {
        $requestData = [
            'comment' => [
                'text' => \adrem\getPlaintextContent($this->contentEntity->getData()['content']),
            ],
            'languages' => [
                'en',
            ],
            'requestedAttributes' => array_fill_keys($this->requestedAttributes, new \stdClass()),
            'doNotStore' => static::requestedDoNotStore(),
        ];

        $responseData = \adrem\getJsonApiResponse(static::getApiEndpointUrl('comments:analyze'), $requestData);

        if ($responseData !== false) {
            if (isset($responseData['attributeScores']) && is_array($responseData['attributeScores'])) {
                foreach ($this->requestedAttributes as $attributeName) {
                    $value = &$responseData['attributeScores'][$attributeName]['summaryScore']['value'];

                    if (isset($value)) {
                        $this->attributeValues[$attributeName] = $value;
                    }
                }
            }

            $this->setResultData($responseData);

            return true;
        } else {
            return false;
        }
    }
}
