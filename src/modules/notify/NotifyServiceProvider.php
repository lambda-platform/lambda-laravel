<?php

namespace Lambda\Notify;

use Illuminate\Support\ServiceProvider;

class NotifyServiceProvider extends ServiceProvider
{
    public function boot()
    {
        include __DIR__ . '/routes.php';
    }

    public function register()
    {
        $this->app->singleton(Notify::class, function () {
            return new Notify();
        });

        $this->app->alias(Notify::class, 'notify');
    }
}
