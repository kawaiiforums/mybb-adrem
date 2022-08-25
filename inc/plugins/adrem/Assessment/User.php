<?php

namespace adrem\Assessment;

use adrem\Assessment;

class User extends Assessment
{
    protected \adrem\ContentEntity $userEntity;

    public function getAttributeValues(): array
    {
        if (\adrem\contentTypeContextPassable($this->contentEntity::getName(), 'user')) {
            $this->userEntity = \adrem\getContentEntityByContext('user', $this->contentEntity);
        }

        return parent::getAttributeValues();
    }

    public function getPostCountAttribute(): int
    {
        return $this->userEntity->getData(true)['postnum'];
    }

    public function getPostCountLastHourAttribute(): int
    {
        global $db;

        return $db->fetch_field(
            $db->simple_select(
                'posts',
                'COUNT(tid) as n',
                'uid = ' . (int)$this->userEntity->getId() . ' AND visible IN (-1,0,1) AND dateline >= ' . (TIME_NOW - 3600)
            ),
            'n'
        );
    }

    public function getThreadCountLastHourAttribute(): int
    {
        global $db;

        return $db->fetch_field(
            $db->simple_select(
                'threads',
                'COUNT(tid) as n',
                'uid = ' . (int)$this->userEntity->getId() . ' AND visible IN (-1,0,1) AND dateline >= ' . (TIME_NOW - 3600)
            ),
            'n'
        );
    }

    public function getDaysRegisteredAttribute(): int
    {
        $secondsElapsed = \TIME_NOW - (int)$this->userEntity->getData(true)['regdate'];

        return floor($secondsElapsed / 86400);
    }

    public function getWarningPointsAttribute(): int
    {
        return $this->userEntity->getData(true)['warningpoints'];
    }

    public function getWebsiteFieldFilledAttribute(): int
    {
        return $this->userEntity->getData(true)['website'] !== '';
    }
}
