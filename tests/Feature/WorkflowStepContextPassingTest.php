<?php

namespace Briefley\WorkflowBuilder\Tests\Feature;

use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStepStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowScheduleType;
use Briefley\WorkflowBuilder\Models\Workflow;
use Briefley\WorkflowBuilder\Tests\Fixtures\ConsumePayloadStepExecutor;
use Briefley\WorkflowBuilder\Tests\Fixtures\GeneratePayloadStepExecutor;
use Briefley\WorkflowBuilder\Tests\TestCase;

class WorkflowStepContextPassingTest extends TestCase
{
    public function test_it_passes_meta_from_previous_step_to_context_aware_executor(): void
    {
        config()->set('workflow-builder.step_executors', [
            'generate_payload_step' => GeneratePayloadStepExecutor::class,
            'consume_payload_step' => ConsumePayloadStepExecutor::class,
        ]);

        $workflow = Workflow::query()->create([
            'name' => 'Context Workflow',
            'is_enabled' => true,
            'schedule_type' => WorkflowScheduleType::INTERVAL,
            'schedule_interval_minutes' => 15,
            'next_run_at' => now()->subMinute(),
        ]);

        $workflow->steps()->create([
            'sequence' => 1,
            'step_type' => 'generate_payload_step',
        ]);

        $workflow->steps()->create([
            'sequence' => 2,
            'step_type' => 'consume_payload_step',
        ]);

        $this->artisan('workflow-builder:dispatch-due')->assertSuccessful();

        $workflow->refresh();
        $run = $workflow->runs()->first();

        $this->assertNotNull($run);
        $this->assertSame(WorkflowRunStatus::SUCCEEDED, $run->status);

        $steps = $run->runSteps()->orderBy('sequence')->get();
        $this->assertCount(2, $steps);
        $this->assertSame(WorkflowRunStepStatus::SUCCEEDED, $steps[0]->status);
        $this->assertSame(WorkflowRunStepStatus::SUCCEEDED, $steps[1]->status);
        $this->assertSame('token-from-step-1', $steps[1]->meta['received_token'] ?? null);
    }
}
