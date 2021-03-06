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
        $inputs->shouldReceive('option')->withArgs(array('-q, --quite', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-l, --list', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-n, --new', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-c, --changed', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-e, --error', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-a, --all', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-i, --interactive', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-m, --mark-installed', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-s, --stop-on-error', m::any()))->once();
        $inputs->shouldReceive('option')->withArgs(array('-cf, --config [filename]', m::any()))->once();

        $inputs->shouldReceive('param')->withArgs(array('masks', m::any()))->once();

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

    public function testGetConfigCanReadPhpConfigFiles()
    {
        $configStructure = array('etc' => array('db-patcher.php' => "<?php return array('test' => 'value');"));
        vfs::setup('root', null, self::rootProjectFileStructure($configStructure));

        $this->assertSame(array('test' => 'value'), getConfig(vfs::url('root/vendor/xiag/db-patcher')));
    }

    public function testOptionsStrategiesMap()
    {
        $this->assertSame(
            array(
                '-n' => '\DBPatcher\Strategy\newStrategy',
                '-c' => '\DBPatcher\Strategy\changedStrategy',
                '-e' => '\DBPatcher\Strategy\errorStrategy',
                '-a' => '\DBPatcher\Strategy\forceAllStrategy',
                '-i' => '\DBPatcher\Strategy\interactiveStrategy'
            ),
            optionsStrategiesMap()
        );
    }

    public function testGetConfigOptionCallsCorrectOption()
    {
        $inputs = m::mock();
        $inputs->shouldReceive('get')->with('--config')->once()->andReturn('some');

        $this->assertSame('some', getConfigOption($inputs));
    }

    public function testMarkPatchesOptionCallsCorrectOption()
    {
        $inputs = m::mock();
        $inputs->shouldReceive('get')->with('-m')->once()->andReturn(true);

        $this->assertTrue(getMarkPatchesOption($inputs));
    }

    public function testGetPatchFileMasksToApplyCallsCorrectOptionAndReturnsResult()
    {
        $inputs = m::mock();
        $inputs->shouldReceive('get')->with('masks')->once()->andReturn('f1');
        $inputs->shouldReceive('getInputs')->once()->andReturn(array('f2', 'f3', '-c' => 'some', 'masks' => 'f1'));

        $this->assertSame(array('f1', 'f2', 'f3'), getPatchFileMasksToApply($inputs));
    }

    public function testGetListOnlyOptionCallsCorrectOption()
    {
        $inputs = m::mock();
        $inputs->shouldReceive('get')->with('-l')->once()->andReturn(true);

        $this->assertTrue(getListOnlyOption($inputs));
    }

    public function testGetStopOnErrorOptionCallsCorrectOption()
    {
        $inputs = m::mock();
        $inputs->shouldReceive('get')->with('-s')->once()->andReturn(true);

        $this->assertTrue(getStopOnErrorOption($inputs));
    }

    public function testGetQuiteOptionCallsCorrectOption()
    {
        $inputs = m::mock();
        $inputs->shouldReceive('get')->with('-q')->once()->andReturn(true);

        $this->assertTrue(getQuiteOption($inputs));
    }

    private static function rootProjectFileStructure($add = array())
    {
        return array_merge($add, array('vendor' => array('xiag' => array('db-patcher' => array()))));
    }

}
