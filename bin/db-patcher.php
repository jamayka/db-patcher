#!/usr/bin/env php
<?php

$baseDir = dirname(__DIR__);

foreach ([$baseDir . '/../../autoload.php', $baseDir . '/vendor/autoload.php'] as $file) {
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
    $dbConnection = \Doctrine\DBAL\DriverManager::getConnection(
        array_merge($config['db'], ['driver' => 'pdo_pgsql'])
    );
} catch (\Doctrine\DBAL\DBALException $e) {
    $output->error('Cannot connect to DB!');
    exit(4);
}

// --------------------------------------------------------------------------------------------------------------------

$runPatch = function ($patchFile) use ($inputs, $output, $dbConnection) {
    $output->out("- {$patchFile->name} ...");

    list($patchFile, $errorMsg) = \DBPatcher\Apply\applyPatch(
        $patchFile,
        $dbConnection,
        new \Ymmtmsys\Command\Command(),
        \DBPatcher\Strategy\strategyFactory(
            '\DBPatcher\Strategy\regularStrategy',
            [
                '-n' => '\DBPatcher\Strategy\regularStrategy',
                '-i' => '\DBPatcher\Strategy\interactiveStrategy'
            ],
            $inputs,
            ['inputs' => $inputs]
        )
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
    $patchFiles = [\DBPatcher\PatchFile::createFromFS($inputs->get('-p'), $patchesDir)];
} else {
    $patchFiles = \DBPatcher\getPatchFiles(
        \DBPatcher\getPatchNamesList($patchesDir),
        $patchesDir,
        ['\DBPatcher\PatchFile', 'createFromFS']
    );
}

$patchFiles = \DBPatcher\getPatchesWithStatuses(
    $patchFiles,
    DBPatcher\Storage\getRowsFromDbForPatchFiles($patchFiles, $dbConnection),
    '\DBPatcher\getPatchWithUpdatedStatus'
);

foreach ($patchFiles as $patchFile) {
    if (!$runPatch($patchFile) && $inputs->get('-s')) {
        break;
    }
}
