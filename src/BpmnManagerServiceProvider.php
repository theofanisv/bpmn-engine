<?php

namespace Theograms\BpmnManager;

use Illuminate\Support\ServiceProvider;

class BpmnManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bpmn-manager.php', 'bpmn-manager');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/bpmn-manager.php' => config_path('bpmn-manager.php'),
            ], 'config');
            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'migrations');
        }

    }
}
