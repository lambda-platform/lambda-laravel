<?php

namespace Lambda\Agent;

use Illuminate\Support\Facades\Facade;

class Agent extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Agent';
    }
}