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

    protected function loadAttributeValues(): bool
    {
        $url = 'https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze?key=' . $this->getApiKey();

        $data = \adrem\getJsonApiResponse($url, [
            'comment' => [
                'text' => $this->contentEntity->getData()['content'],
            ],
            'languages' => [
                'en'
            ],
            'requestedAttributes' => array_fill_keys($this->requestedAttributes, new \stdClass()),
            'doNotStore' => true,
        ]);

        if ($data !== false) {
            if (isset($data['attributeScores']) && is_array($data['attributeScores'])) {
                foreach ($this->requestedAttributes as $attributeName) {
                    $value = &$data['attributeScores'][$attributeName]['summaryScore']['value'];

                    if (isset($value)) {
                        $this->attributeValues[$attributeName] = $value;
                    }
                }
            }

            $this->setResultData($data);

            return true;
        } else {
            return false;
        }
    }

    protected function getApiKey(): ?string
    {
        return \adrem\getSettingValue('perspective_api_key');
    }
}
