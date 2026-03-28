<?php

namespace Briefley\WorkflowBuilder\Services;

use Briefley\WorkflowBuilder\Enums\WorkflowScheduleType;
use Briefley\WorkflowBuilder\Models\Workflow;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class WorkflowScheduleCalculator
{
    public function nextRunAt(Workflow $workflow, ?CarbonInterface $from = null): Carbon
    {
        $timezone = (string) config('app.timezone');
        $base = Carbon::instance($from ?? now())->setTimezone($timezone);

        if ($this->isDailySchedule($workflow)) {
            return $this->nextDailyRunAt($workflow, $base);
        }

        $minutes = max(1, (int) $workflow->schedule_interval_minutes);

        return $base->copy()->addMinutes($minutes)->second(0);
    }

    private function isDailySchedule(Workflow $workflow): bool
    {
        $scheduleType = $workflow->schedule_type;

        if ($scheduleType instanceof WorkflowScheduleType) {
            return $scheduleType === WorkflowScheduleType::DAILY;
        }

        return (string) $scheduleType === WorkflowScheduleType::DAILY->value;
    }

    private function nextDailyRunAt(Workflow $workflow, Carbon $base): Carbon
    {
        [$hour, $minute] = $this->parseDailyTime($workflow);

        $candidate = $base->copy()->setTime($hour, $minute, 0);

        if ($candidate->lessThanOrEqualTo($base)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function parseDailyTime(Workflow $workflow): array
    {
        $scheduleTime = $workflow->schedule_time;

        if ($scheduleTime instanceof CarbonInterface) {
            return [(int) $scheduleTime->format('H'), (int) $scheduleTime->format('i')];
        }

        $raw = trim((string) $scheduleTime);

        if ($raw === '') {
            return [0, 0];
        }

        if (str_contains($raw, ' ')) {
            $raw = (string) str($raw)->afterLast(' ');
        }

        if (! preg_match('/^(?<hour>\d{1,2}):(?<minute>\d{1,2})/', $raw, $matches)) {
            return [0, 0];
        }

        $hour = (int) ($matches['hour'] ?? 0);
        $minute = (int) ($matches['minute'] ?? 0);

        return [
            min(23, max(0, $hour)),
            min(59, max(0, $minute)),
        ];
    }
}
