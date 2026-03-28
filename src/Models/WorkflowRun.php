<?php

namespace Briefley\WorkflowBuilder\Models;

use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunTriggerSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRun extends Model
{
    protected $fillable = [
        'workflow_id',
        'status',
        'trigger_source',
        'current_step_sequence',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected $casts = [
        'status' => WorkflowRunStatus::class,
        'trigger_source' => WorkflowRunTriggerSource::class,
        'current_step_sequence' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function runSteps(): HasMany
    {
        return $this->hasMany(WorkflowRunStep::class)->orderBy('sequence');
    }
}
