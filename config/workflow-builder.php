<?php

return [
    'stale_run_timeout_minutes' => (int) env('WORKFLOW_BUILDER_STALE_RUN_TIMEOUT_MINUTES', 120),

    // Running runs with all steps still pending past this threshold are treated as orphaned.
    'stale_orphan_pending_timeout_minutes' => (int) env('WORKFLOW_BUILDER_STALE_ORPHAN_PENDING_TIMEOUT_MINUTES', 15),

    'polling' => [
        'delay_seconds' => (int) env('WORKFLOW_BUILDER_POLL_DELAY_SECONDS', 60),
    ],

    'queues' => [
        'dispatcher' => env('WORKFLOW_BUILDER_QUEUE_DISPATCHER', 'default'),
        'steps' => env('WORKFLOW_BUILDER_QUEUE_STEPS', 'default'),
    ],

    'jobs' => [
        'steps' => [
            'tries' => (int) env('WORKFLOW_BUILDER_STEP_JOB_TRIES', 5),
            'timeout_seconds' => (int) env('WORKFLOW_BUILDER_STEP_JOB_TIMEOUT_SECONDS', 1800),
            'overlap' => [
                'release_after_seconds' => (int) env('WORKFLOW_BUILDER_OVERLAP_RELEASE_AFTER_SECONDS', 10),
                'workflow_lock_ttl_seconds' => (int) env('WORKFLOW_BUILDER_WORKFLOW_LOCK_TTL_SECONDS', 1860),
                'step_lock_ttl_seconds' => (int) env('WORKFLOW_BUILDER_STEP_LOCK_TTL_SECONDS', 1860),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow jobs (optional UI config)
    |--------------------------------------------------------------------------
    |
    | Optional metadata for step type selection UIs (for example Filament).
    | Supported formats:
    |
    | 1) label map
    |    'my_step_type' => 'My Step Label'
    |
    | 2) structured config
    |    'my_step_type' => [
    |        'label' => 'My Step Label',
    |        'step_type' => 'my_step_type', // optional, defaults to key
    |        'executor' => App\Workflow\Executors\MyStepExecutor::class, // optional
    |    ]
    |
    | Backward compatibility:
    | - Existing "step_executors" remains the runtime source of truth.
    | - If this key is empty, consumers can derive selectable jobs from
    |   "step_executors" to preserve existing behavior.
    |
    */
    'workflow_jobs' => [
        // 'some_step_type' => 'My Step Label',
    ],

    /*
    |--------------------------------------------------------------------------
    | Step executor registry
    |--------------------------------------------------------------------------
    |
    | Map a workflow step type to a class implementing:
    | Briefley\WorkflowBuilder\Contracts\WorkflowStepExecutor
    |
    */
    'step_executors' => [
        // 'some_step_type' => App\Workflow\Executors\SomeStepExecutor::class,
    ],
];
