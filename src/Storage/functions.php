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
        $connection->executeQuery('SELECT id FROM db_patcher LIMIT 1');
    } catch (\Doctrine\DBAL\DBALException $e) {
        $connection->executeQuery(<<<SQL
CREATE TABLE db_patcher
(
  id serial NOT NULL,
  "name" text NOT NULL,
  status smallint NOT NULL DEFAULT 0,
  md5 text NOT NULL,
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

    if (!empty($rowsFromDb)) {
        return array_combine(array_map(function ($r) { return $r['name']; }, $rowsFromDb), $rowsFromDb);
    }

    return array();
}

/**
 * @param \Doctrine\DBAL\Connection $connection
 * @param DBPatcher\PatchFile $patchFile
 */
function savePatchFile($connection, $patchFile)
{
    $name = $connection->quote($patchFile->name, \PDO::PARAM_INT);
    $data = array('status' => $patchFile->status, 'md5' => $patchFile->md5);
    if ($connection->update('db_patcher', $data, array('name' => $name)) === 0) {
        $connection->insert('db_patcher', array_merge($data, array('name' => $name)));
    }
}
