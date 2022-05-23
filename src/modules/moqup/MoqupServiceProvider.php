<?php

namespace Lambda\Moqup;

use Illuminate\Support\ServiceProvider;

class MoqupServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }

    public function register()
    {
        $this->app->singleton(Puzzle::class, function () {
            return new Moqup();
        });
        $this->app->alias(Moqup::class, 'moqup');
        $this->loadViewsFrom(__DIR__.'/views', 'moqup');
    }
}
