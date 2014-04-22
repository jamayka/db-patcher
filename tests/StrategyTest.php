<?php

namespace DBPatcher\Strategy;

use \Mockery as m;
use DBPatcher as p;

class StrategyTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider newStrategyTestProvider
     */
    public function testNewStrategy($shouldSkip, $status)
    {
        $patchFile = p\PatchFile::copyWithNewStatus(p\PatchFile::_createForTest('n1', 'f1', 'm1', 'e1'), $status);
        $this->assertSame($shouldSkip, newStrategy($patchFile, function () { return false; }));
    }

    public function newStrategyTestProvider()
    {
        return array(
            array(true, p\PatchFile::STATUS_NEW),
            array(false, p\PatchFile::STATUS_CHANGED),
            array(false, p\PatchFile::STATUS_INSTALLED),
            array(false, p\PatchFile::STATUS_ERROR)
        );
    }

    /**
     * @dataProvider changedStrategyTestProvider
     */
    public function testChangedStrategy($shouldSkip, $status)
    {
        $patchFile = p\PatchFile::copyWithNewStatus(p\PatchFile::_createForTest('n1', 'f1', 'm1', 'e1'), $status);
        $this->assertSame($shouldSkip, changedStrategy($patchFile, function () { return false; }));
    }

    public function changedStrategyTestProvider()
    {
        return array(
            array(false, p\PatchFile::STATUS_NEW),
            array(true, p\PatchFile::STATUS_CHANGED),
            array(false, p\PatchFile::STATUS_INSTALLED),
            array(false, p\PatchFile::STATUS_ERROR)
        );
    }

    /**
     * @dataProvider errorStrategyTestProvider
     */
    public function testErrorStrategy($shouldSkip, $status)
    {
        $patchFile = p\PatchFile::copyWithNewStatus(p\PatchFile::_createForTest('n1', 'f1', 'm1', 'e1'), $status);
        $this->assertSame($shouldSkip, errorStrategy($patchFile, function () { return false; }));
    }

    public function errorStrategyTestProvider()
    {
        return array(
            array(false, p\PatchFile::STATUS_NEW),
            array(false, p\PatchFile::STATUS_CHANGED),
            array(false, p\PatchFile::STATUS_INSTALLED),
            array(true, p\PatchFile::STATUS_ERROR)
        );
    }

    /**
     * @dataProvider forceAllStrategyTestProvider
     */
    public function testForceAllStrategy($shouldSkip, $status)
    {
        $patchFile = p\PatchFile::copyWithNewStatus(p\PatchFile::_createForTest('n1', 'f1', 'm1', 'e1'), $status);
        $this->assertSame($shouldSkip, forceAllStrategy($patchFile));
    }

    public function forceAllStrategyTestProvider()
    {
        return array(
            array(true, p\PatchFile::STATUS_NEW),
            array(true, p\PatchFile::STATUS_CHANGED),
            array(true, p\PatchFile::STATUS_INSTALLED),
            array(true, p\PatchFile::STATUS_ERROR)
        );
    }

    public function testInteractiveStrategyShouldAskSuperStrategyAndThenReturnConfirmResult()
    {
        $inputs = m::mock();
        $inputs->shouldReceive('confirm')->andReturn(false)->once();

        $falseStrategy = m::mock();
        $falseStrategy->shouldReceive('call')->andReturn(false)->once();

        $this->assertFalse(
            interactiveStrategy(
                p\PatchFile::_createForTest('n1', 'f1', 'm1', 'e1'),
                array($falseStrategy, 'call'),
                $inputs
            )
        );
    }

    public function testStrategyFactoryReturnsDefaultStrategy()
    {
        $m = m::mock();
        $m->shouldReceive('call1')->once();
        $m->shouldReceive('call2')->never();

        $c1fn = function ($patchFile) use ($m) {
            $m->call1();
        };
        call_user_func(
            strategyFactory(
                array($c1fn),
                array(
                    '-c1' => $c1fn,
                    '-c2' => function ($patchFile) use ($m) {
                        $m->call2();
                    }
                ),
                m::mock()->shouldIgnoreMissing()
            ),
            ''
        );
    }

    public function testStrategyFactoryReturnsStrategyFromMapIfInputSpecified()
    {
        $m = m::mock();
        $m->shouldReceive('callFromDefault')->never();
        $m->shouldReceive('callFromMap')->once();

        $inputs = m::mock();
        $inputs->shouldReceive('get')->with('-o')->andReturn(true)->once();

        call_user_func(
            strategyFactory(
                array(
                    function ($patchFile) use ($m) {
                        $m->callFromDefault();
                    }
                ),
                array(
                    '-o' => function ($patchFile) use ($m) {
                            $m->callFromMap();
                        }
                ),
                $inputs
            ),
            ''
        );
    }

    public function testStrategyFactoryShouldPassSuperStrategyIfNeeded()
    {
        $m = m::mock();
        $m->shouldReceive('callFromDefault')->once();
        $m->shouldReceive('callFromMap')->once();

        $inputs = m::mock();
        $inputs->shouldReceive('get')->with('-d')->andReturn(true)->once();
        $inputs->shouldReceive('get')->with('-o')->andReturn(true)->once();

        call_user_func(
            strategyFactory(
                array(),
                array(
                    '-d' => function ($patchFile) use ($m) {
                            $m->callFromDefault();
                        },
                    '-o' => function ($patchFile, $superStrategy) use ($m) {
                            $superStrategy($patchFile);
                            $m->callFromMap();
                        }
                ),
                $inputs
            ),
            ''
        );
    }

    public function testStrategyPassesNeededArgumentsToStrategy()
    {
        $m = m::mock();
        $m->shouldReceive('call')->with('test', true, array(3), null)->once();

        call_user_func(
            strategyFactory(
                array(
                    function ($patchFile, $arg1, $arg2, $arg3, $arg4) use ($m) {
                        $m->call($arg1, $arg2, $arg3, $arg4);
                    }
                ),
                array(),
                m::mock()->shouldIgnoreMissing(),
                array('arg1' => 'test', 'arg2' => true, 'arg3' => array(3))
            ),
            ''
        );
    }

}
