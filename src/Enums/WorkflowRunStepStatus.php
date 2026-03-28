<?php

namespace Briefley\WorkflowBuilder\Enums;

enum WorkflowRunStepStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case WAITING = 'waiting';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::SUCCEEDED, self::FAILED], true);
    }
}
