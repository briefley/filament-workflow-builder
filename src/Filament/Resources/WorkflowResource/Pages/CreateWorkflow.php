<?php

namespace Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\Pages;

use Briefley\WorkflowBuilder\Enums\WorkflowScheduleType;
use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource;
use Briefley\WorkflowBuilder\Models\Workflow;
use Briefley\WorkflowBuilder\Services\WorkflowScheduleCalculator;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflow extends CreateRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['schedule_type'] = $this->normalizeScheduleType($data['schedule_type'] ?? null);
        $data['schedule_minute'] = null;

        if ($data['schedule_type'] === WorkflowScheduleType::DAILY->value) {
            $data['schedule_interval_minutes'] = null;
        } else {
            $data['schedule_time'] = null;
        }

        if (blank($data['next_run_at'] ?? null) && ($data['is_enabled'] ?? true)) {
            $workflow = new Workflow($data);
            $calculator = app(WorkflowScheduleCalculator::class);
            $data['next_run_at'] = $calculator->nextRunAt($workflow, now());
        }

        return $data;
    }

    private function normalizeScheduleType(mixed $scheduleType): string
    {
        $resolved = (string) $scheduleType;

        if ($resolved === WorkflowScheduleType::DAILY->value) {
            return WorkflowScheduleType::DAILY->value;
        }

        return WorkflowScheduleType::INTERVAL->value;
    }
}
