<?php

namespace DBPatcher\Strategy;

use DBPatcher;

/**
 * @param DBPatcher\PatchFile $patchFile
 * @return bool
 */
function regularStrategy($patchFile)
{
    switch ($patchFile->status) {
        case DBPatcher\PatchFile::STATUS_NEW:
        case DBPatcher\PatchFile::STATUS_CHANGED:
            return true;
        case DBPatcher\PatchFile::STATUS_INSTALLED:
        case DBPatcher\PatchFile::STATUS_ERROR:
            return false;
    }

    return false;
}

/**
 * @param DBPatcher\PatchFile $patchFile
 * @return bool
 */
function strictStrategy($patchFile)
{
    switch ($patchFile->status) {
        case DBPatcher\PatchFile::STATUS_NEW:
            return true;
        case DBPatcher\PatchFile::STATUS_CHANGED:
        case DBPatcher\PatchFile::STATUS_INSTALLED:
        case DBPatcher\PatchFile::STATUS_ERROR:
            return false;
    }

    return false;
}

/**
 * @param DBPatcher\PatchFile $patchFile
 * @return bool
 */
function forceAllStrategy($patchFile)
{
    switch ($patchFile->status) {
        case DBPatcher\PatchFile::STATUS_NEW:
        case DBPatcher\PatchFile::STATUS_CHANGED:
        case DBPatcher\PatchFile::STATUS_INSTALLED:
        case DBPatcher\PatchFile::STATUS_ERROR:
            return true;
    }

    return false;
}

/**
 * @param DBPatcher\PatchFile $patchFile
 * @param callable $superStrategy
 * @param \FusePump\Cli\Inputs $inputs
 * @return boolean
 */
function interactiveStrategy($patchFile, $superStrategy, $inputs)
{
    if (!call_user_func($superStrategy, $patchFile)) {
        switch ($patchFile->status) {
            case DBPatcher\PatchFile::STATUS_INSTALLED:
                $statusText = '[installed]';
                break;
            case DBPatcher\PatchFile::STATUS_CHANGED:
                $statusText = '[installed but changed after installation]';
                break;
            case DBPatcher\PatchFile::STATUS_ERROR:
                $statusText = '[installed with errors]';
                break;
            default:
                $statusText = '[new]';
        }

        return $inputs->confirm("Apply patch file {$patchFile->name} $statusText?");
    }

    return true;
}

/**
 * @param callable $defaultStrategy
 * @param array $strategyMap
 * @param \FusePump\Cli\Inputs $inputs
 * @param array $arguments
 * @return callable
 */
function strategyFactory($defaultStrategy, $strategyMap = array(), $inputs = null, $arguments = [])
{
    $strategyList = [];

    $addStrategy = function ($strategy) use (&$strategyList, $arguments) {
        $args = [];

        $reflection = new \ReflectionFunction($strategy);
        foreach ($reflection->getParameters() as $param) {
            if ($param->getName() === 'patchFile') {
                continue;
            } elseif ($param->getName() === 'superStrategy') {
                $args[] = array_pop($strategyList);
            } elseif (array_key_exists($param->getName(), $arguments)) {
                $args[] = $arguments[$param->getName()];
            } else {
                $args[] = null;
            }
        }

        $strategyList[] = function ($patchFile) use ($strategy, $args) {
            call_user_func_array($strategy, array_merge([$patchFile], $args));
        };
    };

    $addStrategy($defaultStrategy);

    foreach ($strategyMap as $option => $strategy) {
        if ($inputs->get($option)) {
            $addStrategy($strategy);
        }
    }

    return array_pop($strategyList);
}
