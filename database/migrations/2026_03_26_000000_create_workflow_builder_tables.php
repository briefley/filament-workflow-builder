<?php

use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStepStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->string('schedule_type');
            $table->unsignedInteger('schedule_interval_minutes')->nullable();
            $table->unsignedTinyInteger('schedule_minute')->nullable();
            $table->time('schedule_time')->nullable();
            $table->string('external_reference')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'next_run_at'], 'workflow_builder_workflows_due_idx');
            $table->index('external_reference', 'workflow_builder_workflows_external_ref_idx');
            $table->index(['schedule_type', 'is_enabled'], 'workflow_builder_workflows_schedule_idx');
        });

        Schema::create('workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('step_type');
            $table->string('external_reference')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'sequence'], 'workflow_builder_steps_workflow_sequence_unique');
            $table->index(['workflow_id', 'step_type'], 'workflow_builder_steps_workflow_type_idx');
            $table->index('external_reference', 'workflow_builder_steps_external_ref_idx');
        });

        Schema::create('workflow_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->string('status')->default(WorkflowRunStatus::RUNNING->value);
            $table->string('trigger_source');
            $table->unsignedInteger('current_step_sequence')->nullable();
            $table->string('external_reference')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'status'], 'workflow_builder_runs_workflow_status_idx');
            $table->index(['status', 'updated_at'], 'workflow_builder_runs_status_updated_idx');
            $table->index('external_reference', 'workflow_builder_runs_external_ref_idx');
        });

        Schema::create('workflow_run_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained('workflow_runs')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default(WorkflowRunStepStatus::PENDING->value);
            $table->unsignedInteger('attempt')->default(0);
            $table->string('external_reference')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['workflow_run_id', 'workflow_step_id'], 'workflow_builder_run_steps_run_step_unique');
            $table->index(['workflow_run_id', 'sequence'], 'workflow_builder_run_steps_run_sequence_idx');
            $table->index(['status', 'updated_at'], 'workflow_builder_run_steps_status_updated_idx');
            $table->index('external_reference', 'workflow_builder_run_steps_external_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_run_steps');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
    }
};
