<?php

namespace adrem\Assessment;

use adrem\Assessment;

class StopForumSpam extends Assessment
{
    const VERSION = '1.6';

    protected \adrem\ContentEntity $userEntity;

    public static function getProvidedAttributes(): array
    {
        return array_keys(self::getAttributeCombinations());
    }

    public function getAttributeValues(): array
    {
        if (\adrem\contentTypeContextPassable($this->contentEntity::getName(), 'user')) {
            $this->userEntity = \adrem\getContentEntityByContext('user', $this->contentEntity);
        }

        return parent::getAttributeValues();
    }

    protected static function getAttributeCombinations(): array
    {
        static $attributeCombinations;

        if ($attributeCombinations === null) {
            $attributeCombinations = [];

            foreach (self::getScoreTypes() as $scoreTypeName => $scoreType) {
                foreach (self::getInputTypes() as $inputTypeName => $inputType) {
                    $name = $inputTypeName . ucfirst($scoreTypeName);

                    $attributeCombinations[$name] = [
                        'inputTypeName' => $inputTypeName,
                        'inputType' => $inputType,
                        'scoreTypeName' => $scoreTypeName,
                        'scoreType' => $scoreType,
                    ];
                }
            }
        }

        return $attributeCombinations;
    }

    protected static function getApiEndpointUrl(): string
    {
        return 'https://api.stopforumspam.org/api';
    }

    /**
     * @return array<string,array{
     *   name: string,
     *   value: callable(array): string,
     * }>
     */
    protected static function getInputTypes(): array
    {
        return [
            'email' => [
                'name' => 'email',
                'value' => fn ($data) => $data['email'],
            ],
            'emailHash' => [
                'name' => 'emailhash',
                'value' => fn ($data) => hash('md5', $data['email']),
            ],
            'lastIp' => [
                'name' => 'ip',
                'value' => fn ($data) => \my_inet_ntop($data['lastip']),
            ],
            'registrationIp' => [
                'name' => 'ip',
                'value' => fn ($data) => \my_inet_ntop($data['regip']),
            ],
            'username' => [
                'name' => 'username',
                'value' => fn ($data) => $data['username'],
            ],
        ];
    }

    protected static function getScoreTypes(): array
    {
        return [
            'confidence' => [
                'name' => 'confidence',
                'default' => 0,
            ],
            'frequency' => [
                'name' => 'frequency',
                'default' => 0,
            ],
        ];
    }

    protected function loadAttributeValues(): bool
    {
        $attributeCombinations = self::getAttributeCombinations();

        $attributeRequestData = [];
        $requestedAttributeCombinations = [];
        $requestedAttributeCombinationValues = [];

        $userData = $this->userEntity->getData(true);

        foreach ($this->requestedAttributes as $attributeName) {
            $attributeCombination = $attributeCombinations[$attributeName];
            $value = $attributeCombination['inputType']['value']($userData);

            $attributeRequestData[ $attributeCombination['inputType']['name'] ] = $value;
            $requestedAttributeCombinations[$attributeName] = $attributeCombination;
            $requestedAttributeCombinationValues[$attributeName] = $value;
        }

        $requestedScoreTypeNames = array_column($requestedAttributeCombinations, 'scoreTypeName');

        $requestData = [
            'confidence' => in_array('confidence', $requestedScoreTypeNames),
            'json' => 1,
        ] + $attributeRequestData;

        // fetch_remote_file() doesn't process arrays; API doesn't accept `application/json` requests
        $query = http_build_query($requestData);

        $response = \fetch_remote_file(static::getApiEndpointUrl() . '?' . $query);

        if ($response !== false) {
            $responseData = json_decode($response, true);

            if ($responseData !== null && ($responseData['success'] ?? null) === 1) {
                foreach ($requestedAttributeCombinations as $attributeName => $attributeCombination) {
                    $inputTypeResultEntries = &$responseData[ $attributeCombination['inputType']['name'] ];

                    if (isset($inputTypeResultEntries)) {
                        if (isset($inputTypeResultEntries['value'])) {
                            // API may return a single entry directly instead of an array
                            $inputTypeResultEntries = [$inputTypeResultEntries];
                        }

                        $attributeValue = null;

                        foreach ($inputTypeResultEntries as $entry) {
                            if ($entry['value'] === $requestedAttributeCombinationValues[$attributeName]) {
                                $attributeValue = $entry[ $attributeCombination['scoreType']['name'] ] ?? null;

                                break;
                            }
                        }

                        $this->attributeValues[$attributeName] =
                            $attributeValue ?? $attributeCombination['scoreType']['default'];
                    }
                }

                $this->setResultData($responseData);

                return true;
            }
        }

        return false;
    }
}
