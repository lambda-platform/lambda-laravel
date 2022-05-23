<?php

namespace Lambda\Template\Paper\helper;

use Illuminate\Support\Facades\Config;

class TemplateHelper
{
    public $title = '';
    public $favicon = '';
    public $logo = '';

    public function __construct()
    {
        $config = Config::get('lambda');
        $this->title = $config['title'];
        $this->favicon = $config['favicon'];
        $this->logo = $config['logo'];
    }
}
