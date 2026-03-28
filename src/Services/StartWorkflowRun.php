<?php

namespace Briefley\WorkflowBuilder\Services;

use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStepStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunTriggerSource;
use Briefley\WorkflowBuilder\Jobs\RunWorkflowStepJob;
use Briefley\WorkflowBuilder\Models\Workflow;
use Briefley\WorkflowBuilder\Models\WorkflowRun;
use Illuminate\Support\Facades\DB;

class StartWorkflowRun
{
    public function start(
        Workflow $workflow,
        WorkflowRunTriggerSource $triggerSource,
    ): ?WorkflowRun {
        $firstRunStepId = null;
        $run = null;

        DB::transaction(function () use ($workflow, $triggerSource, &$run, &$firstRunStepId): void {
            $lockedWorkflow = Workflow::query()->lockForUpdate()->find($workflow->id);

            if (! $lockedWorkflow instanceof Workflow || $lockedWorkflow->hasActiveRun()) {
                return;
            }

            /** @var WorkflowRun $run */
            $run = $lockedWorkflow->runs()->create([
                'status' => WorkflowRunStatus::RUNNING,
                'trigger_source' => $triggerSource,
                'started_at' => now(),
                'finished_at' => null,
                'error_message' => null,
            ]);

            /** @var \Illuminate\Database\Eloquent\Collection<int, \Briefley\WorkflowBuilder\Models\WorkflowStep> $steps */
            $steps = $lockedWorkflow->steps()->orderBy('sequence')->get();

            foreach ($steps as $step) {
                /** @var \Briefley\WorkflowBuilder\Models\WorkflowRunStep $runStep */
                $runStep = $run->runSteps()->create([
                    'workflow_step_id' => $step->id,
                    'sequence' => $step->sequence,
                    'status' => WorkflowRunStepStatus::PENDING,
                    'attempt' => 0,
                    'meta' => [],
                ]);

                if (! is_int($firstRunStepId)) {
                    $firstRunStepId = $runStep->id;
                }
            }

            if (! is_int($firstRunStepId)) {
                $run->forceFill([
                    'status' => WorkflowRunStatus::SUCCEEDED,
                    'finished_at' => now(),
                    'current_step_sequence' => null,
                    'error_message' => null,
                ])->save();
            }

            $lockedWorkflow->forceFill(['last_run_at' => now()])->save();
        });

        if (! $run instanceof WorkflowRun) {
            return null;
        }

        if (is_int($firstRunStepId)) {
            RunWorkflowStepJob::dispatch($run->id, $firstRunStepId, $workflow->id)
                ->onQueue((string) config('workflow-builder.queues.steps', 'default'));
        }

        return $run->fresh(['runSteps']);
    }
}
