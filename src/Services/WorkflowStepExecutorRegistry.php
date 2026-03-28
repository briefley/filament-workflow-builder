<?php

namespace Briefley\WorkflowBuilder\Services;

use Briefley\WorkflowBuilder\Contracts\WorkflowStepExecutor;
use RuntimeException;

class WorkflowStepExecutorRegistry
{
    /**
     * @var array<string, WorkflowStepExecutor>
     */
    private array $resolvedExecutors = [];

    public function resolve(string $stepType): WorkflowStepExecutor
    {
        if (isset($this->resolvedExecutors[$stepType])) {
            return $this->resolvedExecutors[$stepType];
        }

        $executors = config('workflow-builder.step_executors', []);

        if (! is_array($executors)) {
            throw new RuntimeException('workflow-builder.step_executors must be an array.');
        }

        $executorClass = $executors[$stepType] ?? null;

        if (! is_string($executorClass) || $executorClass === '') {
            throw new RuntimeException("No workflow step executor is configured for step type [{$stepType}].");
        }

        $executor = app($executorClass);

        if (! $executor instanceof WorkflowStepExecutor) {
            throw new RuntimeException("Configured executor [{$executorClass}] for step type [{$stepType}] must implement WorkflowStepExecutor.");
        }

        $this->resolvedExecutors[$stepType] = $executor;

        return $executor;
    }
}
