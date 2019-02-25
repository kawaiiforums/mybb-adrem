<?php

namespace adrem\Hooks;

function admin_load(): void
{
    global $mybb, $run_module, $action_file;

    if ($run_module == 'config' && $action_file == 'adrem_ruleset_validation') {
        if ($value = $mybb->get_input('value')) {
            $results = \adrem\Ruleset::getValidationResults($value);

            header('Content-type: application/json');

            echo json_encode($results);

            exit;
        }
    }
}

function admin_config_action_handler(array &$actions): void
{
    $actions['adrem_ruleset_validation'] = [
        'active' => 'adrem_ruleset_validation',
        'file' => 'adrem_ruleset_validation',
    ];
}

function admin_config_settings_change(): void
{
    global $page, $lang;

    $lang->load('adrem');

    $page->extra_header .= <<<HTML
<link href="./jscripts/codemirror/lib/codemirror.css" rel="stylesheet">
<link href="./jscripts/codemirror/theme/mybb.css" rel="stylesheet">
<link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css" rel="stylesheet">
<script src="./jscripts/codemirror/lib/codemirror.js"></script>
<script src="./jscripts/codemirror/mode/javascript/javascript.js"></script>

<script>
lang.adrem_validation_errors = "{$lang->adrem_validation_errors}"; 
lang.adrem_validation_warnings = "{$lang->adrem_validation_warnings}";
</script>
<script src="./jscripts/adrem_ruleset_validation.js"></script>
<style>
#adrem_validation_results { padding: 10px; background-color: rgba(255,100,0,0.05); }
#adrem_validation_results:empty { display: none; }
</style>
HTML;
}

function admin_config_settings_change_commit(): void
{
    global $mybb;

    $value = &$mybb->input['upsetting']['adrem_ruleset'];

    if (isset($value)) {
        $validationResults = \adrem\Ruleset::getValidationResults($value);

        if ($validationResults['errors'] === []) {
            \adrem\updateCache([
                'ruleset' => \adrem\Ruleset::getParsedRuleset($value),
            ]);
        }
    }
}
