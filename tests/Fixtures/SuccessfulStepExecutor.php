<?php

namespace Briefley\WorkflowBuilder\Tests\Fixtures;

use Briefley\WorkflowBuilder\Contracts\WorkflowStepExecutor;
use Briefley\WorkflowBuilder\DTO\StepExecutionResult;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;

class SuccessfulStepExecutor implements WorkflowStepExecutor
{
    public function execute(WorkflowRunStep $runStep): StepExecutionResult
    {
        return StepExecutionResult::succeeded(meta: [
            'result' => 'ok',
            'run_step_id' => $runStep->id,
        ]);
    }
}
