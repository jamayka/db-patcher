<?php

namespace DBPatcher\Storage;

use DBPatcher;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;

/**
 * @return string
 */
function getDbPatcherVersion()
{
    return '0.0.2';
}

/**
 * @param Connection $connection
 * @return Connection
 */
function getDbConnection($connection)
{
    $currentVersion = getDbPatcherVersion();

    try {
        $version = $connection->fetchColumn('SELECT db_patcher_version()');
    } catch (DBALException $e) {
        createDbPatcherVersionSqlFunction($connection);

        $connection->executeQuery(<<<SQL
CREATE TABLE db_patcher
(
  id serial NOT NULL,
  "name" text NOT NULL,
  status smallint NOT NULL DEFAULT 0,
  md5 text NOT NULL,
  installed_tmstmp timestamp without time zone,
  CONSTRAINT pk_db_patch PRIMARY KEY (id ),
  CONSTRAINT ak_key_2_db_patch UNIQUE ("name")
)
SQL
        );

        $version = $currentVersion;
    }

    if (version_compare($currentVersion, $version) > 0) {
        updateDbPatcherDatabase($connection, $version);
        createDbPatcherVersionSqlFunction($connection);
    }

    return $connection;
}

/**
 * @param Connection $connection
 */
function createDbPatcherVersionSqlFunction($connection)
{
    $currentVersion = getDbPatcherVersion();

    $connection->executeQuery(<<<SQL
CREATE OR REPLACE FUNCTION db_patcher_version() RETURNS double AS $$ SELECT '$currentVersion'; $$ LANGUAGE SQL;
SQL
    );
}

/**
 * @param Connection $connection
 * @param string $fromVersion
 */
function updateDbPatcherDatabase($connection, $fromVersion)
{
    $patches = array(
        '0.0.1' => array('ALTER TABLE db_patcher DROP COLUMN modified_tmstmp')
    );

    foreach ($patches as $version => $sqls) {
        if (version_compare($version, $fromVersion) >= 0) {
            array_walk($sqls, function ($sql) use ($connection) { $connection->executeQuery($sql); });
        }
    }
}

/**
 * @param DBPatcher\PatchFile[] $patchFiles
 * @param Connection $connection
 * @return array
 */
function getRowsFromDbForPatchFiles($patchFiles, $connection)
{
    $rowsFromDb = $connection->executeQuery(
        'SELECT "name", status, md5 FROM db_patcher WHERE "name" IN (?)',
        array(array_map(function ($p) { return $p->name; }, $patchFiles)),
        array(Connection::PARAM_STR_ARRAY)
    )->fetchAll();

    if (!empty($rowsFromDb)) {
        return array_combine(array_map(function ($r) { return $r['name']; }, $rowsFromDb), $rowsFromDb);
    }

    return array();
}

/**
 * @param Connection $connection
 * @param DBPatcher\PatchFile $patchFile
 */
function savePatchFile($connection, $patchFile)
{
    $data = array('status' => $patchFile->status, 'md5' => $patchFile->md5);
    if ($connection->update('db_patcher', $data, array('name' => $patchFile->name)) === 0) {
        $connection->insert('db_patcher', array_merge($data, array('name' => $patchFile->name)));
    }
}
