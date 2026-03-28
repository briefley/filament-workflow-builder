<?php

namespace Briefley\WorkflowBuilder\Jobs;

use Briefley\WorkflowBuilder\Contracts\ContextAwareWorkflowStepExecutor;
use Briefley\WorkflowBuilder\Contracts\WorkflowStepExecutor;
use Briefley\WorkflowBuilder\DTO\StepExecutionResult;
use Briefley\WorkflowBuilder\DTO\WorkflowStepExecutionContext;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStepStatus;
use Briefley\WorkflowBuilder\Models\WorkflowRun;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;
use Briefley\WorkflowBuilder\Services\WorkflowStepDispatcher;
use Briefley\WorkflowBuilder\Services\WorkflowStepExecutorRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RunWorkflowStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 1800;

    private int $overlapReleaseAfterSeconds;

    private int $workflowOverlapLockTtlSeconds;

    private int $stepOverlapLockTtlSeconds;

    public function __construct(
        private readonly int $runId,
        private readonly int $runStepId,
        private readonly int $workflowId,
    ) {
        $this->tries = $this->configInt([
            'workflow-builder.jobs.steps.tries',
            'workflow-builder.jobs.step.tries',
        ], 5);
        $this->timeout = $this->configInt([
            'workflow-builder.jobs.steps.timeout',
            'workflow-builder.jobs.steps.timeout_seconds',
            'workflow-builder.jobs.step.timeout',
            'workflow-builder.jobs.step.timeout_seconds',
        ], 1800);

        $this->overlapReleaseAfterSeconds = $this->configInt([
            'workflow-builder.jobs.steps.overlap.release_after_seconds',
            'workflow-builder.jobs.steps.overlap.release_seconds',
            'workflow-builder.overlap.release_after_seconds',
            'workflow-builder.overlap.release_seconds',
        ], 10);

        $defaultLockTtl = max(60, $this->timeout + 60);

        $this->workflowOverlapLockTtlSeconds = $this->configInt([
            'workflow-builder.jobs.steps.overlap.workflow_lock_ttl_seconds',
            'workflow-builder.jobs.steps.overlap.workflow_expire_after_seconds',
            'workflow-builder.jobs.steps.overlap.lock_ttl_seconds',
            'workflow-builder.overlap.workflow_lock_ttl_seconds',
            'workflow-builder.overlap.workflow_expire_after_seconds',
            'workflow-builder.overlap.lock_ttl_seconds',
        ], $defaultLockTtl);

        $this->stepOverlapLockTtlSeconds = $this->configInt([
            'workflow-builder.jobs.steps.overlap.step_lock_ttl_seconds',
            'workflow-builder.jobs.steps.overlap.step_expire_after_seconds',
            'workflow-builder.jobs.steps.overlap.lock_ttl_seconds',
            'workflow-builder.overlap.step_lock_ttl_seconds',
            'workflow-builder.overlap.step_expire_after_seconds',
            'workflow-builder.overlap.lock_ttl_seconds',
        ], $defaultLockTtl);

        $this->onQueue((string) config('workflow-builder.queues.steps', 'default'));
    }

    public function runId(): int
    {
        return $this->runId;
    }

    public function runStepId(): int
    {
        return $this->runStepId;
    }

    public function workflowId(): int
    {
        return $this->workflowId;
    }

    public function middleware(): array
    {
        return [
            $this->overlapMiddleware($this->workflowOverlapKey(), $this->workflowOverlapLockTtlSeconds),
            $this->overlapMiddleware($this->stepOverlapKey(), $this->stepOverlapLockTtlSeconds),
        ];
    }

    public function handle(
        WorkflowStepExecutorRegistry $executorRegistry,
        WorkflowStepDispatcher $stepDispatcher,
    ): void {
        $runStep = WorkflowRunStep::query()
            ->with(['workflowRun', 'workflowStep'])
            ->find($this->runStepId);

        if (! $runStep instanceof WorkflowRunStep) {
            return;
        }

        $run = $runStep->workflowRun;

        if (! $run instanceof WorkflowRun || $run->id !== $this->runId) {
            return;
        }

        if ($run->status !== WorkflowRunStatus::RUNNING) {
            return;
        }

        if ($runStep->status->isTerminal()) {
            return;
        }

        if (! $this->isRunnableBySequence($runStep)) {
            return;
        }

        $this->markRunning($run, $runStep);

        try {
            $stepType = $runStep->workflowStep?->step_type;

            if (! is_string($stepType) || $stepType === '') {
                $stepDispatcher->failRun($run, $runStep, 'Workflow step type is invalid.');

                return;
            }

            $executor = $executorRegistry->resolve($stepType);
            $result = $this->executeStep($executor, $runStep);

            $this->applyResult($run, $runStep, $result, $stepDispatcher);
        } catch (\Throwable $exception) {
            $stepDispatcher->failRun($run, $runStep, $exception->getMessage());
        }
    }

    private function applyResult(
        WorkflowRun $run,
        WorkflowRunStep $runStep,
        StepExecutionResult $result,
        WorkflowStepDispatcher $stepDispatcher,
    ): void {
        $meta = is_array($runStep->meta) ? $runStep->meta : [];

        $runStep->forceFill([
            'external_reference' => $result->externalReference ?? $runStep->external_reference,
            'meta' => array_merge($meta, $result->meta),
        ]);

        if ($result->status === WorkflowRunStepStatus::WAITING) {
            $runStep->forceFill([
                'status' => WorkflowRunStepStatus::WAITING,
                'error_message' => null,
                'finished_at' => null,
            ])->save();

            if ($result->shouldPoll) {
                RunWorkflowStepJob::dispatch($run->id, $runStep->id, $this->workflowId)
                    ->delay(now()->addSeconds(max(1, (int) config('workflow-builder.polling.delay_seconds', 60))))
                    ->onQueue((string) config('workflow-builder.queues.steps', 'default'));
            }

            return;
        }

        if ($result->status === WorkflowRunStepStatus::SUCCEEDED) {
            $runStep->forceFill([
                'status' => WorkflowRunStepStatus::SUCCEEDED,
                'error_message' => null,
                'finished_at' => now(),
            ])->save();

            $stepDispatcher->dispatchNextOrFinalize($run, $runStep);

            return;
        }

        $runStep->forceFill([
            'status' => WorkflowRunStepStatus::FAILED,
            'error_message' => $result->errorMessage,
            'finished_at' => now(),
        ])->save();

        $stepDispatcher->failRun($run, $runStep, $result->errorMessage ?? 'Workflow step failed.');
    }

    private function executeStep(WorkflowStepExecutor $executor, WorkflowRunStep $runStep): StepExecutionResult
    {
        if (! $executor instanceof ContextAwareWorkflowStepExecutor) {
            return $executor->execute($runStep);
        }

        return $executor->executeWithContext($runStep, $this->buildExecutionContext($runStep));
    }

    private function buildExecutionContext(WorkflowRunStep $runStep): WorkflowStepExecutionContext
    {
        $previousSucceededRunSteps = $runStep->workflowRun
            ->runSteps()
            ->with('workflowStep')
            ->where('sequence', '<', $runStep->sequence)
            ->where('status', WorkflowRunStepStatus::SUCCEEDED->value)
            ->orderBy('sequence')
            ->get();

        return WorkflowStepExecutionContext::fromRunSteps($previousSucceededRunSteps);
    }

    private function markRunning(WorkflowRun $run, WorkflowRunStep $runStep): void
    {
        $run->forceFill([
            'current_step_sequence' => $runStep->sequence,
            'updated_at' => now(),
        ])->save();

        $runStep->forceFill([
            'status' => WorkflowRunStepStatus::RUNNING,
            'attempt' => max(1, $runStep->attempt + 1),
            'started_at' => $runStep->started_at ?? now(),
            'finished_at' => null,
        ])->save();
    }

    private function isRunnableBySequence(WorkflowRunStep $runStep): bool
    {
        return ! $runStep->workflowRun
            ->runSteps()
            ->where('sequence', '<', $runStep->sequence)
            ->where('status', '!=', WorkflowRunStepStatus::SUCCEEDED->value)
            ->exists();
    }

    public function failed(?\Throwable $exception): void
    {
        $runStep = WorkflowRunStep::find($this->runStepId);

        if (! $runStep instanceof WorkflowRunStep || $runStep->status->isTerminal()) {
            return;
        }

        $run = $runStep->workflowRun;

        if (! $run instanceof WorkflowRun || $run->status !== WorkflowRunStatus::RUNNING) {
            return;
        }

        app(WorkflowStepDispatcher::class)->failRun(
            $run,
            $runStep,
            $exception?->getMessage() ?? 'Worker process exited unexpectedly.',
        );
    }

    private function stepOverlapKey(): string
    {
        return "workflow-builder:workflow-step:{$this->runId}:{$this->runStepId}";
    }

    private function workflowOverlapKey(): string
    {
        return "workflow-builder:workflow-run:{$this->workflowId}";
    }

    private function overlapMiddleware(string $key, int $lockTtlSeconds): WithoutOverlapping
    {
        return (new WithoutOverlapping($key))
            ->releaseAfter($this->overlapReleaseAfterSeconds)
            ->expireAfter($lockTtlSeconds);
    }

    /**
     * @param  list<string>  $keys
     */
    private function configInt(array $keys, int $default, int $minimum = 1): int
    {
        foreach ($keys as $key) {
            $value = config($key);

            if ($this->isNumeric($value)) {
                return max($minimum, (int) $value);
            }
        }

        return max($minimum, $default);
    }

    private function isNumeric(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }
}
