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

## Compatibility Matrix

| Package line | Filament | Laravel | PHP | Status |
| --- | --- | --- | --- | --- |
| `^1.0` | `^5.0` | `^11.0 \| ^12.0 \| ^13.0` | `^8.2` | Primary line |
| `^0.4` | `^4.0` | `^11.28 \| ^12.0 \| ^13.0` | `^8.2` | Legacy compatibility line |
| `^0.3` | `^3.3` | `^10.45 \| ^11.0 \| ^12.0` | `^8.1 \| ^8.2` | Legacy compatibility line |

## Installation

1. Require the package for your Filament major.

```bash
composer require briefley/filament-workflow-builder:^1.0 # Filament v5
composer require briefley/filament-workflow-builder:^0.4 # Filament v4
composer require briefley/filament-workflow-builder:^0.3 # Filament v3
```

2. Publish config and migrations.

```bash
php artisan vendor:publish --tag=workflow-builder-config
php artisan vendor:publish --tag=workflow-builder-migrations
php artisan migrate
```

## Maintenance Policy

- `^1.x` (Filament v5) is the primary feature line.
- `^0.4.x` (Filament v4) and `^0.3.x` (Filament v3) are compatibility maintenance lines.
- New features are developed on `^1.x` first, then selectively backported when safe.

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

## Build Your Own Workflow Jobs

### 1. Create a step executor

Each workflow "job" is a step type mapped to an executor class.

```php
<?php

namespace App\Workflow\Executors;

use Briefley\WorkflowBuilder\Contracts\WorkflowStepExecutor;
use Briefley\WorkflowBuilder\DTO\StepExecutionResult;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;
use Illuminate\Support\Facades\Log;

class SendReportStepExecutor implements WorkflowStepExecutor
{
    public function execute(WorkflowRunStep $runStep): StepExecutionResult
    {
        // Run your business logic here (API call, file export, DB work, etc).
        Log::info('Report step executed', [
            'workflow_run_id' => $runStep->workflow_run_id,
            'workflow_run_step_id' => $runStep->id,
        ]);

        return StepExecutionResult::succeeded(meta: [
            'report_id' => 123,
        ]);
    }
}
```

### 1.1 Pass data from previous steps (optional)

If your job needs outputs from earlier jobs in the same workflow run, implement:

- `Briefley\WorkflowBuilder\Contracts\ContextAwareWorkflowStepExecutor`

You will receive `WorkflowStepExecutionContext` containing succeeded previous step outputs (`meta`).

```php
<?php

use Briefley\WorkflowBuilder\Contracts\ContextAwareWorkflowStepExecutor;
use Briefley\WorkflowBuilder\DTO\StepExecutionResult;
use Briefley\WorkflowBuilder\DTO\WorkflowStepExecutionContext;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;

class SendEmailsFromCsvStepExecutor implements ContextAwareWorkflowStepExecutor
{
    public function execute(WorkflowRunStep $runStep): StepExecutionResult
    {
        // Backward-compatible fallback when context is not available.
        return StepExecutionResult::failed('Missing context.');
    }

    public function executeWithContext(
        WorkflowRunStep $runStep,
        WorkflowStepExecutionContext $context,
    ): StepExecutionResult {
        $csvPath = $context->latestValueForStepType('generate_fake_users_csv_job', 'csv_path');

        if (! is_string($csvPath) || $csvPath === '') {
            return StepExecutionResult::failed('CSV path not found.');
        }

        // Continue with your step logic...
        return StepExecutionResult::succeeded();
    }
}
```

Helpful context methods:

- `latestMetaForStepType(string $stepType): ?array`
- `latestValueForStepType(string $stepType, string $key, mixed $default = null): mixed`
- `metaForSequence(int $sequence): ?array`
- `valueForSequence(int $sequence, string $key, mixed $default = null): mixed`

### 2. Return one of the supported step results

Each step executor must implement:

- `Briefley\WorkflowBuilder\Contracts\WorkflowStepExecutor`

and return `Briefley\WorkflowBuilder\DTO\StepExecutionResult` from `execute()`:

- `StepExecutionResult::succeeded(...)` for completed steps.
- `StepExecutionResult::failed('reason')` to fail the run.
- `StepExecutionResult::waiting(...)` for async polling-style steps.

### 2.1 Production reliability tips

When implementing real jobs, these two patterns help avoid common production failures:

- Make retryable steps idempotent.
  Persist processed item identifiers (for example email addresses, external IDs, or row hashes) in step `meta`, and skip already-processed items on retry.
- Avoid `dev`-only runtime dependencies in executors.
  If your executor uses optional helpers/libraries (for example Faker), provide a fallback path so production installs with `--no-dev` do not crash.

### 3. Register your step type in config

Add your label and executor to `config/workflow-builder.php`:

```php
return [
    'workflow_jobs' => [
        'send_report_job' => [
            'label' => 'Send Report Job',
        ],
    ],

    'step_executors' => [
        'send_report_job' => App\Workflow\Executors\SendReportStepExecutor::class,
    ],
];
```

The `step_executors` key is the runtime source of truth.  
The `workflow_jobs` key is used for friendly labels in UI selectors.

### 4. Add the step in Filament

1. Open **Workflows**.
2. Create or edit a workflow.
3. In the **Steps** relation manager, add a row with your step type.
4. Order steps by sequence.

### 5. Run scheduler + queue worker

The package dispatches due workflows from the scheduler, and steps execute in queue jobs.

```bash
php artisan schedule:work
php artisan queue:work
```

If you use Horizon, run Horizon instead of `queue:work`.

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

## Testing

Run the package test suite:

```bash
composer test
```
