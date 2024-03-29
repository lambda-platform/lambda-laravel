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

        $this->loadViewsFrom(__DIR__ . '/Agent/Views', 'agent');
        $this->loadViewsFrom(__DIR__ . '/Puzzle/Views', 'puzzle');
        $this->loadViewsFrom(__DIR__ . '/Template/Views', 'template');

        $this->loadRoutesFrom(__DIR__ . '/Agent/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Puzzle/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Krud/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Process/routes.php');

        $this->publishes([
            __DIR__ . '/config/lambda-config.php' => config_path('lambda.php'),
        ]);
    }
}
