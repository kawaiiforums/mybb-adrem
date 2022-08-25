<?php

namespace adrem\Assessment;

use adrem\Assessment;

class Post extends Assessment
{
    public function getSecondsSinceInsertAttribute(): int
    {
        $time = $this->getContentEntityData(true)['dateline'];

        $result = \TIME_NOW - $time;

        return $result;
    }

    public function getSecondsSinceLastUpdateAttribute(): int
    {
        $time = $this->contentEntity->getData(false, 'previous')['edittime'];

        if ($time == 0) {
            // first update
            $result = 0;
        } else {
            $result = \TIME_NOW - $time;
        }

        return $result;
    }
}
