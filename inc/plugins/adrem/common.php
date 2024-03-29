<?php

namespace adrem;

// hooks
function addHooks(array $hooks, string $namespace = null): void
{
    global $plugins;

    if ($namespace) {
        $prefix = $namespace . '\\';
    } else {
        $prefix = null;
    }

    foreach ($hooks as $hook) {
        $plugins->add_hook($hook, $prefix . $hook);
    }
}

function addHooksNamespace(string $namespace): void
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;
        if (substr($callable, 0, $namespaceWithPrefixLength) == $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, null, 0, $namespaceWithPrefixLength);

            $priority = substr($callable, -2);

            if (is_numeric(substr($hookName, -2))) {
                $hookName = substr($hookName, 0, -2);
            } else {
                $priority = 10;
            }

            $plugins->add_hook($hookName, $callable, $priority);
        }
    }
}

// reflection
function getClassMethodsNamesMatching($class, string $pattern): array
{
    $methodNames = [];

    $methods = get_class_methods($class);

    foreach ($methods as $method) {
        if (preg_match($pattern, $method, $matches)) {
            $methodNames[] = $matches[1];
        }
    }

    return $methodNames;
}

// settings
function getSettingValue(string $name): string
{
    global $mybb;
    return $mybb->settings['adrem_' . $name];
}

function getCsvSettingValues(string $name): array
{
    static $values;

    if (!isset($values[$name])) {
        $values[$name] = array_filter(explode(',', getSettingValue($name)));
    }

    return $values[$name];
}

function getDelimitedSettingValues(string $name): array
{
    static $values;

    if (!isset($values[$name])) {
        $values[$name] = array_filter(preg_split("/\\r\\n|\\r|\\n/", getSettingValue($name)));
    }

    return $values[$name];
}

// themes
function loadTemplates(array $templates, string $prefix = null): void
{
    global $templatelist;

    if (!empty($templatelist)) {
        $templatelist .= ',';
    }
    if ($prefix) {
        $templates = preg_filter('/^/', $prefix, $templates);
    }

    $templatelist .= implode(',', $templates);
}

function tpl(string $name): string
{
    global $templates;

    $templateName = 'adrem_' . $name;
    $directory = MYBB_ROOT . 'inc/plugins/adrem/templates/';

    if (DEVELOPMENT_MODE) {
        $templateContent = str_replace(
            "\\'",
            "'",
            addslashes(
                file_get_contents($directory . $name . '.tpl')
            )
        );

        if (!isset($templates->cache[$templateName]) && !isset($templates->uncached_templates[$templateName])) {
            $templates->uncached_templates[$templateName] = $templateName;
        }

        return $templateContent;
    } else {
        return $templates->get($templateName);
    }
}

function replaceInTemplate(string $title, string $find, string $replace): bool
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    return \find_replace_templatesets($title, '#' . preg_quote($find, '#') . '#', $replace);
}

// datacache
function getCacheValue(string $key)
{
    global $cache;

    return $cache->read('adrem')[$key] ?? null;
}

function updateCache(array $values, bool $overwrite = false): void
{
    global $cache;

    if ($overwrite) {
        $cacheContent = $values;
    } else {
        $cacheContent = $cache->read('adrem');
        $cacheContent = array_merge($cacheContent, $values);
    }

    $cache->update('adrem', $cacheContent);
}

// filesystem
function getFilesContentInDirectory(string $path, string $fileNameSuffix): array
{
    $contents = [];

    $directory = new \DirectoryIterator($path);

    foreach ($directory as $file) {
        if (!$file->isDot() && !$file->isDir()) {
            $templateName = $file->getPathname();
            $templateName = basename($templateName, $fileNameSuffix);
            $contents[$templateName] = file_get_contents($file->getPathname());
        }
    }

    return $contents;
}

// database
function dropTables(array $tableNames, bool $onlyIfExists = false, bool $cascade = false): void
{
    global $db;

    if ($cascade) {
        if (in_array($db->type, ['mysqli', 'mysql'])) {
            $db->write_query('SET foreign_key_checks = 0');
        } elseif ($db->type == 'sqlite') {
            $db->write_query('PRAGMA foreign_keys = OFF');
        }
    }

    foreach ($tableNames as $tableName) {
        if (!$onlyIfExists || $db->table_exists($tableName)) {
            if ($db->type == 'pgsql' && $cascade) {
                $db->write_query("DROP TABLE " . TABLE_PREFIX . $tableName . " CASCADE");
            } else {
                $db->drop_table($tableName, true);
            }
        }
    }

    if ($cascade) {
        if (in_array($db->type, ['mysqli', 'mysql'])) {
            $db->write_query('SET foreign_key_checks = 1');
        } elseif ($db->type == 'sqlite') {
            $db->write_query('PRAGMA foreign_keys = ON');
        }
    }
}

// data
function getArraySubset(array $array, array $keys): array
{
    return array_intersect_key($array, array_flip($keys));
}

// 3rd party
function loadPluginLibrary(): void
{
    global $lang, $PL;

    $lang->load('adrem');

    if (!defined('PLUGINLIBRARY')) {
        define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');
    }

    if (!file_exists(PLUGINLIBRARY)) {
        flash_message($lang->adrem_admin_pluginlibrary_missing, 'error');

        admin_redirect('index.php?module=config-plugins');
    } elseif (!$PL) {
        require_once PLUGINLIBRARY;
    }
}
