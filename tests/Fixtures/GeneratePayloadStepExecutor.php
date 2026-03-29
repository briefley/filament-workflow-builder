<?php

namespace Briefley\WorkflowBuilder\Tests\Fixtures;

use Briefley\WorkflowBuilder\Contracts\WorkflowStepExecutor;
use Briefley\WorkflowBuilder\DTO\StepExecutionResult;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;

class GeneratePayloadStepExecutor implements WorkflowStepExecutor
{
    public function execute(WorkflowRunStep $runStep): StepExecutionResult
    {
        return StepExecutionResult::succeeded(meta: [
            'token' => 'token-from-step-1',
            'source_run_step_id' => $runStep->id,
        ]);
    }
}
