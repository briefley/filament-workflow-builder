<?php

namespace Briefley\WorkflowBuilder\Tests\Feature;

use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource;
use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\RelationManagers\WorkflowRunsRelationManager;
use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\RelationManagers\WorkflowStepsRelationManager;
use Briefley\WorkflowBuilder\Models\Workflow;
use Filament\Forms\Form;
use Illuminate\Database\QueryException;
use ReflectionMethod;
use Briefley\WorkflowBuilder\Tests\TestCase;

class FilamentSmokeTest extends TestCase
{
    public function test_workflow_resource_exposes_only_steps_and_runs_relations(): void
    {
        $relations = WorkflowResource::getRelations();

        $this->assertSame([
            WorkflowStepsRelationManager::class,
            WorkflowRunsRelationManager::class,
        ], $relations);
    }

    public function test_workflow_resource_and_relation_manager_use_form_api(): void
    {
        $resourceFormSignature = new ReflectionMethod(WorkflowResource::class, 'form');
        $resourceFormParameterType = $resourceFormSignature->getParameters()[0]->getType()?->getName();

        $relationManagerFormSignature = new ReflectionMethod(WorkflowStepsRelationManager::class, 'form');
        $relationManagerFormParameterType = $relationManagerFormSignature->getParameters()[0]->getType()?->getName();

        $this->assertSame(Form::class, $resourceFormParameterType);
        $this->assertSame(Form::class, $relationManagerFormParameterType);
    }

    public function test_workflow_step_sequence_is_unique_per_workflow(): void
    {
        $workflow = Workflow::query()->create([
            'name' => 'Unique Sequence Workflow',
            'is_enabled' => true,
            'schedule_type' => 'interval',
            'schedule_interval_minutes' => 5,
            'next_run_at' => now()->addMinute(),
        ]);

        $workflow->steps()->create([
            'sequence' => 1,
            'step_type' => 'first_step',
        ]);

        $this->expectException(QueryException::class);

        $workflow->steps()->create([
            'sequence' => 1,
            'step_type' => 'duplicate_step',
        ]);
    }
}
