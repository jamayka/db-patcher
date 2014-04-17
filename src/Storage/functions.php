<?php

namespace DBPatcher\Storage;

use DBPatcher;

/**
 * @param \Doctrine\DBAL\Connection $connection
 * @return \Doctrine\DBAL\Connection
 */
function getDbConnection($connection)
{
    try {
        $connection->executeQuery('SELECT id FROM db_patch LIMIT 1');
    } catch (\Doctrine\DBAL\DBALException $e) {
        $connection->executeQuery(<<<SQL
CREATE TABLE db_patch
(
  id serial NOT NULL,
  "name" text NOT NULL,
  status smallint NOT NULL DEFAULT 0,
  modified_tmstmp timestamp without time zone,
  installed_tmstmp timestamp without time zone,
  CONSTRAINT pk_db_patch PRIMARY KEY (id ),
  CONSTRAINT ak_key_2_db_patch UNIQUE ("name")
)
SQL
        );
    }

    return $connection;
}

/**
 * @param DBPatcher\PatchFile[] $patchFiles
 * @param \Doctrine\DBAL\Connection $connection
 * @return array
 */
function getRowsFromDbForPatchFiles($patchFiles, $connection)
{
    $rowsFromDb = $connection->executeQuery(
        'SELECT "name", status, md5 FROM db_patcher WHERE "name" IN (?)',
        array_map(function ($p) { return $p->name; }, $patchFiles),
        array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
    )->fetchAll();

    return array_combine(array_map(function ($r) { return $r['name']; }, $rowsFromDb), $rowsFromDb);
}

/**
 * @param \Doctrine\DBAL\Connection $connection
 * @param DBPatcher\PatchFile $patchFile
 */
function savePatchFile($connection, $patchFile)
{
    $name = $connection->quote($patchFile->name, \PDO::PARAM_INT);
    if ($connection->update('db_patch', array('status' => $patchFile->status), array('name' => $name)) === 0) {
        $connection->insert('db_patch', array('status' => $patchFile->status, 'name' => $name));
    }
}
