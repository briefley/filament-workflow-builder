<?php

namespace Briefley\WorkflowBuilder\DTO;

use Briefley\WorkflowBuilder\Enums\WorkflowRunStepStatus;

class StepExecutionResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly WorkflowRunStepStatus $status,
        public readonly ?string $externalReference = null,
        public readonly ?string $errorMessage = null,
        public readonly array $meta = [],
        public readonly bool $shouldPoll = false,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function succeeded(?string $externalReference = null, array $meta = []): self
    {
        return new self(
            status: WorkflowRunStepStatus::SUCCEEDED,
            externalReference: $externalReference,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function waiting(?string $externalReference = null, array $meta = []): self
    {
        return new self(
            status: WorkflowRunStepStatus::WAITING,
            externalReference: $externalReference,
            meta: $meta,
            shouldPoll: true,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function failed(
        string $errorMessage,
        ?string $externalReference = null,
        array $meta = [],
    ): self {
        return new self(
            status: WorkflowRunStepStatus::FAILED,
            externalReference: $externalReference,
            errorMessage: $errorMessage,
            meta: $meta,
        );
    }
}
