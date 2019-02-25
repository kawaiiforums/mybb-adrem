<?php

namespace adrem;

class RecursiveRuleArrayIterator extends \RecursiveArrayIterator
{
    public function hasChildren()
    {
        return is_array($this->current()) && is_array(current($this->current()));
    }
}
