<?php

namespace Briefley\WorkflowBuilder\Tests\Feature;

use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStepStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunTriggerSource;
use Briefley\WorkflowBuilder\Enums\WorkflowScheduleType;
use Briefley\WorkflowBuilder\Models\Workflow;
use Briefley\WorkflowBuilder\Tests\Fixtures\SuccessfulStepExecutor;
use Briefley\WorkflowBuilder\Tests\TestCase;

class DispatchDueWorkflowsCommandTest extends TestCase
{
    public function test_it_dispatches_due_workflow_and_executes_step(): void
    {
        config()->set('workflow-builder.step_executors', [
            'successful_step' => SuccessfulStepExecutor::class,
        ]);

        $workflow = Workflow::query()->create([
            'name' => 'Orders Sync',
            'is_enabled' => true,
            'schedule_type' => WorkflowScheduleType::INTERVAL,
            'schedule_interval_minutes' => 5,
            'next_run_at' => now()->subMinute(),
        ]);

        $workflow->steps()->create([
            'sequence' => 1,
            'step_type' => 'successful_step',
        ]);

        $this->artisan('workflow-builder:dispatch-due')->assertSuccessful();

        $workflow->refresh();

        $run = $workflow->runs()->first();
        $this->assertNotNull($run);
        $this->assertSame(WorkflowRunStatus::SUCCEEDED, $run->status);

        $runStep = $run->runSteps()->first();
        $this->assertNotNull($runStep);
        $this->assertSame(WorkflowRunStepStatus::SUCCEEDED, $runStep->status);
        $this->assertSame('ok', $runStep->meta['result'] ?? null);

        $this->assertNotNull($workflow->next_run_at);
        $this->assertTrue($workflow->next_run_at->greaterThan(now()->subMinute()));
    }

    public function test_it_marks_overlap_runs_as_skipped_when_a_run_is_active(): void
    {
        config()->set('workflow-builder.step_executors', [
            'successful_step' => SuccessfulStepExecutor::class,
        ]);

        $workflow = Workflow::query()->create([
            'name' => 'Billing Workflow',
            'is_enabled' => true,
            'schedule_type' => WorkflowScheduleType::INTERVAL,
            'schedule_interval_minutes' => 10,
            'next_run_at' => now()->subMinute(),
        ]);

        $workflow->steps()->create([
            'sequence' => 1,
            'step_type' => 'successful_step',
        ]);

        $workflow->runs()->create([
            'status' => WorkflowRunStatus::RUNNING,
            'trigger_source' => WorkflowRunTriggerSource::SCHEDULER,
            'started_at' => now()->subMinute(),
        ]);

        $this->artisan('workflow-builder:dispatch-due')->assertSuccessful();

        $latestRun = $workflow->runs()->first();
        $this->assertNotNull($latestRun);
        $this->assertSame(WorkflowRunStatus::SKIPPED_OVERLAP, $latestRun->status);
        $this->assertSame('Skipped due to an active workflow run.', $latestRun->error_message);
    }
}
