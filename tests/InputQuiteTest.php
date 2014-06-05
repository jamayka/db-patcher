<?php

namespace DBPatcher;

use \Mockery as m;

class InputQuiteTest extends \PHPUnit_Framework_TestCase
{

    public function testConfirmAndPromptReturnTrue()
    {
        $p = self::inputs();
        $this->assertTrue($p->confirm('Confirm'));
        $this->assertTrue($p->prompt('Prompt'));
    }

    public function testRedirectsOtherMethodsToOriginalInputObject()
    {
        $m = m::mock();
        $m->shouldReceive('get')->with('opt')->andReturn('some')->once();
        $m->shouldReceive('getInputs')->withNoArgs()->andReturn(array('val'))->once();

        $p = self::inputs($m);
        $this->assertSame('some', $p->get('opt'));
        $this->assertSame(array('val'), $p->getInputs());
    }

    private static function inputs($originalInputs = null)
    {
        return new InputQuite($originalInputs ?: m::mock());
    }

}
