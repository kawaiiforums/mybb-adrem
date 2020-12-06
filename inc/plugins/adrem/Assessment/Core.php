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
                    return $matchSet['url'] !== $matchSet['name'];
                }
            )
        );
    }

    public function getTriggerWordsCountAttribute(): int
    {
        $matches = 0;

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
                $matches += preg_match_all('#\\b' . preg_quote($value) . '\\b#i', $contentEntityData[$field]);
            }
        }

        return $matches;
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
            "{\0ADREM_PLACEHOLDER\0{\$url}\0{\$name}\0}"
        );

        preg_match_all('#\\{\0ADREM_PLACEHOLDER\0(?<url>.*?)\0(?<name>.*?)\0\\}#', $parsedMessage, $matchSets, PREG_SET_ORDER);

        $exemptHostnames = \adrem\getDelimitedSettingValues('assessment_core_link_exception_hosts');

        foreach ($matchSets as $matchSet) {
            $host = parse_url($matchSet['url'], PHP_URL_HOST);

            if ($host === false || $host === null || !in_array($host, $exemptHostnames)) {
                $matches[] = $matchSet;
            }
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
