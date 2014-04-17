<?php

namespace DBPatcher\Storage;

use \Mockery as m;
use DBPatcher;

class StorageTest extends \PHPUnit_Framework_TestCase
{

    function testGetDbConnectionChecksForPatcherTable()
    {
        $connection = m::mock()->shouldIgnoreMissing();
        $connection->shouldReceive('executeQuery')->with('SELECT id FROM db_patch LIMIT 1')->once();

        getDbConnection($connection);
    }

    function testGetDbConnectionShouldCreateStructureIfNeeded()
    {
        $connection = m::mock()->shouldIgnoreMissing();
        $connection->shouldReceive('executeQuery')
            ->with('SELECT id FROM db_patch LIMIT 1')
            ->once()
            ->andThrow(new \Doctrine\DBAL\DBALException());
        $connection->shouldReceive('executeQuery')->with('/^CREATE TABLE db_patch/');

        getDbConnection($connection);
    }

    public function testGetRowsFromDbForPatchFilesCallsCorrectSqlAndReturnCorrectStructure()
    {
        $patchFile1 = DBPatcher\PatchFile::_createForTest('n1', 'f1', 'm1', 'e1');
        $patchFile2 = DBPatcher\PatchFile::_createForTest('n2', 'f2', 'm2', 'e2');
        $patchFile3 = DBPatcher\PatchFile::_createForTest('n3', 'f3', 'm3', 'e3');

        $statement = m::mock();
        $statement->shouldReceive('fetchAll')
            ->withNoArgs()
            ->andReturn(
                array(
                    array('name' => 'n1', 'status' => DBPatcher\PatchFile::STATUS_INSTALLED, 'md5' => 'mm1'),
                    array('name' => 'n2', 'status' => DBPatcher\PatchFile::STATUS_ERROR, 'md5' => 'mm2')
                )
            )
            ->once();

        $connection = m::mock()->shouldIgnoreMissing();
        $connection->shouldReceive('executeQuery')
            ->with(
                'SELECT "name", status, md5 FROM db_patcher WHERE "name" IN (?)',
                array('n1', 'n2', 'n3'),
                array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            )
            ->andReturn($statement)
            ->once();

        $this->assertEquals(
            array(
                'n1' => array('name' => 'n1', 'status' => DBPatcher\PatchFile::STATUS_INSTALLED, 'md5' => 'mm1'),
                'n2' => array('name' => 'n2', 'status' => DBPatcher\PatchFile::STATUS_ERROR, 'md5' => 'mm2')
            ),
            getRowsFromDbForPatchFiles(array($patchFile1, $patchFile2, $patchFile3), $connection)
        );
    }

    public function testSavePatchFileCallsDbUpdateMethod()
    {
        $patchFile = DBPatcher\PatchFile::_createForTest('n1', 'f1', 'm1', 'e1', DBPatcher\PatchFile::STATUS_INSTALLED);

        $connection = m::mock();
        $connection->shouldReceive('quote')->andReturnUsing(function ($a) { return $a; });
        $connection->shouldReceive('update')
            ->with('db_patch', array('status' => DBPatcher\PatchFile::STATUS_INSTALLED), array('name' => 'n1'))
            ->andReturn(1)
            ->once();
        $connection->shouldReceive('insert')->never();

        savePatchFile($connection, $patchFile);
    }

    public function testSavePatchFileCallsDbInsertIfUpdateAffectedNoRows()
    {
        $patchFile = DBPatcher\PatchFile::_createForTest('n1', 'f1', 'm1', 'e1', DBPatcher\PatchFile::STATUS_INSTALLED);

        $connection = m::mock();
        $connection->shouldReceive('quote')->andReturnUsing(function ($a) { return $a; });
        $connection->shouldReceive('update')->andReturn(0)->once();
        $connection->shouldReceive('insert')
            ->with('db_patch', array('status' => DBPatcher\PatchFile::STATUS_INSTALLED, 'name' => 'n1'))
            ->once();

        savePatchFile($connection, $patchFile);
    }

}
