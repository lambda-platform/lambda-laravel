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
        $this->loadViewsFrom(__DIR__ . '/puzzle/views', 'puzzle');
        $this->loadViewsFrom(__DIR__ . '/template/views', 'template');

        $this->loadRoutesFrom(__DIR__ . '/Agent/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/puzzle/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/krud/routes.php');

        $this->publishes([
            __DIR__ . '/config/lambda-config.php' => config_path('lambda.php'),
        ]);
    }
}
