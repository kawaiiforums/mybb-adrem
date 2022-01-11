<?php

namespace adrem\Assessment;

use adrem\Assessment;

class Core extends Assessment
{
    public function getRandomProportionAttribute(): float
    {
        return rand(0, 100) / 100;
    }

    public function get1Attribute(): int
    {
        return 1;
    }

    public function get0Attribute(): int
    {
        return 0;
    }

    public function getMycodeLinkCountAttribute(): int
    {
        return count(
            self::getMycodeLinkMatches($this->getContentEntityData()['content'])
        );
    }

    public function getMycodeLinkWithNewUrlCountAttribute(): int
    {
        $urlsCurrent = array_column(
            self::getMycodeLinkMatches($this->getContentEntityData()['content']),
            'url'
        );

        if ($this->contentEntity->dataRevisionExists('previous')) {
            $urlsPrevious = array_column(
                self::getMycodeLinkMatches($this->contentEntity->getData(false, 'previous')['content']),
                'url'
            );
        } else {
            $urlsPrevious = [];
        }

        $newUrls = array_diff($urlsCurrent, $urlsPrevious);

        return count($newUrls);
    }

    public function getMycodeNamedLinkCountAttribute(): int
    {
        return count(
            array_filter(
                self::getMycodeLinkMatches($this->getContentEntityData()['content']),
                function (array $matchSet) {
                    return (
                        $matchSet['url'] === null || // potential incomplete nested match
                        $matchSet['url'] !== $matchSet['name']
                    );
                }
            )
        );
    }

    public function getMycodeColorCountAttribute(): int
    {
        $parsedMessage = self::getParsedMessage($this->getContentEntityData()['content']);

        $matches = substr_count($parsedMessage, ' class="mycode_color">');

        return $matches;
    }

    public function getMycodeSizeCountAttribute(): int
    {
        $parsedMessage = self::getParsedMessage($this->getContentEntityData()['content']);

        $matches = substr_count($parsedMessage, ' class="mycode_size">');

        return $matches;
    }

    public function getTriggerWordsCountAttribute(): int
    {
        $matchedValues = [];

        $contentEntityData = $this->getContentEntityData();
        $contentEntityFields = array_intersect(
            [
                'title',
                'content',
            ],
            array_keys($contentEntityData)
        );

        $values = \adrem\getDelimitedSettingValues('assessment_core_trigger_words');

        foreach ($contentEntityFields as $field) {
            foreach ($values as $value) {
                if (!in_array($value, $matchedValues)) {
                    $result = preg_match('#\\b' . preg_quote($value) . '\\b#i', $contentEntityData[$field]);

                    if ($result === 1) {
                        $matchedValues[] = $value;
                    }
                }
            }
        }

        $uniqueMatches = count($matchedValues);

        return $uniqueMatches;
    }

    public function getWordfilterCountAttribute(): int
    {
        global $cache;

        $matches = 0;

        $contentEntityData = $this->getContentEntityData();
        $contentEntityFields = array_intersect(
            [
                'title',
                'content',
            ],
            array_keys($contentEntityData)
        );

        require_once MYBB_ROOT  . 'inc/class_parser.php';
        $parser = new \postParser;

        $badwords = $cache->read('badwords');

        if (is_array($badwords)) {
            foreach ($badwords as $badword) {
                if (!$badword['regex']) {
                    $badword['badword'] = $parser->generate_regex($badword['badword']);
                }

                foreach ($contentEntityFields as $field) {
                    $matches += preg_match_all('#' . $badword['badword'] . '#is', $contentEntityData[$field]);
                }
            }
        }

        return $matches;
    }

    private static function getMycodeLinkMatches(string $content): array
    {
        $matches = [];

        $parsedMessage = self::getParsedMessage(
            $content,
            [],
            [
                'mycode_url' => '<a data-adrem-placeholder="" data-url="{$url}">{$name}</a>',
            ]
        );

        preg_match_all(
            '#<a data-adrem-placeholder="" data-url="(?<url>[^<"]*?)">(?<name>.*?)</a>#',
            $parsedMessage,
            $matchSets,
            PREG_SET_ORDER
        );

        $exemptHostnames = \adrem\getDelimitedSettingValues('assessment_core_link_exception_hosts');

        foreach ($matchSets as $matchSet) {
            $host = parse_url($matchSet['url'], PHP_URL_HOST);

            if ($host === false || $host === null || !in_array($host, $exemptHostnames)) {
                $matches[] = $matchSet;
            }
        }

        // inflate results to account for failed matches from nested tags
        $placeholderCount = substr_count($parsedMessage, '<a data-adrem-placeholder="" ');
        $unprocessedMatches = $placeholderCount - count($matchSets);

        if ($unprocessedMatches !== 0) {
            $matches = array_merge(
                $matches,
                array_fill(0, $unprocessedMatches, [
                    'url' => null,
                    'name' => null,
                ])
            );
        }

        return $matches;
    }

    private static function getParsedMessage(string $message, array $parserOptions = [], array $templatePlaceholders = []): string
    {
        global $templates;

        $originalTemplates = [];

        foreach ($templatePlaceholders as $templateName => $templatePlaceholder) {
            $originalTemplates[$templateName] = $templates->cache[$templateName] ?? null;
            $templates->cache[$templateName] = $templatePlaceholder;
        }

        require_once MYBB_ROOT . 'inc/class_parser.php';
        $parser = new \postParser();

        $parserOptions = array_merge(
            [
                'allow_html' => 0,
                'allow_mycode' => 1,
                'allow_smilies' => 1,
                'allow_imgcode' => 1,
                'allow_videocode' => 1,
                'filter_badwords' => 1,
                'me_username' => 0,
                'shorten_urls' => 0,
                'highlight' => 0,
            ],
            $parserOptions
        );

        $parsedMessage = $parser->parse_message($message, $parserOptions);

        foreach ($templatePlaceholders as $templateName => $templatePlaceholder) {
            if ($originalTemplates[$templateName] === null) {
                unset($templates->cache[$templateName]);
            } else {
                $templates->cache[$templateName] = $originalTemplates[$templateName];
            }
        }

        return $parsedMessage;
    }
}
