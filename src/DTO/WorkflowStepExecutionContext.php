<?php

namespace Briefley\WorkflowBuilder\DTO;

use Briefley\WorkflowBuilder\Models\WorkflowRunStep;

final class WorkflowStepExecutionContext
{
    /**
     * @param  list<array{
     *     sequence:int,
     *     step_type:string,
     *     external_reference:?string,
     *     meta:array<string, mixed>
     * }>  $completedSteps
     */
    public function __construct(
        public readonly array $completedSteps,
    ) {}

    /**
     * @param  iterable<WorkflowRunStep>  $runSteps
     */
    public static function fromRunSteps(iterable $runSteps): self
    {
        $completedSteps = [];

        foreach ($runSteps as $runStep) {
            $stepType = trim((string) ($runStep->workflowStep?->step_type ?? ''));
            $meta = is_array($runStep->meta) ? $runStep->meta : [];
            $externalReference = $runStep->external_reference;

            $completedSteps[] = [
                'sequence' => (int) $runStep->sequence,
                'step_type' => $stepType,
                'external_reference' => is_string($externalReference) && $externalReference !== ''
                    ? $externalReference
                    : null,
                'meta' => $meta,
            ];
        }

        return new self($completedSteps);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestMetaForStepType(string $stepType): ?array
    {
        $needle = trim($stepType);

        if ($needle === '') {
            return null;
        }

        $latest = null;

        foreach ($this->completedSteps as $step) {
            if ((string) ($step['step_type'] ?? '') !== $needle) {
                continue;
            }

            $latest = is_array($step['meta'] ?? null) ? $step['meta'] : [];
        }

        return $latest;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metaForSequence(int $sequence): ?array
    {
        foreach ($this->completedSteps as $step) {
            if ((int) ($step['sequence'] ?? 0) !== $sequence) {
                continue;
            }

            return is_array($step['meta'] ?? null) ? $step['meta'] : [];
        }

        return null;
    }

    public function latestValueForStepType(
        string $stepType,
        string $key,
        mixed $default = null,
    ): mixed {
        $meta = $this->latestMetaForStepType($stepType);

        if (! is_array($meta) || $meta === []) {
            return $default;
        }

        return data_get($meta, $key, $default);
    }

    public function valueForSequence(
        int $sequence,
        string $key,
        mixed $default = null,
    ): mixed {
        $meta = $this->metaForSequence($sequence);

        if (! is_array($meta) || $meta === []) {
            return $default;
        }

        return data_get($meta, $key, $default);
    }
}

