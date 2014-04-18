<?php

namespace DBPatcher;

use DBPatcher\InputPreview\Exception;

class InputPreview extends \FusePump\Cli\Inputs
{

    public static function confirm()
    {
        throw new Exception;
    }

    public static function prompt()
    {
        throw new Exception;
    }

}