<?php

namespace adrem;

// Rulesets
function getRuleset(): Ruleset
{
    static $ruleset;

    if (!$ruleset) {
        $ruleset = new Ruleset(
            \adrem\getCacheValue('ruleset')
        );
    }

    return $ruleset;
}

// Content Types
function contentTypeExists(string $name): bool
{
    return class_exists('\adrem\ContentEntity\\' . ucfirst($name));
}

function getContentEntity(string $contentType, ?int $contentEntityId = null): ?ContentEntity
{
    if (\adrem\contentTypeExists($contentType)) {
        $class = '\adrem\ContentEntity\\' . ucfirst($contentType);

        /* @var ContentEntity $entity */
        $entity = new $class($contentEntityId);

        return $entity;
    } else {
        return null;
    }
}

function contentEntityAccessibleForCurrentUser(string $contentType, int $contentEntityId): bool
{
    $entity = \adrem\getContentEntity($contentType, $contentEntityId);

    return $entity->accessibleForCurrentUser();
}

function getContentEntityUrl(string $contentType, int $contentEntityId): ?string
{
    $entity = \adrem\getContentEntity($contentType, $contentEntityId);

    return $entity->getUrl();
}

function getContentTypeEntityActions(string $contentType): array
{
    $class = \adrem\getContentEntity($contentType);

    return $class::getSupportedActions();
}

function contentTypeActionExists(string $contentType, string $action): bool
{
    return in_array($action, \adrem\getContentTypeEntityActions($contentType));
}

// Inspections
function discoverContentEntity(ContentEntity $contentEntity): void
{
    \adrem\inspectContentEntity($contentEntity);
}

function inspectContentEntity(ContentEntity $contentEntity): void
{
    global $db;

    $inspection = new Inspection();

    $inspection->setRuleset(\adrem\getRuleset());
    $inspection->setContentEntity($contentEntity);

    $inspection->persist($db, true);
    $inspection->run();

    $contentEntity->triggerActions(
        $inspection->getContentTypeActions()
    );
}

// Assessments
function assessmentExists(string $name): bool
{
    return class_exists('\adrem\Assessment\\' . ucfirst($name));
}

function assessmentProvidesAttribute(string $assessmentName, string $attributeName): bool
{
    $class = \adrem\getAssessment($assessmentName);

    $providedAttributes = $class::getProvidedAttributes();

    return in_array($attributeName, $providedAttributes);
}

function assessmentSupportsAttributeValueSuggestions(string $assessmentName, string $version): bool
{
    $class = \adrem\getAssessment($assessmentName);

    return $class::supportsAttributeValueSuggestions($version);
}

function getAssessment(string $name, ?int $id = null): ?Assessment
{
    if (\adrem\assessmentExists($name)) {
        $class = '\adrem\Assessment\\' . ucfirst($name);

        /* @var Assessment $assessment */
        $assessment = new $class($id);

        return $assessment;
    } else {
        return null;
    }
}

// modules
function getModuleNames(bool $useCache = true): array
{
    if ($useCache) {
        $moduleNames = \adrem\getCacheValue('modules') ?? [];
    } else {
        $moduleNames = [];

        $directory = new \DirectoryIterator(MYBB_ROOT . 'inc/plugins/adrem/modules');

        foreach ($directory as $file) {
            if (!$file->isDot() && $file->isDir()) {
                $moduleNames[] = $file->getFilename();
            }
        }
    }

    return $moduleNames;
}

function loadModules(array $moduleNames): void
{
    foreach ($moduleNames as $moduleName) {
        require_once MYBB_ROOT . 'inc/plugins/adrem/modules/' . $moduleName . '/module.php';
    }
}

function registerSettings(array $settings): void
{
    global $adremRegisteredSettings;

    if ($adremRegisteredSettings === null) {
        $adremRegisteredSettings = [];
    }

    $adremRegisteredSettings = array_merge($adremRegisteredSettings, $settings);
}

function getRegisteredSettings(): array
{
    global $adremRegisteredSettings;

    return $adremRegisteredSettings ?? [];
}

// miscellaneous
function forumIsMonitored(int $forumId): bool
{
    $values = \adrem\getCsvSettingValues('monitored_forums');

    return (
        in_array($forumId, $values) ||
        in_array(-1, $values)
    );
}

function userIsMonitored(?int $userId = null): bool
{
    $values = \adrem\getCsvSettingValues('monitored_groups');

    return (
        \is_member($values, $userId ?? false) ||
        in_array(-1, $values)
    );
}

function getJsonApiResponse(string $url, ?array $data = null)
{
    global $config;

    if (!\my_validate_url($url, true)) {
        return false;
    }

    $url_components = @parse_url($url);

    if (!isset($url_components['scheme'])) {
        $url_components['scheme'] = 'https';
    }
    if (!isset($url_components['port'])) {
        $url_components['port'] = $url_components['scheme'] == 'https' ? 443 : 80;
    }

    if (
        !$url_components ||
        empty($url_components['host']) ||
        (!empty($url_components['scheme']) && !in_array($url_components['scheme'], ['http', 'https'])) ||
        (!in_array($url_components['port'], [80, 8080, 443])) ||
        (!empty($config['disallowed_remote_hosts']) && in_array($url_components['host'], $config['disallowed_remote_hosts']))
    ) {
        return false;
    }

    $addresses = \get_ip_by_hostname($url_components['host']);
    $destination_address = $addresses[0];

    if (!empty($config['disallowed_remote_addresses'])) {
        foreach ($config['disallowed_remote_addresses'] as $disallowed_address) {
            $ip_range = \fetch_ip_range($disallowed_address);
            $packed_address = \my_inet_pton($destination_address);

            if (is_array($ip_range)) {
                if (strcmp($ip_range[0], $packed_address) <= 0 && strcmp($ip_range[1], $packed_address) >= 0) {
                    return false;
                }
            } elseif ($destination_address == $disallowed_address) {
                return false;
            }
        }
    }

    $curlopt = [
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FOLLOWLOCATION => 0,
        CURLOPT_CONNECT_TO => [
            $url_components['host'].':'.$url_components['port'].':'.$destination_address,
        ],
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
    ];

    if ($data !== null) {
        $curlopt[CURLOPT_POST] = 1;
        $curlopt[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    if ($ca_bundle_path = \get_ca_bundle_path()) {
        $curlopt[CURLOPT_SSL_VERIFYPEER] = 1;
        $curlopt[CURLOPT_CAINFO] = $ca_bundle_path;
    } else {
        $curlopt[CURLOPT_SSL_VERIFYPEER] = 0;
    }

    $ch = curl_init();

    curl_setopt_array($ch, $curlopt);

    $response = curl_exec($ch);

    curl_close($ch);

    if ($response !== false) {
        $data = json_decode($response, true);

        if ($data !== null) {
            return $data;
        }
    }

    return false;
}
