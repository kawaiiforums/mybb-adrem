<?php

namespace adrem\Assessment;

use adrem\Assessment;

class User extends Assessment
{
    /** @var \adrem\ContentEntity\User $userEntity */
    protected $userEntity;

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

    public function getDaysRegisteredAttribute(): int
    {
        $secondsElapsed = \TIME_NOW - (int)$this->userEntity->getData(true)['regdate'];

        return floor($secondsElapsed / 86400);
    }

    public function getWarningPointsAttribute(): int
    {
        return $this->userEntity->getData(true)['warningpoints'];
    }
}
