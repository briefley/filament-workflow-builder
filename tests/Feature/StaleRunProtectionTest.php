<?php

namespace Briefley\WorkflowBuilder\Tests\Feature;

use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStepStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunTriggerSource;
use Briefley\WorkflowBuilder\Enums\WorkflowScheduleType;
use Briefley\WorkflowBuilder\Models\Workflow;
use Briefley\WorkflowBuilder\Models\WorkflowRun;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;
use Briefley\WorkflowBuilder\Tests\Fixtures\SuccessfulStepExecutor;
use Briefley\WorkflowBuilder\Tests\TestCase;

class StaleRunProtectionTest extends TestCase
{
    public function test_it_fails_stale_orphan_pending_runs(): void
    {
        config()->set('workflow-builder.step_executors', [
            'successful_step' => SuccessfulStepExecutor::class,
        ]);
        config()->set('workflow-builder.stale_run_timeout_minutes', 5);
        config()->set('workflow-builder.stale_orphan_pending_timeout_minutes', 5);

        $workflow = Workflow::query()->create([
            'name' => 'Stale Workflow',
            'is_enabled' => true,
            'schedule_type' => WorkflowScheduleType::INTERVAL,
            'schedule_interval_minutes' => 5,
            'next_run_at' => now()->addHour(),
        ]);

        $step = $workflow->steps()->create([
            'sequence' => 1,
            'step_type' => 'successful_step',
        ]);

        $run = $workflow->runs()->create([
            'status' => WorkflowRunStatus::RUNNING,
            'trigger_source' => WorkflowRunTriggerSource::SCHEDULER,
            'started_at' => now()->subMinutes(20),
            'finished_at' => null,
            'error_message' => null,
        ]);

        $runStep = $run->runSteps()->create([
            'workflow_step_id' => $step->id,
            'sequence' => 1,
            'status' => WorkflowRunStepStatus::PENDING,
            'attempt' => 0,
            'meta' => [],
        ]);

        $staleAt = now()->subMinutes(20);

        WorkflowRun::query()->whereKey($run->id)->update([
            'created_at' => $staleAt,
            'updated_at' => $staleAt,
            'started_at' => $staleAt,
        ]);

        WorkflowRunStep::query()->whereKey($runStep->id)->update([
            'created_at' => $staleAt,
            'updated_at' => $staleAt,
        ]);

        $this->artisan('workflow-builder:dispatch-due')->assertSuccessful();

        $run->refresh();
        $runStep->refresh();

        $this->assertSame(WorkflowRunStatus::FAILED, $run->status);
        $this->assertSame(WorkflowRunStepStatus::FAILED, $runStep->status);
        $this->assertStringContainsString('stale', strtolower((string) $run->error_message));
        $this->assertStringContainsString('stale', strtolower((string) $runStep->error_message));
    }
}
