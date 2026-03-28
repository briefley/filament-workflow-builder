<?php

namespace Briefley\WorkflowBuilder;

use Briefley\WorkflowBuilder\Console\Commands\DispatchDueWorkflowsCommand;
use Briefley\WorkflowBuilder\Services\WorkflowStepExecutorRegistry;
use Illuminate\Support\ServiceProvider;

class WorkflowBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workflow-builder.php', 'workflow-builder');

        $this->app->singleton(WorkflowStepExecutorRegistry::class, fn () => new WorkflowStepExecutorRegistry);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'workflow-builder');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            DispatchDueWorkflowsCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/workflow-builder.php' => config_path('workflow-builder.php'),
        ], 'workflow-builder-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'workflow-builder-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/workflow-builder'),
        ], 'workflow-builder-views');
    }
}
