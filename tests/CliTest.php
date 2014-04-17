<?php

namespace DBPatcher\Cli;

use \Mockery as m;
use DBPatcher;
use \org\bovigo\vfs\vfsStream as vfs;

class CliTest extends \PHPUnit_Framework_TestCase
{

    public function testOptionsFactory()
    {
        $inputs = m::mock(function ($m) { $m->shouldIgnoreMissing(); });
        $inputs->shouldReceive('option')->withArgs(['-n, --new', m::any()])->once();
        $inputs->shouldReceive('option')->withArgs(['-f, --force', m::any()])->once();
        $inputs->shouldReceive('option')->withArgs(['-i, --interactive', m::any()])->once();
        $inputs->shouldReceive('option')->withArgs(['-s, --stop-on-error', m::any()])->once();
        $inputs->shouldReceive('option')->withArgs(['-c, --config [filename]', m::any()])->once();
        $inputs->shouldReceive('option')->withArgs(['-d, --dir [path]', m::any()])->once();
        $inputs->shouldReceive('option')->withArgs(['-p, --patch [name]', m::any()])->once();

        $this->assertSame($inputs, getConfiguredOptions($inputs));
    }

    public function testGetConfigLoadConfigFromEtc()
    {
        $configStructure = ['etc' => ['db-patcher.json' => json_encode(['test' => 'value'])]];
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $this->assertSame(['test' => 'value'], getConfig(vfs::url('root/vendor/xiag/db-patcher')));
    }

    public function testGetConfigLoadConfigFromDataEtc()
    {
        $configStructure = ['data' => ['etc' => ['db-patcher.json' => json_encode(['test' => 'value'])]]];
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $this->assertSame(['test' => 'value'], getConfig(vfs::url('root/vendor/xiag/db-patcher')));
    }

    public function testGetConfigPrefersForcedPath()
    {
        $configStructure = [
            'etc' => ['db-patcher.json' => json_encode(['test' => 'value'])],
            'someconfig.json' => json_encode(['test' => 'value2'])
        ];
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $this->assertSame(
            ['test' => 'value2'],
            getConfig(vfs::url('root/vendor/xiag/db-patcher'), vfs::url('root/someconfig.json'))
        );
    }

    public function testGetConfigSkipsConfigsWithErrors()
    {
        $configStructure = [
            'etc' => ['db-patcher.json' => 'ffffffffffff'],
            'data' => ['etc' => ['db-patcher.json' => json_encode(['test' => 'value'])]]
        ];
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $this->assertSame(['test' => 'value'], getConfig(vfs::url('root/vendor/xiag/db-patcher')));
    }

    public function testGetConfigChangesRelativeDirectoryToAbsolute()
    {
        $configStructure = [
            'etc' => ['db-patcher.json' => json_encode(['directory' => '../patches'])],
            'patches' => array()
        ];
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $config = getConfig(vfs::url('root/vendor/xiag/db-patcher'));
        $this->assertSame(vfs::url('root/vendor/xiag/db-patcher/../../../etc/../patches'), $config['directory']);
    }

    private static function rootProjectFileStructure($add = [])
    {
        return array_merge($add, ['vendor' => ['xiag' => ['db-patcher' => []]]]);
    }

}
