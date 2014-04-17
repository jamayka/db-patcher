<?php

namespace DBPatcher\Apply;

use \Mockery as m;
use org\bovigo\vfs\vfsStream;
use DBPatcher;

class ApplyTest extends \PHPUnit_Framework_TestCase
{

    public function testApplySqlPatchCallsConnectionForSqlPatchAndReturnsPatchWithInstalledStatus()
    {
        vfsStream::setup('test', null, ['name.sql' => 'SELECT 1;SELECT 2;']);

        $patchFile = DBPatcher\PatchFile::createFromFS('name.sql', vfsStream::url('test'));

        /** @var \Doctrine\DBAL\SQLParserUtils Connection $c */
        $connection = m::mock();
        $connection->shouldReceive('beginTransaction')->once();
        $connection->shouldReceive('executeQuery')->with('SELECT 1;')->once();
        $connection->shouldReceive('executeQuery')->with('SELECT 2;')->once();
        $connection->shouldReceive('commit')->once();

        list($patch) = applySqlPatch($patchFile, $connection);
        $this->assertInstanceOf('\DBPatcher\PatchFile', $patch);
        $this->assertSame(DBPatcher\PatchFile::STATUS_INSTALLED, $patch->status);
    }

    public function testApplySqlPatchReturnsPatchWithErrorStatusOnExceptionInSql()
    {
        vfsStream::setup('test', null, ['name.sql' => 'SELE']);

        $patchFile = DBPatcher\PatchFile::createFromFS('name.sql', vfsStream::url('test'));

        /** @var \Doctrine\DBAL\SQLParserUtils Connection $c */
        $connection = m::mock();
        $connection->shouldReceive('beginTransaction')->once();
        $connection->shouldReceive('executeQuery')
            ->with('SELE')
            ->once()
            ->andThrow('\Doctrine\DBAL\DBALException', 'Error test');
        $connection->shouldReceive('rollBack')->once();

        list($patch, $errorMsg) = applySqlPatch($patchFile, $connection);
        $this->assertSame('Error test', $errorMsg);
        $this->assertInstanceOf('\DBPatcher\PatchFile', $patch);
        $this->assertSame(DBPatcher\PatchFile::STATUS_ERROR, $patch->status);
    }

    public function testApplyPhpPatchCallsExecAndReturnsInstalledIfExitCodeIsZero()
    {
        $patchFile = DBPatcher\PatchFile::_createForTest('patch.php', 'test/patch.php', 'sfgsdg', 'php');

        $cmd = m::mock(function ($m) { $m->shouldIgnoreMissing(); });
        $cmd->shouldReceive('exec')->with('/usr/bin/env php test/patch.php')->andSet('return_var', 0)->once();

        list($patch) = applyPhpPatch($patchFile, $cmd);
        $this->assertInstanceOf('\DBPatcher\PatchFile', $patch);
        $this->assertSame(DBPatcher\PatchFile::STATUS_INSTALLED, $patch->status);
    }

    public function testApplyPhpPatchCallsExecAndReturnsErrorIfExitCodeISNotZero()
    {
        $patchFile = DBPatcher\PatchFile::_createForTest('patch.php', 'test/patch.php', 'sfgsdg', 'php');

        $cmd = m::mock(function ($m) { $m->shouldIgnoreMissing(); });
        $cmd->shouldReceive('exec')
            ->with('/usr/bin/env php test/patch.php')
            ->andSet('return_var', 3)
            ->andSet('output', ['test error!'])
            ->once();

        list($patch, $errorMsg) = applyPhpPatch($patchFile, $cmd);
        $this->assertInstanceOf('\DBPatcher\PatchFile', $patch);
        $this->assertSame('test error!', $errorMsg);
        $this->assertSame(DBPatcher\PatchFile::STATUS_ERROR, $patch->status);
    }

    public function testApplyPatchCallsStrategyCallbackForPatchAndSkipsItWhenFalse()
    {
        $patchFile = DBPatcher\PatchFile::_createForTest('patch.php', 'test/patch.php', 'sfgsdg', 'php');

        $strategy = m::mock();
        $strategy->shouldReceive('call')->with($patchFile)->andReturn(false);

        $cmd = m::mock(function ($m) { $m->shouldIgnoreMissing(); });
        $cmd->shouldReceive('exec')->never();

        list($patch) = applyPatch($patchFile, m::mock(), $cmd, [$strategy, 'call']);
        $this->assertNull($patch);
    }

    public function testApplyPatchCallsStrategyCallbackForPatchAndAppliesItWhenTrue()
    {
        $patchFile = DBPatcher\PatchFile::_createForTest('patch.php', 'test/patch.php', 'sfgsdg', 'php');

        $strategy = m::mock();
        $strategy->shouldReceive('call')->with($patchFile)->andReturn(true);

        $cmd = m::mock(function ($m) { $m->shouldIgnoreMissing(); });
        $cmd->shouldReceive('exec')->andSet('return_var', 0)->once();

        list($patch) = applyPatch($patchFile, m::mock(), $cmd, [$strategy, 'call']);
        $this->assertNotNull($patch);
    }

}
