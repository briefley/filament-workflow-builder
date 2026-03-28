<?php

namespace Briefley\WorkflowBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_id',
        'sequence',
        'step_type',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function runSteps(): HasMany
    {
        return $this->hasMany(WorkflowRunStep::class);
    }
}
