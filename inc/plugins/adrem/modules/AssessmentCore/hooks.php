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
        $value = &$mybb->input['upsetting'][$settingName];

        if (isset($value)) {
            $values = array_filter(
                preg_split("/\\r\\n|\\r|\\n/", $value)
            );

            $values = array_unique($values);
            natsort($values);

            $value = implode("\n", $values);
        }
    }
}
