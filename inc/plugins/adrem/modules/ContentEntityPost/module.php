<?php

namespace adrem\modules\ContentEntityPost;

require __DIR__ . '/hooks.php';

\adrem\registerSettings([
    'contententity_post_monitored_forums' => [
        'title' => 'Post Content Entity: Monitored Forums',
        'description' => 'Select which forums\' content should be assessed.',
        'optionscode' => 'forumselect',
        'value' => '-1',
    ],
]);

\adrem\addHooksNamespace('adrem\modules\ContentEntityPost\Hooks');

function forumIsMonitored(int $forumId): bool
{
    $values = \adrem\getCsvSettingValues('contententity_post_monitored_forums');

    return (
        in_array($forumId, $values) ||
        in_array(-1, $values)
    );
}
