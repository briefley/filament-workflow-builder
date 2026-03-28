<?php

namespace Briefley\WorkflowBuilder\DTO;

use Briefley\WorkflowBuilder\Models\WorkflowRunStep;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class WorkflowRunStepModalData
{
    public function __construct(
        public readonly int $sequence,
        public readonly string $stepType,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $statusBadgeClass,
        public readonly int $attempt,
        public readonly string $startedAt,
        public readonly string $finishedAt,
        public readonly string $errorMessage,
    ) {}

    public static function fromModel(WorkflowRunStep $runStep): self
    {
        $status = self::normalizeStatus($runStep->status);

        return new self(
            sequence: (int) $runStep->sequence,
            stepType: (string) ($runStep->workflowStep?->step_type ?? '-'),
            status: $status,
            statusLabel: self::statusLabel($status),
            statusBadgeClass: self::statusBadgeClass($status),
            attempt: (int) $runStep->attempt,
            startedAt: self::formatDateTime($runStep->started_at),
            finishedAt: self::formatDateTime($runStep->finished_at),
            errorMessage: Str::limit((string) ($runStep->error_message ?? ''), 300),
        );
    }

    public static function normalizeStatus(mixed $status): string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        if ($status instanceof \UnitEnum) {
            return $status->name;
        }

        return (string) $status;
    }

    public static function statusLabel(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'succeeded' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'running', 'waiting' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'pending' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        };
    }

    public static function formatDateTime(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        return filled($value) ? (string) $value : '-';
    }
}
