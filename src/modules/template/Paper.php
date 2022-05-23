<?php

namespace Lambda\Template\Paper;

use Illuminate\Support\Facades\Facade;

class Paper extends Facade
{
    public static function paper()
    {
        return "Hello Paper";
    }
}