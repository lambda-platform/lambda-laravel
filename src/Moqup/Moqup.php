<?php

namespace Lambda\Moqup;

use Illuminate\Support\Facades\Facade;

class Moqup extends Facade
{
    public $uiSchema;

    public function __construct()
    {
        $this->uiSchema = [];
    }

    public function createSchema($el)
    {
        $schema = $this->uiSchema;
    }

    public function build()
    {
    }
}
