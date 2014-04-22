<?php

namespace DBPatcher;

use DBPatcher\InputPreview\Exception;
use FusePump\Cli\Inputs;

class InputPreview
{

    /**
     * @var \FusePump\Cli\Inputs
     */
    private $inputs;

    /**
     * @param Inputs $inputs
     */
    public function __construct($inputs)
    {
        $this->inputs = $inputs;
    }

    public static function confirm()
    {
        throw new Exception;
    }

    public static function prompt()
    {
        throw new Exception;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->inputs, $name), $arguments);
    }

}