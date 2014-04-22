<?php

namespace DBPatcher\Cli;

use DBPatcher;

/**
 * @param \FusePump\Cli\Inputs $inputs
 * @return \FusePump\Cli\Inputs
 */
function getConfiguredOptions($inputs)
{
    $inputs->option('-l, --list', 'Just output list of patches');
    $inputs->option('-n, --new', 'Install new patches');
    $inputs->option('-c, --changed', 'Install changed patches');
    $inputs->option('-e, --error', 'Install error patches');
    $inputs->option('-a, --all', 'Install all patches (installed, errors, changed, new)');
    $inputs->option('-i, --interactive', 'Interactive mode');
    $inputs->option('-m, --mark-installed', 'Do not actually apply patch just mark as installed');
    $inputs->option('-s, --stop-on-error', 'Stop patches on error');
    $inputs->option('-p, --patch [name]', 'Patch name to run (relative to patches directory)');
    $inputs->option('--pattern [pattern]', 'Shell wildcard pattern for patch file name');
    $inputs->option('--config [filename]', 'Config json filename');

    return $inputs;
}

// --------------------------------------------------------------------------------------------------------------------

/**
 * @param string $baseDir
 * @param string $configPath
 * @return array|null
 */
function getConfig($baseDir, $configPath = null)
{
    $projectDir = rtrim($baseDir, '/') . '/../../../';

    $filenames = array(
        $projectDir . 'etc/db-patcher.json',
        $projectDir . 'etc/db-patcher.php',
        $projectDir . 'data/etc/db-patcher.json',
        $projectDir . 'data/etc/db-patcher.php',
    );

    if ($configPath) {
        array_unshift($filenames, $configPath);
    }

    foreach ($filenames as $filename) {
        if (is_file($filename) && is_readable($filename)) {
            $config = false;
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if ($extension === 'json') {
                $config = json_decode(file_get_contents($filename), true);
            } elseif ($extension === 'php') {
                $config = include $filename;
            }

            if ($config) {
                if (array_key_exists('directory', $config)) {
                    $config['directory'] = getAbsolutePath($config['directory'], dirname($filename));
                }

                return $config;
            }
        }
    }

    return null;
}

// --------------------------------------------------------------------------------------------------------------------

/**
 * @param string $path
 * @param string $baseDir
 * @return string
 */
function getAbsolutePath($path, $baseDir)
{
    $p = parse_url($path);
    if (is_array($p) && $p['path'] && $path === $p['path'] && $path[0] !== '/') {
        return $baseDir . '/' . $p['path'];
    }

    return $path;
}
