<?php

namespace Lambda\Template\Helper;

use Illuminate\Support\Facades\Config;

class TemplateHelper
{
    public $title = '';
    public $favicon = '';
    public $logo = '';

    public function __construct()
    {
        $this->title = Config::get('lambda.title', '');
        $this->favicon = Config::get('lambda.favicon', '');
        $this->logo = Config::get('lambda.logo', '');
    }
}
