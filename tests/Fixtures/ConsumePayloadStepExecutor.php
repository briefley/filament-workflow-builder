<?php

namespace Briefley\WorkflowBuilder\Tests\Fixtures;

use Briefley\WorkflowBuilder\Contracts\ContextAwareWorkflowStepExecutor;
use Briefley\WorkflowBuilder\DTO\StepExecutionResult;
use Briefley\WorkflowBuilder\DTO\WorkflowStepExecutionContext;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;

class ConsumePayloadStepExecutor implements ContextAwareWorkflowStepExecutor
{
    public function execute(WorkflowRunStep $runStep): StepExecutionResult
    {
        return StepExecutionResult::failed('Missing execution context.');
    }

    public function executeWithContext(
        WorkflowRunStep $runStep,
        WorkflowStepExecutionContext $context,
    ): StepExecutionResult {
        $token = $context->latestValueForStepType('generate_payload_step', 'token');

        if (! is_string($token) || $token === '') {
            return StepExecutionResult::failed('Token not found in context.');
        }

        return StepExecutionResult::succeeded(meta: [
            'received_token' => $token,
            'consumer_run_step_id' => $runStep->id,
        ]);
    }
}
