<?php

namespace Briefley\WorkflowBuilder\Jobs;

use Briefley\WorkflowBuilder\Contracts\HandlesStaleRunStepFailure;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStepStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunTriggerSource;
use Briefley\WorkflowBuilder\Models\Workflow;
use Briefley\WorkflowBuilder\Models\WorkflowRun;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;
use Briefley\WorkflowBuilder\Services\StartWorkflowRun;
use Briefley\WorkflowBuilder\Services\WorkflowScheduleCalculator;
use Briefley\WorkflowBuilder\Services\WorkflowStepExecutorRegistry;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DispatchDueWorkflowsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const STALE_TIMEOUT_REASON = 'Run auto-failed due to stale timeout.';

    private const STALE_ORPHAN_PENDING_REASON = 'Run auto-failed due to stale pending timeout.';

    public function __construct()
    {
        $this->onQueue((string) config('workflow-builder.queues.dispatcher', 'default'));
    }

    public function handle(
        WorkflowScheduleCalculator $scheduleCalculator,
        StartWorkflowRun $startWorkflowRun,
        WorkflowStepExecutorRegistry $executorRegistry,
    ): void {
        $this->failStaleRuns($executorRegistry);

        Workflow::query()
            ->where('is_enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->chunkById(100, function (\Illuminate\Database\Eloquent\Collection $workflows) use ($scheduleCalculator, $startWorkflowRun): void {
                /** @var \Illuminate\Database\Eloquent\Collection<int, Workflow> $workflows */
                foreach ($workflows as $workflow) {
                    if ($workflow->hasActiveRun()) {
                        $this->createSkippedOverlapRun($workflow);
                        $this->advanceNextRunAt($workflow, $scheduleCalculator);

                        continue;
                    }

                    $run = $startWorkflowRun->start($workflow, WorkflowRunTriggerSource::SCHEDULER);

                    if ($run === null) {
                        $this->createSkippedOverlapRun($workflow);
                    }

                    $this->advanceNextRunAt($workflow, $scheduleCalculator);
                }
            });
    }

    private function createSkippedOverlapRun(Workflow $workflow): void
    {
        $workflow->runs()->create([
            'status' => WorkflowRunStatus::SKIPPED_OVERLAP,
            'trigger_source' => WorkflowRunTriggerSource::SCHEDULER,
            'started_at' => now(),
            'finished_at' => now(),
            'error_message' => 'Skipped due to an active workflow run.',
        ]);
    }

    private function advanceNextRunAt(Workflow $workflow, WorkflowScheduleCalculator $calculator): void
    {
        if (! $workflow->is_enabled) {
            $workflow->next_run_at = null;
            $workflow->save();

            return;
        }

        $workflow->next_run_at = $calculator->nextRunAt($workflow, now());
        $workflow->save();
    }

    private function failStaleRuns(WorkflowStepExecutorRegistry $executorRegistry): void
    {
        $staleMinutes = $this->configInt([
            'workflow-builder.stale_run_timeout_minutes',
        ], 120);

        $orphanPendingMinutes = $this->configInt([
            'workflow-builder.stale_orphan_pending_timeout_minutes',
            'workflow-builder.orphan_pending_timeout_minutes',
            'workflow-builder.orphan_pending_run_timeout_minutes',
        ], $staleMinutes);

        $staleThreshold = now()->subMinutes($staleMinutes);
        $orphanPendingThreshold = now()->subMinutes($orphanPendingMinutes);

        WorkflowRun::query()
            ->where('status', WorkflowRunStatus::RUNNING)
            ->with('runSteps')
            ->chunkById(100, function (\Illuminate\Database\Eloquent\Collection $runs) use ($staleThreshold, $orphanPendingThreshold, $executorRegistry): void {
                /** @var \Illuminate\Database\Eloquent\Collection<int, WorkflowRun> $runs */
                foreach ($runs as $run) {
                    $reason = $this->resolveStaleFailureReason($run, $staleThreshold, $orphanPendingThreshold);

                    if (! is_string($reason)) {
                        continue;
                    }

                    $staleStepIds = $this->failRunAsStale($run, $reason);
                    $this->notifyStaleStepFailureHooks($staleStepIds, $reason, $executorRegistry);
                }
            });
    }

    private function resolveStaleFailureReason(
        WorkflowRun $run,
        CarbonInterface $staleThreshold,
        CarbonInterface $orphanPendingThreshold,
    ): ?string {
        if ($this->isOrphanPendingRun($run, $orphanPendingThreshold)) {
            return self::STALE_ORPHAN_PENDING_REASON;
        }

        $lastActivity = $this->resolveLastActivityAt($run);

        if (! $lastActivity instanceof CarbonInterface || $lastActivity->greaterThan($staleThreshold)) {
            return null;
        }

        return self::STALE_TIMEOUT_REASON;
    }

    /**
     * @return list<int>
     */
    private function failRunAsStale(WorkflowRun $run, string $reason): array
    {
        $failedAt = now();
        $staleStepIds = [];

        DB::transaction(function () use ($run, $reason, $failedAt, &$staleStepIds): void {
            $staleStepIds = $run->runSteps()
                ->whereIn('status', $this->staleFailableStepStatuses())
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            if ($staleStepIds !== []) {
                $run->runSteps()
                    ->whereIn('id', $staleStepIds)
                    ->update([
                        'status' => WorkflowRunStepStatus::FAILED->value,
                        'finished_at' => $failedAt,
                        'error_message' => $reason,
                        'updated_at' => $failedAt,
                    ]);
            }

            $run->forceFill([
                'status' => WorkflowRunStatus::FAILED,
                'finished_at' => $failedAt,
                'error_message' => $reason,
            ])->save();

            $run->workflow()->update(['last_run_at' => $failedAt]);
        });

        return $staleStepIds;
    }

    /**
     * @param  list<int>  $runStepIds
     */
    private function notifyStaleStepFailureHooks(
        array $runStepIds,
        string $reason,
        WorkflowStepExecutorRegistry $executorRegistry,
    ): void {
        if ($runStepIds === []) {
            return;
        }

        $runSteps = WorkflowRunStep::query()
            ->with('workflowStep')
            ->whereIn('id', $runStepIds)
            ->orderBy('sequence')
            ->get();

        /** @var \Illuminate\Database\Eloquent\Collection<int, WorkflowRunStep> $runSteps */
        foreach ($runSteps as $runStep) {
            $this->notifyStaleStepFailureHook($runStep, $reason, $executorRegistry);
        }
    }

    private function notifyStaleStepFailureHook(
        WorkflowRunStep $runStep,
        string $reason,
        WorkflowStepExecutorRegistry $executorRegistry,
    ): void {
        $stepType = $runStep->workflowStep?->step_type;

        if (! is_string($stepType) || $stepType === '') {
            return;
        }

        try {
            $executor = $executorRegistry->resolve($stepType);
        } catch (\Throwable) {
            return;
        }

        if (! $executor instanceof HandlesStaleRunStepFailure) {
            return;
        }

        try {
            $executor->handleStaleFailure($runStep, $reason);
        } catch (\Throwable) {
            // Stale cleanup hook is best-effort and must not block stale run recovery.
        }
    }

    private function isOrphanPendingRun(WorkflowRun $run, CarbonInterface $threshold): bool
    {
        if ($run->runSteps->isEmpty()) {
            return false;
        }

        $allPending = $run->runSteps->every(function (WorkflowRunStep $runStep): bool {
            return $this->runStepStatusValue($runStep) === WorkflowRunStepStatus::PENDING->value;
        });

        if (! $allPending) {
            return false;
        }

        if ($run->started_at instanceof CarbonInterface) {
            return $run->started_at->lessThanOrEqualTo($threshold);
        }

        if ($run->created_at instanceof CarbonInterface) {
            return $run->created_at->lessThanOrEqualTo($threshold);
        }

        return false;
    }

    private function runStepStatusValue(WorkflowRunStep $runStep): string
    {
        if ($runStep->status instanceof WorkflowRunStepStatus) {
            return $runStep->status->value;
        }

        return (string) $runStep->status;
    }

    /**
     * @return list<string>
     */
    private function staleFailableStepStatuses(): array
    {
        return [
            WorkflowRunStepStatus::PENDING->value,
            WorkflowRunStepStatus::RUNNING->value,
            WorkflowRunStepStatus::WAITING->value,
        ];
    }

    private function resolveLastActivityAt(WorkflowRun $run): ?CarbonInterface
    {
        $stepUpdatedAt = $run->runSteps()->max('updated_at');

        $timestamps = [];

        if ($run->updated_at instanceof CarbonInterface) {
            $timestamps[] = $run->updated_at;
        }

        if ($run->started_at instanceof CarbonInterface) {
            $timestamps[] = $run->started_at;
        }

        if ($stepUpdatedAt instanceof CarbonInterface) {
            $timestamps[] = $stepUpdatedAt;
        }

        if (is_string($stepUpdatedAt)) {
            $timestamps[] = Carbon::parse($stepUpdatedAt);
        }

        if ($timestamps === []) {
            return null;
        }

        /** @var CarbonInterface $latest */
        $latest = collect($timestamps)
            ->sortByDesc(static fn (CarbonInterface $timestamp): int => $timestamp->getTimestamp())
            ->first();

        return $latest;
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
