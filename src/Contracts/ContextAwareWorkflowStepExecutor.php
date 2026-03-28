<?php

namespace Briefley\WorkflowBuilder\Contracts;

use Briefley\WorkflowBuilder\DTO\StepExecutionResult;
use Briefley\WorkflowBuilder\DTO\WorkflowStepExecutionContext;
use Briefley\WorkflowBuilder\Models\WorkflowRunStep;

interface ContextAwareWorkflowStepExecutor extends WorkflowStepExecutor
{
    public function executeWithContext(
        WorkflowRunStep $runStep,
        WorkflowStepExecutionContext $context,
    ): StepExecutionResult;
}

