<?php

namespace rajmundtoth0\LaravelJobRemove;

use Illuminate\Support\ServiceProvider;
use rajmundtoth0\LaravelJobRemove\Console\LaravelJobRemoveCommand;
use rajmundtoth0\LaravelJobRemove\Services\LaravelJobRemoveService;

class LaravelJobRemoveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LaravelJobRemoveService::class);
        if ($this->app->runningInConsole()) {
            $this->commands([
                LaravelJobRemoveCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/job-remove.php' => config_path('job-remove.php'),
            ]);
        }
    }
}
