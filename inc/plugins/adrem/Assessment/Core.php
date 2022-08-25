<?php

namespace adrem\Assessment;

use adrem\Assessment;

class Core extends Assessment
{
    use ParsingTrait;

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

        $count = substr_count($parsedMessage, ' class="mycode_color">');

        return $count;
    }

    public function getMycodeSizeCountAttribute(): int
    {
        $parsedMessage = self::getParsedMessage($this->getContentEntityData()['content']);

        $count = substr_count($parsedMessage, ' class="mycode_size">');

        return $count;
    }

    public function getTriggerWordsCountAttribute(): int
    {
        return self::getMatchedWordsCount(
            \adrem\getArraySubset(
                $this->getContentEntityData(),
                [
                    'title',
                    'content',
                ]
            ),
            \adrem\getDelimitedSettingValues('assessment_core_trigger_words'),
        );
    }

    public function getWordfilterCountAttribute(): int
    {
        return self::getWordfilterCount(
            \adrem\getArraySubset(
                $this->getContentEntityData(),
                [
                    'title',
                    'content',
                ]
            ),
        );
    }
}
