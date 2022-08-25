<?php

namespace adrem\Assessment;

trait ParsingTrait
{
    private static function getMatchedWordsCount(array $values, array $words): int
    {
        $matchedWords = [];

        foreach ($words as $word) {
            if (!in_array($word, $matchedWords)) {
                $pattern = '#\\b' . preg_quote($word) . '\\b#i';
                $result = self::getMatchCount($pattern, $values, false);

                if ($result === 1) {
                    $matchedWords[] = $word;
                }
            }
        }

        $uniqueMatches = count($matchedWords);

        return $uniqueMatches;
    }

    private static function getWordfilterCount(array $values): int
    {
        global $cache;

        $count = 0;

        require_once MYBB_ROOT  . 'inc/class_parser.php';
        $parser = new \postParser;

        $badwords = $cache->read('badwords');

        if (is_array($badwords)) {
            foreach ($badwords as $badword) {
                if (!$badword['regex']) {
                    $badword['badword'] = $parser->generate_regex($badword['badword']);
                }

                $pattern = '#' . $badword['badword'] . '#is';
                $count += self::getMatchCount($pattern, $values);
            }
        }

        return $count;
    }

    private static function getMatchCount(string $pattern, array $values, bool $global = true): int
    {
        return count(
            self::getMatches($pattern, $values, $global)
        );
    }

    private static function getMatches(string $pattern, array $values, bool $global = true): array
    {
        $matches = [];

        foreach ($values as $value) {
            if ($global === true) {
                preg_match_all(
                    $pattern,
                    $value,
                    $localMatches,
                    PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
                );
            } else {
                $count = preg_match(
                    $pattern,
                    $value,
                    $localMatches,
                    PREG_UNMATCHED_AS_NULL
                );

                if ($count === 1) {
                    return $localMatches;
                }
            }

            $matches = array_merge($matches, $localMatches);
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