<?php

namespace DBPatcher\Apply;

use DBPatcher;

/**
 * @param DBPatcher\PatchFile $patchFile
 * @param \Doctrine\DBAL\Connection $connection
 * @param \Ymmtmsys\Command\Command $cmd
 * @param callable $strategyCallback
 * @return array
 */
function applyPatch($patchFile, $connection, $cmd, $strategyCallback)
{
    if (!call_user_func($strategyCallback, $patchFile)) {
        return array(null);
    }

    if ($patchFile->extension === 'php') {
        return applyPhpPatch($patchFile, $cmd);
    }

    if ($patchFile->extension === 'sql') {
        return applySqlPatch($patchFile, $connection);
    }

    return array(null);
}

/**
 * @param DBPatcher\PatchFile $patchFile
 * @param \Ymmtmsys\Command\Command $cmd
 * @return DBPatcher\PatchFile
 */
function applyPhpPatch($patchFile, $cmd)
{
    if ($patchFile->extension === 'php') {
        $cmd->exec('/usr/bin/env php ' . $patchFile->filename);

        if ($cmd->return_var == 0) {
            return array(DBPatcher\PatchFile::copyWithNewStatus($patchFile, DBPatcher\PatchFile::STATUS_INSTALLED));
        } else {
            return array(
                DBPatcher\PatchFile::copyWithNewStatus($patchFile, DBPatcher\PatchFile::STATUS_ERROR),
                implode(PHP_EOL, $cmd->output)
            );
        }
    }

    return array(DBPatcher\PatchFile::copyWithNewStatus($patchFile, DBPatcher\PatchFile::STATUS_ERROR));
}

/**
 * @param DBPatcher\PatchFile $patchFile
 * @param \Doctrine\DBAL\Connection $connection
 * @return DBPatcher\PatchFile
 */
function applySqlPatch($patchFile, $connection)
{
    if ($patchFile->extension === 'sql') {
        $queries = \SqlFormatter::splitQuery(file_get_contents($patchFile->filename));
        $connection->beginTransaction();

        try {
            array_walk($queries, function ($q) use ($connection) { $connection->executeQuery($q); });
        } catch (\Doctrine\DBAL\DBALException $e) {
            $connection->rollBack();
            return array(
                DBPatcher\PatchFile::copyWithNewStatus($patchFile, DBPatcher\PatchFile::STATUS_ERROR),
                $e->getMessage()
            );
        }

        $connection->commit();

        return array(DBPatcher\PatchFile::copyWithNewStatus($patchFile, DBPatcher\PatchFile::STATUS_INSTALLED));
    }

    return array(DBPatcher\PatchFile::copyWithNewStatus($patchFile, DBPatcher\PatchFile::STATUS_ERROR));
}
