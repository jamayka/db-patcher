#!/usr/bin/env php
<?php

$baseDir = dirname(__DIR__);

foreach (array($baseDir . '/../../autoload.php', $baseDir . '/vendor/autoload.php') as $file) {
    if (file_exists($file) && is_readable($file)) {
        define('DBPATCHER_COMPOSER_INSTALL', $file);
        break;
    }
}

if (!defined('DBPATCHER_COMPOSER_INSTALL')) {
    die('Please set up the DBPatcher using composer' . PHP_EOL);
}

require DBPATCHER_COMPOSER_INSTALL;

// --------------------------------------------------------------------------------------------------------------------

$output = new \FusePump\Cli\Logger();
$inputs = \DBPatcher\Cli\getConfiguredOptions(new \FusePump\Cli\Inputs($argv));

if (!$inputs->parse()) {
    exit(1);
}

// --------------------------------------------------------------------------------------------------------------------

$config = \DBPatcher\Cli\getConfig($baseDir, $inputs->get('-c'));
if ($config === null) {
    $output->error('Wrong config file!');
    exit(2);
}

// --------------------------------------------------------------------------------------------------------------------

if (!array_key_exists('db', $config) || !is_array($config['db'])) {
    $output->error('Wrong DB config!');
    exit(3);
}

try {
    $dbConnection = \DBPatcher\Storage\getDbConnection(
        \Doctrine\DBAL\DriverManager::getConnection(
            array_merge($config['db'], array('driver' => 'pdo_pgsql'))
        )
    );
} catch (\Doctrine\DBAL\DBALException $e) {
    $output->error('Cannot connect to DB!');
    exit(4);
}

// --------------------------------------------------------------------------------------------------------------------

$strategy = \DBPatcher\Strategy\strategyFactory(
    '\DBPatcher\Strategy\regularStrategy',
    array(
        '-n' => '\DBPatcher\Strategy\regularStrategy',
        '-f' => '\DBPatcher\Strategy\forceAllStrategy',
        '-i' => '\DBPatcher\Strategy\interactiveStrategy'
    ),
    $inputs,
    array('inputs' => $inputs->get('-l') ? new \DBPatcher\InputPreview($argv) : $inputs)
);

// --------------------------------------------------------------------------------------------------------------------

$printPatch = function ($patchFile) use ($output, $strategy) {
    try {
        $actionLabel = $strategy($patchFile) ? 'install' : 'skip';
    } catch (\DBPatcher\InputPreview\Exception $e) {
        $actionLabel = 'interactive';
    }

    $output->out(\DBPatcher\patchText($patchFile) . " - $actionLabel");
};

$runPatch = function ($patchFile) use ($inputs, $output, $dbConnection, $strategy) {
    $output->out("==================================");
    $output->out(\DBPatcher\patchText($patchFile));

    if (!$strategy($patchFile)) {
        $output->out('Skipping');
        return true;
    }

    list($patchFile, $errorMsg) = array_merge(
        \DBPatcher\Apply\applyPatch($patchFile, $dbConnection, new \Ymmtmsys\Command\Command()),
        array(null)
    );

    \DBPatcher\Storage\savePatchFile($dbConnection, $patchFile);

    if ($patchFile->status === \DBPatcher\PatchFile::STATUS_ERROR) {
        $output->out("Error!", 'bold_red');
        $output->out($errorMsg);

        return false;
    }

    $output->out("Success", 'bold_green');
    return true;
};

// --------------------------------------------------------------------------------------------------------------------

if ($inputs->get('-d')) {
    $patchesDir = $inputs->get('-d');
} elseif (array_key_exists('directory', $config)) {
    $patchesDir = $config['directory'];
} else {
    $output->error('Patches directory should be specified in config or in command option!');
    exit(5);
}

if (!is_dir($patchesDir) || !is_readable($patchesDir)) {
    $output->error('Patches directory does not exist or is not readable!');
    exit(6);
}

// --------------------------------------------------------------------------------------------------------------------

if ($inputs->get('-p')) {
    $patchFiles = array(\DBPatcher\PatchFile::createFromFS($inputs->get('-p'), $patchesDir));
} else {
    $patchFiles = \DBPatcher\getPatchFiles(
        \DBPatcher\getPatchNamesList($patchesDir),
        $patchesDir,
        array('\DBPatcher\PatchFile', 'createFromFS')
    );
}

$patchFiles = \DBPatcher\getPatchesWithStatuses(
    $patchFiles,
    DBPatcher\Storage\getRowsFromDbForPatchFiles($patchFiles, $dbConnection),
    '\DBPatcher\getPatchWithUpdatedStatus'
);

foreach ($patchFiles as $patchFile) {
    if ($inputs->get('-l')) {
        $printPatch($patchFile);
    } else {
        if (!$runPatch($patchFile) && $inputs->get('-s')) {
            break;
        }
    }
}
