<?php

namespace Lambda;

use Illuminate\Support\ServiceProvider;

class LambdaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Lambda::class, function () {
            return new Lambda();
        });
    }

    public function boot()
    {

        $this->loadViewsFrom(__DIR__ . '/Agent/views', 'agent');
        $this->loadViewsFrom(__DIR__ . '/Puzzle/views', 'puzzle');
        $this->loadViewsFrom(__DIR__ . '/Template/views', 'template');

        $this->loadRoutesFrom(__DIR__ . '/Agent/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Puzzle/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Krud/routes.php');

        $this->publishes([
            __DIR__ . '/config/lambda-config.php' => config_path('lambda.php'),
        ]);
    }
}
