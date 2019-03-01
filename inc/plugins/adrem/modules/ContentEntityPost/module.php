<?php

require __DIR__ . '/hooks.php';

\adrem\registerSettings([
    'monitored_forums' => [
        'title'       => 'Monitored Forums',
        'description' => 'Select which forums\' content should be assessed.',
        'optionscode' => 'forumselect',
        'value'       => '-1',
    ],
]);

\adrem\addHooksNamespace('adrem\modules\ContentEntityPost\Hooks');
