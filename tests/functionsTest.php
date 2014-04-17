<?php

namespace DBPatcher;

use \Mockery as m;
use org\bovigo\vfs\vfsStream;

class DBPatcherTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider statusesMd5Provider
     */
    public function testGetPatchWithUpdatedStatus($expectedStatus, $dbStatus, $dbMd5)
    {
        $patchFile = PatchFile::_createForTest('n1', 'f1', 'm1', 'e1');
        $this->assertSame($expectedStatus, getPatchWithUpdatedStatus($patchFile, $dbStatus, $dbMd5)->status);
    }

    public function statusesMd5Provider()
    {
        return array(
            array(PatchFile::STATUS_CHANGED, PatchFile::STATUS_INSTALLED, 'mm1'),
            array(PatchFile::STATUS_INSTALLED, PatchFile::STATUS_INSTALLED, 'm1'),
            array(PatchFile::STATUS_ERROR, PatchFile::STATUS_ERROR, 'm1'),
            array(PatchFile::STATUS_NEW, PatchFile::STATUS_ERROR, 'mm1')
        );
    }

    public function testGetPatchesWithStatusesCallsFactoryAndReturnsCorrectList()
    {
        $rowsFromDb = array(
            'n1' => array('name' => 'n1', 'status' => PatchFile::STATUS_INSTALLED, 'md5' => 'mm1'),
            'n2' => array('name' => 'n2', 'status' => PatchFile::STATUS_ERROR, 'md5' => 'mm2')
        );

        $patchFile1 = PatchFile::_createForTest('n1', 'f1', 'm1', 'e1');
        $patchFile2 = PatchFile::_createForTest('n2', 'f2', 'm2', 'e2');
        $patchFile3 = PatchFile::_createForTest('n3', 'f3', 'm3', 'e3');

        $patchFile1Changed = PatchFile::_createForTest('n1c', 'f1c', 'm1c', 'e1c');
        $patchFile2Changed = PatchFile::_createForTest('n2c', 'f2c', 'm2c', 'e2c');

        $factory = m::mock();
        $factory->shouldReceive('call')->with($patchFile1, PatchFile::STATUS_INSTALLED, 'mm1')
            ->andReturn($patchFile1Changed)->once();
        $factory->shouldReceive('call')->with($patchFile2, PatchFile::STATUS_ERROR, 'mm2')
            ->andReturn($patchFile2Changed)->once();

        $this->assertEquals(
            array($patchFile1Changed, $patchFile2Changed, $patchFile3),
            getPatchesWithStatuses(array($patchFile1, $patchFile2, $patchFile3), $rowsFromDb, array($factory, 'call'))
        );
    }

// --------------------------------------------------------------------------------------------------------------------

    public function testGetPatchNamesListReturnsSortedFilesListFromFilesystem()
    {
        $structure = array(
            '2.some-patch.sql' => '',
            '1.dir' => array(
                '5.php-script.php' => '',
                '1.patch.sql' => ''
            ),
            '3.other-patch.sql' => ''
        );

        vfsStream::setup('testDir', null, $structure);

        $expected = array(
            '1.dir/1.patch.sql',
            '1.dir/5.php-script.php',
            '2.some-patch.sql',
            '3.other-patch.sql'
        );

        $this->assertSame($expected, getPatchNamesList(vfsStream::url('testDir')));
    }

    public function testGetPatchFilesCallsFactoryMethodAndReturnsListOfPatchFiles()
    {
        $patch1 = PatchFile::_createForTest('n1', 'f1', 'm1', 'e1');
        $patch2 = PatchFile::_createForTest('n2', 'f2', 'm2', 'e2');

        $m = m::mock();
        $m->shouldReceive('invoke')->with('n1', 'baseDir')->once()->andReturn($patch1);
        $m->shouldReceive('invoke')->with('n2', 'baseDir')->once()->andReturn($patch2);

        $result = getPatchFiles(array('n1', 'n2'), 'baseDir', array($m, 'invoke'));

        $this->assertInternalType('array', $result);
        $this->assertSame(2, count($result));
        $this->assertSame($patch1, $result[0]);
        $this->assertSame($patch2, $result[1]);
    }

}

