<?php

namespace Briefley\WorkflowBuilder\Models;

use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowScheduleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workflow extends Model
{
    protected $fillable = [
        'name',
        'is_enabled',
        'schedule_type',
        'schedule_interval_minutes',
        'schedule_time',
        'next_run_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'schedule_type' => WorkflowScheduleType::class,
        'schedule_interval_minutes' => 'integer',
        'schedule_minute' => 'integer',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sequence');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class)->latest('id');
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(WorkflowRun::class)->latestOfMany();
    }

    public function hasActiveRun(): bool
    {
        return $this->runs()
            ->where('status', WorkflowRunStatus::RUNNING)
            ->exists();
    }
}
