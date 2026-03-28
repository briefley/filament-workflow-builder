# Filament Workflow Builder

A generic Laravel workflow orchestration package with pluggable step executors and an optional Filament admin UI.

## Features

- Interval and fixed-time daily workflow scheduling.
- Queue-driven workflow execution with pluggable step executors.
- Stale run protection and overlap control.
- Filament integration with a single **Workflows** navigation entry:
  - `Steps` relation manager for workflow step configuration.
  - `Runs` relation manager for history.
  - Run steps visible in a modal from each run row.

## Installation

1. Require the package.

```bash
composer require briefley/filament-workflow-builder
```

2. Publish config and migrations.

```bash
php artisan vendor:publish --tag=workflow-builder-config
php artisan vendor:publish --tag=workflow-builder-migrations
php artisan migrate
```

## Configuration

Define available step types and executors in `config/workflow-builder.php`:

```php
return [
    'workflow_jobs' => [
        'hello_world_job' => [
            'label' => 'Hello World Job',
        ],
    ],

    'step_executors' => [
        'hello_world_job' => App\Workflow\Executors\HelloWorldStepExecutor::class,
    ],
];
```

## Executor Contract

Each step executor must implement:

- `Briefley\WorkflowBuilder\Contracts\WorkflowStepExecutor`

and return `Briefley\WorkflowBuilder\DTO\StepExecutionResult` from `execute()`.

## Scheduler

Dispatch due workflows every minute from your scheduler:

```php
Schedule::command('workflow-builder:dispatch-due')->everyMinute();
```

## Filament Plugin

Register the plugin in your panel provider:

```php
->plugin(\Briefley\WorkflowBuilder\WorkflowBuilderPlugin::make())
```

The plugin registers only the `WorkflowResource` and manages steps/runs via relation managers.
