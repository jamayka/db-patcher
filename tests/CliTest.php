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
        $inputs->shouldReceive('option')->withArgs(array('-n, --new', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-f, --force', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-i, --interactive', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-s, --stop-on-error', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-c, --config [filename]', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-d, --dir [path]', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-p, --patch [name]', m::any()))->once();

        $this->assertSame($inputs, getConfiguredOptions($inputs));
    }

    public function testGetConfigLoadConfigFromEtc()
    {
        $configStructure = array('etc' => array('db-patcher.json' => json_encode(array('test' => 'value'))));
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $this->assertSame(array('test' => 'value'), getConfig(vfs::url('root/vendor/xiag/db-patcher')));
    }

    public function testGetConfigLoadConfigFromDataEtc()
    {
        $configStructure = array(
            'data' => array('etc' => array('db-patcher.json' => json_encode(array('test' => 'value'))))
        );
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $this->assertSame(array('test' => 'value'), getConfig(vfs::url('root/vendor/xiag/db-patcher')));
    }

    public function testGetConfigPrefersForcedPath()
    {
        $configStructure = array(
            'etc' => array('db-patcher.json' => json_encode(array('test' => 'value'))),
            'someconfig.json' => json_encode(array('test' => 'value2'))
        );
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $this->assertSame(
            array('test' => 'value2'),
            getConfig(vfs::url('root/vendor/xiag/db-patcher'), vfs::url('root/someconfig.json'))
        );
    }

    public function testGetConfigSkipsConfigsWithErrors()
    {
        $configStructure = array(
            'etc' => array('db-patcher.json' => 'ffffffffffff'),
            'data' => array('etc' => array('db-patcher.json' => json_encode(array('test' => 'value'))))
        );
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $this->assertSame(array('test' => 'value'), getConfig(vfs::url('root/vendor/xiag/db-patcher')));
    }

    public function testGetConfigChangesRelativeDirectoryToAbsolute()
    {
        $configStructure = array(
            'etc' => array('db-patcher.json' => json_encode(array('directory' => '../patches'))),
            'patches' => array()
        );
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $config = getConfig(vfs::url('root/vendor/xiag/db-patcher'));
        $this->assertSame(vfs::url('root/vendor/xiag/db-patcher/../../../etc/../patches'), $config['directory']);
    }

    private static function rootProjectFileStructure($add = array())
    {
        return array_merge($add, array('vendor' => array('xiag' => array('db-patcher' => array()))));
    }

}
