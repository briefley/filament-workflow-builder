<?php

namespace Briefley\WorkflowBuilder\Contracts;

use Briefley\WorkflowBuilder\Models\WorkflowRunStep;

interface HandlesStaleRunStepFailure
{
    public function handleStaleFailure(WorkflowRunStep $runStep, string $reason): void;
}
