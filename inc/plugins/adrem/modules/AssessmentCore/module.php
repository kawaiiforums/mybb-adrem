<?php

global $mybb;

require __DIR__ . '/hooks.php';

\adrem\registerSettings([
    'assessment_core_trigger_words' => [
        'title'       => 'Core Assessment: Trigger Words',
        'description' => 'A list of trigger words (one per line) that increment the <code>triggerWordsCount</code> attribute value. Saved in natural sort order.',
        'optionscode' => 'textarea',
        'value'       => '',
    ],
    'assessment_core_link_exception_hosts' => [
        'title'       => 'Core Assessment: Link Count Hostname Exceptions',
        'description' => 'Domains (one per line) that don\'t increment the <code>mycodeLinkCount</code> nor <code>mycodeNameLinkCount</code> attribute values. Saved in natural sort order.',
        'optionscode' => 'textarea',
        'value'       => parse_url($mybb->settings['bburl'], PHP_URL_HOST),
    ],
]);

\adrem\addHooksNamespace('adrem\modules\AssessmentCore\Hooks');
