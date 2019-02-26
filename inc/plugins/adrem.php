<?php

// core files
require MYBB_ROOT . 'inc/plugins/adrem/Assessment.php';
require MYBB_ROOT . 'inc/plugins/adrem/ContentEntity.php';
require MYBB_ROOT . 'inc/plugins/adrem/Inspection.php';
require MYBB_ROOT . 'inc/plugins/adrem/Ruleset.php';
require MYBB_ROOT . 'inc/plugins/adrem/RecursiveRuleArrayIterator.php';

require MYBB_ROOT . 'inc/plugins/adrem/common.php';
require MYBB_ROOT . 'inc/plugins/adrem/data.php';
require MYBB_ROOT . 'inc/plugins/adrem/core.php';

// hook files
require MYBB_ROOT . 'inc/plugins/adrem/hooks_frontend.php';
require MYBB_ROOT . 'inc/plugins/adrem/hooks_acp.php';

// autoloading
spl_autoload_register(function ($path) {
    $prefix = 'adrem\\';
    $baseDir = MYBB_ROOT . 'inc/plugins/adrem/';

    if (strpos($path, $prefix) === 0) {
        $className = substr($path, strlen($prefix));
        $file = $baseDir . $className . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
});

// init
define('adrem\DEVELOPMENT_MODE', 1);

// hooks
\adrem\addHooksNamespace('adrem\Hooks');

function adrem_info()
{
    global $lang;

    $lang->load('adrem');

    return [
        'name'          => 'Ad Rem',
        'description'   => $lang->adrem_description,
        'website'       => '',
        'author'        => 'Tomasz \'Devilshakerz\' Mlynski',
        'authorsite'    => 'https://devilshakerz.com/',
        'version'       => '1.0',
        'codename'      => 'adrem',
        'compatibility' => '18*',
    ];
}

function adrem_install()
{
    global $db, $cache;

    \adrem\loadPluginLibrary();

    // database
    switch ($db->type) {
        case 'pgsql':
            $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "adrem_inspections (
                    id serial,
                    content_type text NOT NULL,
                    content_entity_id integer NOT NULL,
                    content_entity_data text NOT NULL,
                    completed integer NOT NULL,
                    date_requested integer NOT NULL,
                    date_completed integer,
                    actions text,
                    PRIMARY KEY (id)
                )
            ");
            $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "adrem_assessments (
                    id serial,
                    inspection_id integer NOT NULL
                        REFERENCES " . TABLE_PREFIX . "adrem_assessments(id) ON DELETE CASCADE,
                    name text NOT NULL,
                    version text NOT NULL,
                    date_completed integer NOT NULL,
                    duration decimal(10,4) NOT NULL,
                    result_data text,
                    attribute_values text NOT NULL,
                    PRIMARY KEY (id)
                )
            ");

            break;
        case 'sqlite':
            $db->write_query("
                CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "adrem_inspections` (
                    id serial,
                    content_type text NOT NULL,
                    content_entity_id int NOT NULL,
                    content_entity_data text NOT NULL,
                    completed integer NOT NULL,
                    date_requested integer NOT NULL,
                    date_completed integer,
                    actions text,
                    PRIMARY KEY (id)
                )
            ");
            $db->write_query("
                CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "adrem_assessments` (
                    id integer,
                    inspection_id integer NOT NULL
                        REFERENCES " . TABLE_PREFIX . "adrem_assessments(id) ON DELETE CASCADE,
                    name text NOT NULL,
                    version text NOT NULL,
                    date_completed integer NOT NULL,
                    duration decimal(10,4) NOT NULL,
                    result_data text,
                    attribute_values text NOT NULL,
                    PRIMARY KEY (id)
                )
            ");

            break;
        default:
            $db->write_query("
                CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "adrem_inspections` (
                    `id` integer NOT NULL auto_increment,
                    `content_type` varchar(100) NOT NULL,
                    `content_entity_id` integer NOT NULL,
                    `content_entity_data` text NOT NULL,
                    `completed` integer(1) NOT NULL,
                    `date_requested` integer NOT NULL,
                    `date_completed` integer,
                    `actions` text,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB " . $db->build_create_table_collation() . "
            ");
            $db->write_query("
                CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "adrem_assessments` (
                    `id` integer NOT NULL auto_increment,
                    `inspection_id` integer NOT NULL,
                    `name` varchar(100) NOT NULL,
                    `version` varchar(100) NOT NULL,
                    `date_completed` integer NOT NULL,
                    `duration` decimal(10,4) NOT NULL,
                    `result_data` text,
                    `attribute_values` text NOT NULL,
                    PRIMARY KEY (`id`),
                    FOREIGN KEY (`inspection_id`)
                        REFERENCES " . TABLE_PREFIX . "adrem_inspections (`id`)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB " . $db->build_create_table_collation() . "
            ");

            break;
    }

    // datacache
    $cache->update('adrem', [
        'ruleset' => [],
    ]);
}

function adrem_uninstall()
{
    global $db, $cache, $PL;

    \adrem\loadPluginLibrary();

    // database
    if ($db->type == 'sqlite') {
        $db->close_cursors();
    }

    \adrem\dropTables([
        'adrem_inspections',
        'adrem_assessments',
    ], true, true);

    // settings
    $PL->settings_delete('adrem', true);

    // datacache
    $cache->delete('adrem');
}

function adrem_is_installed()
{
    global $db;

    // manual check to avoid caching issues
    $query = $db->simple_select('settinggroups', 'gid', "name='adrem'");

    return (bool)$db->num_rows($query);
}

function adrem_activate()
{
    global $PL;

    \adrem\loadPluginLibrary();

    // settings
    $PL->settings(
        'adrem',
        'Ad Rem',
        'Settings for the Ad Rem extension.',
        [
            'ruleset' => [
                'title'       => 'Ruleset',
                'description' => 'Edit the JSON Ruleset to customize rules and actions.',
                'optionscode' => 'textarea',
                'value'       => '{
    "post": [
        {
            "rules": [
                {"any": [
                    ["core:wordfilterMatches", ">=", "3"]
                ]}
            ],
            "actions": ["report"]
        }
    ]
}',
            ],
            'monitored_groups' => [
                'title'       => 'Monitored User Groups',
                'description' => 'Select which user groups\' content should be assessed.',
                'optionscode' => 'groupselect',
                'value'       => '2',
            ],
            'monitored_forums' => [
                'title'       => 'Monitored Forums',
                'description' => 'Select which forums\' content should be assessed.',
                'optionscode' => 'forumselect',
                'value'       => '-1',
            ],
            'perspective_api_key' => [
                'title'       => 'Perspective Assessment: API Key',
                'description' => 'An API key for the <i>Perspective</i> assessment.',
                'optionscode' => 'text',
                'value'       => '',
            ],
        ]
    );

    // templates
    $PL->templates(
        'adrem',
        'Ad Rem',
        \adrem\getFilesContentInDirectory(MYBB_ROOT . 'inc/plugins/adrem/templates', '.tpl')
    );

    \itscomplicated\replaceInTemplate(
        'postbit',
        '{$post[\'posturl\']}',
        '{$post[\'posturl\']}{$post[\'inspection_status\']}'
    );
    \itscomplicated\replaceInTemplate(
        'postbit_classic',
        '{$post[\'posturl\']}',
        '{$post[\'posturl\']}{$post[\'inspection_status\']}'
    );
}

function adrem_deactivate()
{
    global $PL;

    \adrem\loadPluginLibrary();

    // templates
    $PL->templates_delete('adrem', true);

    \itscomplicated\replaceInTemplate('postbit', '{$post[\'inspection_status\']}', '');
    \itscomplicated\replaceInTemplate('postbit_classic', '{$post[\'inspection_status\']}', '');
}
