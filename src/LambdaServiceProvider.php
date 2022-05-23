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

        $this->loadViewsFrom(__DIR__ . '/modules/agent/views', 'agent');
        $this->loadViewsFrom(__DIR__ . '/modules/puzzle/views', 'puzzle');
    }

    public function boot()
    {

        include __DIR__.'/modules/puzzle/routes.php';
        include __DIR__.'/modules/agent/routes.php';
        include __DIR__.'/modules/krud/routes.php';

        $this->publishes([
            __DIR__ . '/config/lambda-config.php' => config_path('lambda.php'),
        ]);
    }
}