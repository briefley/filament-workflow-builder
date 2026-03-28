<?php

namespace Briefley\WorkflowBuilder\Enums;

enum WorkflowRunStatus: string
{
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case SKIPPED_OVERLAP = 'skipped_overlap';

    public function isTerminal(): bool
    {
        return $this !== self::RUNNING;
    }
}
