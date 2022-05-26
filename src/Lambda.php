<?php

namespace Lambda;

use Illuminate\Support\Facades\Facade;

class Lambda extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Lambda';
    }
}
