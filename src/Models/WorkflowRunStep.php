<?php

namespace Briefley\WorkflowBuilder\Models;

use Briefley\WorkflowBuilder\Enums\WorkflowRunStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRunStep extends Model
{
    protected $fillable = [
        'workflow_run_id',
        'workflow_step_id',
        'sequence',
        'status',
        'attempt',
        'started_at',
        'finished_at',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'status' => WorkflowRunStepStatus::class,
        'attempt' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta' => 'array',
    ];

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }
}
