<?php

namespace Lambda\Datagrid;

use Illuminate\Support\ServiceProvider;

class DatagridServiceProvider extends ServiceProvider
{
    public function register()
    {

    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/config.php' => config_path('datagrid.php'),
        ]);
    }
}
