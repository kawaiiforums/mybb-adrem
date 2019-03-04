<?php

\adrem\registerSettings([
    'assessment_perspective_api_key' => [
        'title'       => 'Perspective Assessment: API Key',
        'description' => 'An API key for the <i>Perspective</i> assessment.',
        'optionscode' => 'text',
        'value'       => '',
    ],
    'assessment_perspective_do_not_store' => [
        'title'       => 'Perspective Assessment: Do Not Store',
        'description' => 'Choose whether to request submitted data is not stored remotely.',
        'optionscode' => 'yesno',
        'value'       => '1',
    ],
    'assessment_perspective_community_id' => [
        'title'       => 'Perspective Assessment: Community ID',
        'description' => 'Optional community identifier attached to submitted attribute value suggestions.',
        'optionscode' => 'text',
        'value'       => '',
    ],
]);
