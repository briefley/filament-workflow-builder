<?php

namespace Briefley\WorkflowBuilder\Enums;

enum WorkflowRunTriggerSource: string
{
    case SCHEDULER = 'scheduler';
    case MANUAL = 'manual';
}
