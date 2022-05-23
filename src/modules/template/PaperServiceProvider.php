<?php

namespace Lambda\Template\Paper;

use Illuminate\Support\ServiceProvider;

class PaperServiceProvider extends ServiceProvider
{

    public function boot()
    {
        /*
         * Paper config. you can publish by php artisan vendor:publish --tag=paper-config
         * */
        $this->publishes([
            __DIR__ . '/config/paper.php' => config_path('paper.php'),
        ], 'paper-config');
    }

    public function register()
    {
        $this->app->singleton(Paper::class, function () {
            return new Paper();
        });

        $this->app->alias(Paper::class, 'template');
        $this->loadViewsFrom(__DIR__ . '/views', 'template');
    }
}