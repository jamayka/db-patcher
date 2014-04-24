<?php

namespace DBPatcher\Apply;

use DBPatcher;
use Symfony\Component\Process\Process;

/**
 * @param DBPatcher\PatchFile $patchFile
 * @param \Doctrine\DBAL\Connection $connection
 * @param \Symfony\Component\Process\Process $process
 * @param resource $stdout
 * @param resource $stderr
 * @return array
 */
function applyPatch($patchFile, $connection, $process, $stdout, $stderr)
{
    if ($patchFile->extension === 'php') {
        return applyPhpPatch($patchFile, $process, $stdout, $stderr);
    }

    if ($patchFile->extension === 'sql') {
        return applySqlPatch($patchFile, $connection);
    }

    return array(null);
}

/**
 * @param DBPatcher\PatchFile $patchFile
 * @param \Symfony\Component\Process\Process $process
 * @param resource $stdout
 * @param resource $stderr
 * @return DBPatcher\PatchFile
 */
function applyPhpPatch($patchFile, $process, $stdout = null, $stderr = null)
{
    if ($patchFile->extension === 'php') {
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->setCommandLine('/usr/bin/env php ' . $patchFile->filename);
        $process->start(
            function ($type, $buffer) use ($stdout, $stderr) {
                $pipe = $type === Process::ERR && is_resource($stderr) ? $stderr : $stdout;
                if ($pipe) {
                    fputs($pipe, $buffer);
                }
            }
        );

        if ($process->wait() === 0) {
            return array(DBPatcher\PatchFile::copyWithNewStatus($patchFile, DBPatcher\PatchFile::STATUS_INSTALLED));
        } else {
            return array(
                DBPatcher\PatchFile::copyWithNewStatus($patchFile, DBPatcher\PatchFile::STATUS_ERROR),
                $process->getErrorOutput()
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
            array_walk($queries, function ($query) use ($connection) { $connection->executeQuery($query); });
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
