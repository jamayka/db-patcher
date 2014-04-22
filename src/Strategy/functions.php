<?php

namespace DBPatcher\Strategy;

use DBPatcher;

/**
 * @param DBPatcher\PatchFile $patchFile
 * @param callable $superStrategy
 * @return bool
 */
function newStrategy($patchFile, $superStrategy)
{
    if (!call_user_func($superStrategy, $patchFile)) {
        return $patchFile->status === DBPatcher\PatchFile::STATUS_NEW;
    }

    return true;
}

/**
 * @param DBPatcher\PatchFile $patchFile
 * @param callable $superStrategy
 * @return bool
 */
function changedStrategy($patchFile, $superStrategy)
{
    if (!call_user_func($superStrategy, $patchFile)) {
        return $patchFile->status === DBPatcher\PatchFile::STATUS_CHANGED;
    }

    return true;
}

/**
 * @param DBPatcher\PatchFile $patchFile
 * @param callable $superStrategy
 * @return bool
 */
function errorStrategy($patchFile, $superStrategy)
{
    if (!call_user_func($superStrategy, $patchFile)) {
        return $patchFile->status === DBPatcher\PatchFile::STATUS_ERROR;
    }

    return true;
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
        return $inputs->confirm("Apply patch file {$patchFile->name}?");
    }

    return true;
}

/**
 * @param array $defaultStrategies
 * @param array $strategyMap
 * @param \FusePump\Cli\Inputs $inputs
 * @param array $arguments
 * @return callable
 */
function strategyFactory($defaultStrategies, $strategyMap = array(), $inputs = null, $arguments = array())
{
    $strategyList = array(function () { return false; });

    $addStrategy = function ($strategy) use (&$strategyList, $arguments) {
        $args = array();

        $reflection = new \ReflectionFunction($strategy);
        foreach ($reflection->getParameters() as $param) {
            if ($param->getName() === 'patchFile') {
                continue;
            } elseif ($param->getName() === 'superStrategy') {
                $args[] = end($strategyList);
            } elseif (array_key_exists($param->getName(), $arguments)) {
                $args[] = $arguments[$param->getName()];
            } else {
                $args[] = null;
            }
        }

        $strategyList[] = function ($patchFile) use ($strategy, $args) {
            return call_user_func_array($strategy, array_merge(array($patchFile), $args));
        };
    };

    foreach ($strategyMap as $option => $strategy) {
        if ($inputs->get($option)) {
            $addStrategy($strategy);
        }
    }

    if (count($strategyList) < 2) {
        foreach ($defaultStrategies as $option) {
            if (array_key_exists($option, $strategyMap)) {
                $addStrategy($strategyMap[$option]);
            }
        }
    }

    return end($strategyList);
}
