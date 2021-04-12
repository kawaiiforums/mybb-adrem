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

        $content = str_replace("\0", null, $content);

        $parsedMessage = self::getParsedMessageWithTemplatePlaceholder(
            $content,
            'mycode_url',
            '<adrem_link_placeholder url="{$url}" name="{$name}" />'
        );

        preg_match_all('#<adrem_link_placeholder url="(?<url>[^<"]*?)" name="(?<name>[^<"]*?)" />#', $parsedMessage, $matchSets, PREG_SET_ORDER);

        $exemptHostnames = \adrem\getDelimitedSettingValues('assessment_core_link_exception_hosts');

        foreach ($matchSets as $matchSet) {
            $host = parse_url($matchSet['url'], PHP_URL_HOST);

            if ($host === false || $host === null || !in_array($host, $exemptHostnames)) {
                $matches[] = $matchSet;
            }
        }

        // inflate results to account for failed matches from nested tags
        $placeholderCount = substr_count($parsedMessage, '<adrem_link_placeholder ');
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

    private static function getParsedMessageWithTemplatePlaceholder(string $message, string $templateName, string $placeholder, array $parserOptions = []): string
    {
        global $templates;

        $originalTemplate = $templates->cache[$templateName] ?? null;
        $templates->cache[$templateName] = $placeholder;

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

        if ($originalTemplate === null) {
            unset($templates->cache[$templateName]);
        } else {
            $templates->cache[$templateName] = $originalTemplate;
        }

        return $parsedMessage;
    }
}
