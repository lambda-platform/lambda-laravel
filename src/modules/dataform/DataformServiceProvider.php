<?php

namespace Lambda\Dataform;

use Illuminate\Support\ServiceProvider;

class DataformServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/config.php' => config_path('puzzle.php'),
        ]);
    }
}
