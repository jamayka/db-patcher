<?php

namespace DBPatcher;

use \Mockery as m;

class InputPreviewTest extends \PHPUnit_Framework_TestCase
{

    public function testConfirmThrowsException()
    {
        $this->setExpectedException('\DBPatcher\InputPreview\Exception');
        self::inputs()->confirm('Confirm');
    }

    public function testPromptThrowsException()
    {
        $this->setExpectedException('\DBPatcher\InputPreview\Exception');
        self::inputs()->prompt('Confirm');
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
        return new InputPreview($originalInputs ?: m::mock());
    }

}
