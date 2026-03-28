<?php

namespace Briefley\WorkflowBuilder\Services;

use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStepStatus;
use Briefley\WorkflowBuilder\Jobs\RunWorkflowStepJob;
use Briefley\WorkflowBuilder\Models\WorkflowRun;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;
use Illuminate\Support\Facades\DB;

class WorkflowStepDispatcher
{
    public function dispatchNextOrFinalize(WorkflowRun $run, WorkflowRunStep $currentStep): void
    {
        $nextStep = $run->runSteps()
            ->where('sequence', '>', $currentStep->sequence)
            ->orderBy('sequence')
            ->first();

        if ($nextStep instanceof WorkflowRunStep) {
            RunWorkflowStepJob::dispatch($run->id, $nextStep->id, $run->workflow_id)
                ->onQueue((string) config('workflow-builder.queues.steps', 'default'));

            return;
        }

        DB::transaction(function () use ($run, $currentStep): void {
            $run->forceFill([
                'status' => WorkflowRunStatus::SUCCEEDED,
                'finished_at' => now(),
                'current_step_sequence' => $currentStep->sequence,
                'error_message' => null,
            ])->save();

            $run->workflow()->update(['last_run_at' => now()]);
        });
    }

    public function failRun(WorkflowRun $run, WorkflowRunStep $failedStep, string $errorMessage): void
    {
        DB::transaction(function () use ($run, $failedStep, $errorMessage): void {
            $failedStep->forceFill([
                'status' => WorkflowRunStepStatus::FAILED,
                'finished_at' => now(),
                'error_message' => $errorMessage,
            ])->save();

            $run->runSteps()
                ->where('sequence', '>', $failedStep->sequence)
                ->whereIn('status', [
                    WorkflowRunStepStatus::PENDING->value,
                    WorkflowRunStepStatus::RUNNING->value,
                    WorkflowRunStepStatus::WAITING->value,
                ])
                ->update([
                    'status' => WorkflowRunStepStatus::FAILED->value,
                    'finished_at' => now(),
                    'error_message' => 'Not executed because a previous workflow step failed.',
                    'updated_at' => now(),
                ]);

            $run->forceFill([
                'status' => WorkflowRunStatus::FAILED,
                'finished_at' => now(),
                'current_step_sequence' => $failedStep->sequence,
                'error_message' => $errorMessage,
            ])->save();

            $run->workflow()->update(['last_run_at' => now()]);
        });
    }
}
