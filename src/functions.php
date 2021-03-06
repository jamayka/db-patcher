<?php

namespace DBPatcher;

/**
 * @param PatchFile[] $patchFiles
 * @param array $rowsFromDb
 * @param callable $patchWithUpdatedStatusFactory
 * @return PatchFile[]
 */
function getPatchesWithStatuses(
    $patchFiles,
    $rowsFromDb,
    $patchWithUpdatedStatusFactory
) {
    return array_map(
        function ($p) use ($rowsFromDb, $patchWithUpdatedStatusFactory) {
            if (array_key_exists($p->name, $rowsFromDb)) {
                return call_user_func_array(
                    $patchWithUpdatedStatusFactory,
                    array($p, $rowsFromDb[$p->name]['status'], $rowsFromDb[$p->name]['md5'])
                );
            }

            return $p;
        },
        $patchFiles
    );
}

/**
 * @param PatchFile $patchFile
 * @param integer $status
 * @param string $md5
 * @return PatchFile
 */
function getPatchWithUpdatedStatus($patchFile, $status, $md5)
{
    switch ($status) {
        case PatchFile::STATUS_INSTALLED:
            if ($patchFile->md5 !== $md5) {
                return PatchFile::copyWithNewStatus($patchFile, PatchFile::STATUS_CHANGED);
            }
            break;
        case PatchFile::STATUS_ERROR:
            if ($patchFile->md5 !== $md5) {
                return PatchFile::copyWithNewStatus($patchFile, PatchFile::STATUS_NEW);
            }
            break;
        default:
            return PatchFile::copyWithNewStatus($patchFile, PatchFile::STATUS_NEW);
    }

    return PatchFile::copyWithNewStatus($patchFile, $status);
}

// --------------------------------------------------------------------------------------------------------------------

/**
 * @param string[] $patchNames
 * @param string $baseDir
 * @param callable $patchFileFactory
 * @return PatchFile[]
 */
function getPatchFiles($patchNames, $baseDir, $patchFileFactory)
{
    return array_map(
        function ($patchName) use ($baseDir, $patchFileFactory) {
            return call_user_func($patchFileFactory, $patchName, $baseDir);
        },
        $patchNames
    );
}

/**
 * @param string $baseDir
 * @return string[]
 */
function getPatchNamesList($baseDir)
{
    $result = array();

    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseDir)) as $fileInfo) {
        if ($fileInfo->isFile()) {
            $patchName = ltrim(substr($fileInfo->getPathname(), strlen($baseDir)), '/');
            $nameWoExt = $fileInfo->getExtension() ?
                substr($patchName, 0, strlen($patchName) - strlen($fileInfo->getExtension()) - 1) :
                $patchName;
            $result[$nameWoExt] = $patchName;
        }
    }

    ksort($result);
    return array_values($result);
}

/**
 * @param PatchFile $patchFile
 * @return string
 */
function patchStatusText($patchFile)
{
    switch ($patchFile->status) {
        case PatchFile::STATUS_INSTALLED:
            return 'installed';
        case PatchFile::STATUS_CHANGED:
            return 'installed but changed after installation';
        case PatchFile::STATUS_ERROR:
            return 'installed with errors';
        default:
            return 'new';
    }
}

/**
 * @param PatchFile $patchFile
 * @return string
 */
function patchText($patchFile)
{
    $statusText = \DBPatcher\patchStatusText($patchFile);
    return "* {$patchFile->name} [{$statusText}]";
}
