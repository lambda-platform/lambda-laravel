<?php

namespace Lambda\Agent\Helper;

use Illuminate\Support\Facades\Config;

class AgentHelper
{
    public $title = '';
    public $domain = '';
    public $favicon = '';
    public $subTitle = '';
    public $bg = '';
    public $copyright = '';
    public $logo = '';
    public $sideColor = '';
    public $agentLogo = '';

    public function __construct()
    {
        $config = Config::get('lambda');
        $this->domain = $config['domain'];
        $this->title = $config['title'];
        $this->subTitle = $config['subTitle'];
        $this->favicon = $config['favicon'];
        $this->bg = $config['bg'];
        $this->copyright = $config['copyright'];
        $this->logo = $config['logo'];
    }
}
