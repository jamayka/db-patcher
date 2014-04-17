<?php

namespace DBPatcher\Strategy;

use \Mockery as m;
use DBPatcher as p;

class StrategyTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider regularStrategyTestProvider
     */
    public function testRegularStrategy($shouldSkip, $status)
    {
        $patchFile = p\PatchFile::copyWithNewStatus(p\PatchFile::_createForTest('n1', 'f1', 'm1', 'e1'), $status);
        $this->assertSame($shouldSkip, regularStrategy($patchFile));
    }

    public function regularStrategyTestProvider()
    {
        return [
            [true, p\PatchFile::STATUS_NEW],
            [true, p\PatchFile::STATUS_CHANGED],
            [false, p\PatchFile::STATUS_INSTALLED],
            [false, p\PatchFile::STATUS_ERROR]
        ];
    }

    /**
     * @dataProvider strictStrategyTestProvider
     */
    public function testStrictStrategy($shouldSkip, $status)
    {
        $patchFile = p\PatchFile::copyWithNewStatus(p\PatchFile::_createForTest('n1', 'f1', 'm1', 'e1'), $status);
        $this->assertSame($shouldSkip, strictStrategy($patchFile));
    }

    public function strictStrategyTestProvider()
    {
        return [
            [true, p\PatchFile::STATUS_NEW],
            [false, p\PatchFile::STATUS_CHANGED],
            [false, p\PatchFile::STATUS_INSTALLED],
            [false, p\PatchFile::STATUS_ERROR]
        ];
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
        return [
            [true, p\PatchFile::STATUS_NEW],
            [true, p\PatchFile::STATUS_CHANGED],
            [true, p\PatchFile::STATUS_INSTALLED],
            [true, p\PatchFile::STATUS_ERROR]
        ];
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
                [$falseStrategy, 'call'],
                $inputs
            )
        );
    }

    public function testStrategyFactoryReturnsDefaultStrategy()
    {
        $m = m::mock();
        $m->shouldReceive('call')->once();

        call_user_func(
            strategyFactory(
                function ($patchFile) use ($m) {
                    $m->call();
                }
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
                function ($patchFile) use ($m) {
                    $m->callFromDefault();
                },
                [
                    '-o' => function ($patchFile) use ($m) {
                            $m->callFromMap();
                        }
                ],
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
        $inputs->shouldReceive('get')->with('-o')->andReturn(true)->once();

        call_user_func(
            strategyFactory(
                function ($patchFile) use ($m) {
                    $m->callFromDefault();
                },
                [
                    '-o' => function ($patchFile, $superStrategy) use ($m) {
                            $superStrategy($patchFile);
                            $m->callFromMap();
                        }
                ],
                $inputs
            ),
            ''
        );
    }

    public function testStrategyPassesNeededArgumentsToStrategy()
    {
        $m = m::mock();
        $m->shouldReceive('call')->with('test', true, [3], null)->once();

        call_user_func(
            strategyFactory(
                function ($patchFile, $arg1, $arg2, $arg3, $arg4) use ($m) {
                    $m->call($arg1, $arg2, $arg3, $arg4);
                },
                array(),
                null,
                array('arg1' => 'test', 'arg2' => true, 'arg3' => [3])
            ),
            ''
        );
    }

}
