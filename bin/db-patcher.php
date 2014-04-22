#!/usr/bin/env php
<?php

// TODO fat controller needs refactoring!

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

$config = \DBPatcher\Cli\getConfig($baseDir, \DBPatcher\Cli\getConfigOption($inputs));
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

$makeStrategy = function ($inputsInstance) use ($inputs) {
    return \DBPatcher\Strategy\strategyFactory(
        array('\DBPatcher\Strategy\newStrategy', '\DBPatcher\Strategy\changedStrategy'),
        \DBPatcher\Cli\optionsStrategiesMap(),
        $inputs,
        array('inputs' => $inputsInstance)
    );
};

$previewStrategy = $makeStrategy(new \DBPatcher\InputPreview($inputs));
$applyStrategy = $makeStrategy($inputs);

// --------------------------------------------------------------------------------------------------------------------

$printPatch = function ($patchFile) use ($output, $previewStrategy) {
    try {
        $actionLabel = $previewStrategy($patchFile) ? 'install' : 'skip';
    } catch (\DBPatcher\InputPreview\Exception $e) {
        $actionLabel = 'interactive';
    }

    $output->out(\DBPatcher\patchText($patchFile) . " - $actionLabel");

    return $actionLabel !== 'skip';
};

$runPatch = function ($patchFile) use ($inputs, $output, $dbConnection, $applyStrategy) {
    $output->out("==================================");
    $output->out(\DBPatcher\patchText($patchFile));

    if (!$applyStrategy($patchFile)) {
        $output->out('Skipping');
        return true;
    }

    if (\DBPatcher\Cli\getMarkPatchesOption($inputs)) {
        $patchFile = \DBPatcher\PatchFile::copyWithNewStatus($patchFile, \DBPatcher\PatchFile::STATUS_INSTALLED);
    } else {
        list($patchFile, $errorMsg) = array_merge(
            \DBPatcher\Apply\applyPatch($patchFile, $dbConnection, new \Symfony\Component\Process\Process(''), STDOUT, STDERR),
            array(null)
        );

        if ($patchFile->status === \DBPatcher\PatchFile::STATUS_ERROR) {
            $output->out("Error!", 'bold_red');
            $output->out($errorMsg);
        }
    }

    \DBPatcher\Storage\savePatchFile($dbConnection, $patchFile);

    if ($patchFile->status === \DBPatcher\PatchFile::STATUS_ERROR) {
        return false;
    }

    $output->out("Success", 'bold_green');
    return true;
};

// --------------------------------------------------------------------------------------------------------------------

if (array_key_exists('directory', $config)) {
    $patchesDir = $config['directory'];
} else {
    $output->error('Patches directory should be specified in config!');
    exit(5);
}

if (!is_dir($patchesDir) || !is_readable($patchesDir)) {
    $output->error('Patches directory does not exist or is not readable!');
    exit(6);
}

// --------------------------------------------------------------------------------------------------------------------

if (\DBPatcher\Cli\getPatchFileToApplyOption($inputs)) {
    $patchFiles = array(
        \DBPatcher\PatchFile::createFromFS(\DBPatcher\Cli\getPatchFileToApplyOption($inputs), $patchesDir)
    );
} else {
    $patchFiles = \DBPatcher\getPatchFiles(
        \DBPatcher\getPatchNamesList($patchesDir),
        $patchesDir,
        array('\DBPatcher\PatchFile', 'createFromFS')
    );
}

if (($pattern = \DBPatcher\Cli\getPatchFilePatternOption($inputs))) {
    $patchFiles = array_filter($patchFiles, function ($patchFile) use ($pattern) {
            return fnmatch($pattern, $patchFile->name, FNM_CASEFOLD | FNM_PATHNAME);
        });
}

if (empty($patchFiles)) {
    $output->out('No patches to apply.');
    exit;
}

$patchFiles = \DBPatcher\getPatchesWithStatuses(
    $patchFiles,
    DBPatcher\Storage\getRowsFromDbForPatchFiles($patchFiles, $dbConnection),
    '\DBPatcher\getPatchWithUpdatedStatus'
);

if (\DBPatcher\Cli\getListOnlyOption($inputs)) {
    $output->out(count(array_filter(array_map($printPatch, $patchFiles))) . ' patch(es) to apply');
    exit;
}

if (!\DBPatcher\Cli\getPatchFileToApplyOption($inputs)) {
    $output->out('Following patches will be ' . (\DBPatcher\Cli\getMarkPatchesOption($inputs) ? 'marked' : 'applied'));
    $amountOfPatchesToInstall = count(array_filter(array_map($printPatch, $patchFiles)));

    if ($amountOfPatchesToInstall === 0) {
        $output->out('No patches to ' . (\DBPatcher\Cli\getMarkPatchesOption($inputs) ? 'mark' : 'apply'));
        exit;
    }

    if (
        !$inputs->confirm(
            (\DBPatcher\Cli\getMarkPatchesOption($inputs) ? 'Mark' : 'Apply') . " $amountOfPatchesToInstall patch(es)?"
        )
    ) {
        exit;
    }
}

foreach ($patchFiles as $patchFile) {
    if (!$runPatch($patchFile) && \DBPatcher\Cli\getStopOnErrorOption($inputs)) {
        break;
    }
}
