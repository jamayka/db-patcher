<?php

namespace DBPatcher\Apply;

use \Mockery as m;
use org\bovigo\vfs\vfsStream;
use DBPatcher;
use Symfony\Component\Process\Process;

class ApplyTest extends \PHPUnit_Framework_TestCase
{

    public function testApplySqlPatchCallsConnectionForSqlPatchAndReturnsPatchWithInstalledStatus()
    {
        vfsStream::setup('test', null, array('name.sql' => 'SELECT 1;SELECT 2;'));

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
        vfsStream::setup('test', null, array('name.sql' => 'SELE'));

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

        $cmd = m::mock()->shouldIgnoreMissing();
        $cmd->shouldReceive('setCommandLine')->with('/usr/bin/env php test/patch.php')->once();
        $cmd->shouldReceive('start')->once();
        $cmd->shouldReceive('wait')->andReturn(0)->once();

        list($patch) = applyPhpPatch($patchFile, $cmd);
        $this->assertInstanceOf('\DBPatcher\PatchFile', $patch);
        $this->assertSame(DBPatcher\PatchFile::STATUS_INSTALLED, $patch->status);
    }

    public function testApplyPhpPatchReturnsErrorIfExitCodeISNotZero()
    {
        $patchFile = DBPatcher\PatchFile::_createForTest('patch.php', 'test/patch.php', 'sfgsdg', 'php');

        $cmd = m::mock()->shouldIgnoreMissing();
        $cmd->shouldReceive('wait')->andReturn(1)->once();
        $cmd->shouldReceive('getErrorOutput')->andReturn('test error!')->once();

        list($patch, $errorMsg) = applyPhpPatch($patchFile, $cmd);
        $this->assertInstanceOf('\DBPatcher\PatchFile', $patch);
        $this->assertSame('test error!', $errorMsg);
        $this->assertSame(DBPatcher\PatchFile::STATUS_ERROR, $patch->status);
    }

    public function testApplyPhpPatchRedirectsStdout()
    {
        $patchFile = DBPatcher\PatchFile::_createForTest('patch.php', 'test/patch.php', 'sfgsdg', 'php');

        $processCb = function () {};

        $cmd = m::mock()->shouldIgnoreMissing();
        $cmd->shouldReceive('start')
            ->with(m::on(function ($cb) use (&$processCb) { $processCb = $cb; return true; }))
            ->once();
        $cmd->shouldReceive('wait')
            ->andReturnUsing(function () use (&$processCb) { $processCb(Process::OUT, 'test'); return 0; });

        $stdout = fopen('php://temp', 'r+');

        applyPhpPatch($patchFile, $cmd, $stdout);

        rewind($stdout);
        $this->assertSame('test', stream_get_contents($stdout));
        fclose($stdout);
    }

    public function testApplyPhpPatchRedirectsStderr()
    {
        $patchFile = DBPatcher\PatchFile::_createForTest('patch.php', 'test/patch.php', 'sfgsdg', 'php');

        $processCb = function () {};

        $cmd = m::mock()->shouldIgnoreMissing();
        $cmd->shouldReceive('start')
            ->with(m::on(function ($cb) use (&$processCb) { $processCb = $cb; return true; }))
            ->once();
        $cmd->shouldReceive('wait')
            ->andReturnUsing(function () use (&$processCb) { $processCb(Process::ERR, 'test error'); return 0; });

        $stderr = fopen('php://temp', 'r+');

        applyPhpPatch($patchFile, $cmd, null, $stderr);

        rewind($stderr);
        $this->assertSame('test error', stream_get_contents($stderr));
        fclose($stderr);
    }

}
