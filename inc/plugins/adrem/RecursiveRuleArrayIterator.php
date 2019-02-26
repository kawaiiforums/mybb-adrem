<?php

namespace adrem;

class RecursiveRuleArrayIterator extends \RecursiveArrayIterator
{
    public function hasChildren(): bool
    {
        return is_array($this->current()) && is_array(current($this->current()));
    }
}
