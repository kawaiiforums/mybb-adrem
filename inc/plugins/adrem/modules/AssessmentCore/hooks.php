<?php

namespace adrem\modules\AssessmentCore\Hooks;

function admin_config_settings_change(): void
{
    global $mybb;

    $settingNames = [
        'adrem_assessment_core_trigger_words',
        'adrem_assessment_core_link_exception_hosts',
    ];

    foreach ($settingNames as $settingName) {
        if (isset($mybb->input['upsetting'][$settingName])) {
            $values = array_filter(
                preg_split("/\\r\\n|\\r|\\n/", $mybb->input['upsetting'][$settingName]),
                function (string $value): bool { return $value !== ''; }
            );

            $values = array_unique($values);
            natsort($values);

            $mybb->input['upsetting'][$settingName] = implode("\n", $values);
        }
    }
}
