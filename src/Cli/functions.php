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
    $inputs->option('-n, --new', 'Install automatically only new patches');
    $inputs->option('-f, --force', 'Install all patches (installed, errors, changed, new)');
    $inputs->option('-i, --interactive', 'Interactive mode');
    $inputs->option('-s, --stop-on-error', 'Stop patches on error');
    $inputs->option('-c, --config [filename]', 'Config json filename');
    $inputs->option('-d, --dir [path]', 'Patches directory path');
    $inputs->option('-p, --patch [name]', 'Patch name to run (relative to patches directory)');

    return $inputs;
}

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
        $fileInfo = new \SplFileInfo($filename);
        if ($fileInfo->isFile() && $fileInfo->isReadable()) {
            $config = false;
            if ($fileInfo->getExtension() === 'json') {
                $config = json_decode(file_get_contents($filename), true);
            } elseif ($fileInfo->getExtension() === 'php') {
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
