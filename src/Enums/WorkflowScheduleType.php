<?php

namespace Briefley\WorkflowBuilder\Enums;

enum WorkflowScheduleType: string
{
    case INTERVAL = 'interval';
    case HOURLY = 'hourly';
    case DAILY = 'daily';
    case WEEKDAYS = 'weekdays';
}
