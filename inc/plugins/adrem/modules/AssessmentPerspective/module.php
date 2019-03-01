<?php

require __DIR__ . '/hooks.php';

\adrem\registerSettings([
    'perspective_api_key' => [
        'title'       => 'Perspective Assessment: API Key',
        'description' => 'An API key for the <i>Perspective</i> assessment.',
        'optionscode' => 'text',
        'value'       => '',
    ],
    'perspective_do_not_store' => [
        'title'       => 'Perspective Assessment: Do Not Store',
        'description' => 'Choose whether to request submitted data is not stored remotely.',
        'optionscode' => 'yesno',
        'value'       => '1',
    ],
]);

\adrem\addHooksNamespace('adrem\modules\AssessmentPerspective\Hooks');
