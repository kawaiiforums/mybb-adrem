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

    public function getWordfilterMatchesAttribute(): int
    {
        global $cache;

        $matches = 0;

        require_once MYBB_ROOT  . 'inc/class_parser.php';
        $parser = new \postParser;

        $badwords = $cache->read('badwords');

        if (is_array($badwords)) {
            foreach ($badwords as $badword) {
                if (!$badword['regex']) {
                    $badword['badword'] = $parser->generate_regex($badword['badword']);
                }

                $matches += preg_match_all('#' . $badword['badword'] . '#is', $this->contentEntity->getData()['content']);
            }
        }

        return $matches;
    }
}
