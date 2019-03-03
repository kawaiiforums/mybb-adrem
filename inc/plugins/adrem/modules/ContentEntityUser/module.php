<?php

namespace adrem\modules\ContentEntityPost;

\adrem\registerSettings(function () {
    return [
        'contententity_user_warning_type_id' => [
            'title'       => 'User Content Entity: Warning Type ID',
            'description' => 'Set a Warning Type ID that will be assigned to automated user Warnings.',
            'optionscode' => 'numeric',
            'value'       => '',
        ],
        'contententity_user_moderation_time' => [
            'title'       => 'User Content Entity: Moderation Time (seconds)',
            'description' => 'Set time that will be assigned to automated post moderation.',
            'optionscode' => 'numeric',
            'value'       => '3600',
        ],
        'contententity_user_ban_time' => [
            'title'       => 'User Content Entity: Ban Time',
            'description' => 'Set time that will be assigned to automated bans.',
            'optionscode' => "select\n" . \adrem\modules\ContentEntityPost\getBanTimesSelectString(),
            'value'       => '1-0-0',
        ],
    ];
});

function getBanTimesSelectString(): string
{
    $values = [];

    foreach (\fetch_ban_times() as $code => $title) {
        $values[] = $code . '=' . $title;
    }

    $string = implode("\n", $values);

    return $string;
}
