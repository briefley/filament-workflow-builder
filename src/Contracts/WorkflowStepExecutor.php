<?php

namespace Briefley\WorkflowBuilder\Contracts;

use Briefley\WorkflowBuilder\DTO\StepExecutionResult;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;

interface WorkflowStepExecutor
{
    public function execute(WorkflowRunStep $runStep): StepExecutionResult;
}
