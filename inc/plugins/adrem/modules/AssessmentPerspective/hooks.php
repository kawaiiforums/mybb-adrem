<?php

namespace adrem\modules\AssessmentPerspective\Hooks;

function admin_config_plugins_activate_commit(): void
{
    global $codename;

    if ($codename == 'adrem') {
        \adrem\replaceInTemplate(
            'postbit',
            '{$post[\'posturl\']}',
            '{$post[\'posturl\']}{$post[\'inspection_status\']}'
        );
        \adrem\replaceInTemplate(
            'postbit_classic',
            '{$post[\'posturl\']}',
            '{$post[\'posturl\']}{$post[\'inspection_status\']}'
        );
    }
}

function admin_config_plugins_deactivate_commit(): void
{
    global $codename;

    if ($codename == 'adrem') {
        \adrem\replaceInTemplate('postbit', '{$post[\'inspection_status\']}', '');
        \adrem\replaceInTemplate('postbit_classic', '{$post[\'inspection_status\']}', '');
    }
}
