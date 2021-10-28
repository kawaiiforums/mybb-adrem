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
        $className = str_replace('\\', '/', substr($path, strlen($prefix)));
        $file = $baseDir . $className . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
});

\adrem\loadModules(\adrem\getModuleNames());

// init
define('adrem\DEVELOPMENT_MODE', 0);

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
        'version'       => '1.1.1',
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
                    suggested_attribute_values text,
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
                    suggested_attribute_values text,
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
                    `suggested_attribute_values` text,
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
        'modules' => [],
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

    $moduleNames = \adrem\getModuleNames(false);

    \adrem\loadModules($moduleNames);

    // settings
    $settings = [
        'ruleset' => [
            'title'       => 'Ruleset',
            'description' => 'Edit the JSON Ruleset to customize rules and actions.',
            'optionscode' => 'textarea',
            'value'       => '{
    "post": [
        {
            "rules": [
                {"any": [
                    ["core:wordfilterCount", ">=", "3"]
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
            'value'       => '-1',
        ],
        'action_user' => [
            'title'       => 'Action User',
            'description' => 'Choose a user ID that will be assigned to moderator actions.',
            'optionscode' => 'numeric',
            'value'       => '0',
        ],
        'unlogged_assessment_names' => [
            'title'       => 'Unlogged Assessments',
            'description' => 'Comma-separated names of Assessments which results will not be logged.',
            'optionscode' => 'text',
            'value'       => 'user',
        ],
    ];

    $settings = array_merge($settings, \adrem\getRegisteredSettings());

    $PL->settings(
        'adrem',
        'Ad Rem',
        'Settings for the Ad Rem extension.',
        $settings
    );

    // templates
    $PL->templates(
        'adrem',
        'Ad Rem',
        \adrem\getFilesContentInDirectory(MYBB_ROOT . 'inc/plugins/adrem/templates', '.tpl')
    );

    // stylesheets
    $stylesheets = [
        'adrem' => [
            'attached_to' => [],
        ],
    ];

    foreach ($stylesheets as $stylesheetName => $stylesheet) {
        $PL->stylesheet(
            $stylesheetName,
            file_get_contents(MYBB_ROOT . 'inc/plugins/adrem/stylesheets/' . $stylesheetName . '.css'),
            $stylesheet['attached_to']
        );
    }

    // datacache
    $value = \adrem\getSettingValue('ruleset');

    $validationResults = \adrem\Ruleset::getValidationResults($value);

    if ($validationResults['errors'] === []) {
        \adrem\updateCache([
            'ruleset' => \adrem\Ruleset::getParsedRuleset($value),
            'modules' => $moduleNames,
        ]);
    }
}

function adrem_deactivate()
{
    global $PL;

    \adrem\loadPluginLibrary();

    \adrem\loadModules(\adrem\getModuleNames());

    // templates
    $PL->templates_delete('adrem', true);

    // stylesheets
    $PL->stylesheet_delete('adrem', true);
}
