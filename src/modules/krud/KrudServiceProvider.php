<?php

namespace Lambda\Krud;

use Illuminate\Support\ServiceProvider;

class KrudServiceProvider extends ServiceProvider
{
    public function register()
    {
        include __DIR__.'/routes.php';
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/config.php' => config_path('crud.php'),
        ]);
    }
}
