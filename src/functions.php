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
                    [$p, $rowsFromDb[$p->name]['status'], $rowsFromDb[$p->name]['md5']]
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
    $result = [];

    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseDir)) as $fileInfo) {
        $result[] = ltrim(substr($fileInfo->getPathname(), strlen($baseDir)), '/');
    }

    sort($result);
    return $result;
}
