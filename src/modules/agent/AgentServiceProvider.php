<?php

namespace Lambda\Agent;

use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{

    public function boot()
    {
        include __DIR__ . '/routes.php';
        $this->publishes([
            __DIR__ . '/config/agent.php' => config_path('agent.php'),
        ], 'agent-config');
    }

    public function register()
    {
        $this->app->singleton(Agent::class, function () {
            return new Agent();
        });

        $this->app->alias(Agent::class, 'agent');
        $this->loadViewsFrom(__DIR__ . '/views', 'agent');
    }
}
?>
