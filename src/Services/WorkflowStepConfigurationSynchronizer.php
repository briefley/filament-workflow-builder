<?php

namespace Briefley\WorkflowBuilder\Services;

use Briefley\WorkflowBuilder\Models\Workflow;
use Briefley\WorkflowBuilder\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;

class WorkflowStepConfigurationSynchronizer
{
    /**
     * @param  mixed  $stepConfigurations
     */
    public function sync(Workflow $workflow, mixed $stepConfigurations): void
    {
        $normalizedStepConfigurations = $this->normalizeStepConfigurations($stepConfigurations);

        DB::transaction(function () use ($workflow, $normalizedStepConfigurations): void {
            /** @var array<int, WorkflowStep> $existingStepsById */
            $existingStepsById = WorkflowStep::query()
                ->where('workflow_id', $workflow->getKey())
                ->get()
                ->keyBy(static fn (WorkflowStep $step): int => (int) $step->getKey())
                ->all();

            $keptStepIds = [];
            $consumedExistingStepIds = [];

            foreach ($normalizedStepConfigurations as $index => $stepConfiguration) {
                $sequence = $index + 1;
                $stepId = $this->extractStepId($stepConfiguration);
                $existingStep = null;

                if (
                    is_int($stepId)
                    && isset($existingStepsById[$stepId])
                    && ! in_array($stepId, $consumedExistingStepIds, true)
                ) {
                    $existingStep = $existingStepsById[$stepId];
                    $existingStep->forceFill($this->stepAttributes($stepConfiguration, $sequence, $existingStep))->save();

                    $keptStepIds[] = $stepId;
                    $consumedExistingStepIds[] = $stepId;

                    continue;
                }

                $createdStep = $workflow->steps()->create($this->stepAttributes($stepConfiguration, $sequence, $existingStep));

                $keptStepIds[] = (int) $createdStep->getKey();
            }

            $stepDeletionQuery = WorkflowStep::query()->where('workflow_id', $workflow->getKey());

            if ($keptStepIds !== []) {
                $stepDeletionQuery->whereNotIn('id', $keptStepIds);
            }

            $stepDeletionQuery->delete();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeStepConfigurations(mixed $stepConfigurations): array
    {
        if (! is_array($stepConfigurations)) {
            return [];
        }

        return array_values(array_filter(
            $stepConfigurations,
            static fn (mixed $stepConfiguration): bool => is_array($stepConfiguration),
        ));
    }

    private function extractStepId(array $stepConfiguration): ?int
    {
        $stepId = $stepConfiguration['id'] ?? null;

        if (! is_string($stepId) && ! is_int($stepId)) {
            return null;
        }

        if (! is_numeric($stepId)) {
            return null;
        }

        $normalizedStepId = (int) $stepId;

        return $normalizedStepId > 0 ? $normalizedStepId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function stepAttributes(array $stepConfiguration, int $sequence, ?WorkflowStep $existingStep): array
    {
        $stepType = $this->resolveStepType($stepConfiguration, $existingStep);

        return [
            'sequence' => $sequence,
            'step_type' => $stepType,
        ];
    }

    private function resolveStepType(array $stepConfiguration, ?WorkflowStep $existingStep): string
    {
        if (! array_key_exists('step_type', $stepConfiguration)) {
            return (string) ($existingStep?->step_type ?? '');
        }

        return $this->normalizeStepType($stepConfiguration['step_type'])
            ?? (string) ($existingStep?->step_type ?? '');
    }

    private function normalizeStepType(mixed $stepType): ?string
    {
        if (is_string($stepType)) {
            $normalizedStepType = trim($stepType);

            return $normalizedStepType !== '' ? $normalizedStepType : null;
        }

        if (is_int($stepType) || is_float($stepType)) {
            return (string) $stepType;
        }

        return null;
    }

}
