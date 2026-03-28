<?php

namespace Briefley\WorkflowBuilder\DTO;

use Briefley\WorkflowBuilder\Models\WorkflowRun;

final class WorkflowRunModalData
{
    /**
     * @param  list<WorkflowRunStepModalData>  $steps
     */
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $statusBadgeClass,
        public readonly string $startedAt,
        public readonly string $finishedAt,
        public readonly string $errorMessage,
        public readonly array $steps,
    ) {}

    public static function fromModel(WorkflowRun $run): self
    {
        $run->loadMissing(['runSteps.workflowStep']);

        $status = WorkflowRunStepModalData::normalizeStatus($run->status);

        return new self(
            id: (int) $run->id,
            status: $status,
            statusLabel: WorkflowRunStepModalData::statusLabel($status),
            statusBadgeClass: WorkflowRunStepModalData::statusBadgeClass($status),
            startedAt: WorkflowRunStepModalData::formatDateTime($run->started_at),
            finishedAt: WorkflowRunStepModalData::formatDateTime($run->finished_at),
            errorMessage: (string) ($run->error_message ?? ''),
            steps: $run->runSteps
                ->sortBy('sequence')
                ->map(static fn ($runStep): WorkflowRunStepModalData => WorkflowRunStepModalData::fromModel($runStep))
                ->values()
                ->all(),
        );
    }
}
