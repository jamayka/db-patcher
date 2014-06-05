<?php

namespace DBPatcher;

use FusePump\Cli\Inputs;

class InputQuite
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
        return true;
    }

    public static function prompt()
    {
        return true;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->inputs, $name), $arguments);
    }

}
