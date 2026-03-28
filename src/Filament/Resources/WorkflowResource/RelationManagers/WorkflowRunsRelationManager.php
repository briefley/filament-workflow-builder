<?php

namespace Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\RelationManagers;

use Briefley\WorkflowBuilder\DTO\WorkflowRunModalData;
use Briefley\WorkflowBuilder\Enums\WorkflowRunStatus;
use Briefley\WorkflowBuilder\Enums\WorkflowRunTriggerSource;
use Briefley\WorkflowBuilder\Models\WorkflowRun;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class WorkflowRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'runs';

    protected static ?string $title = 'Runs';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('trigger_source')
                    ->badge()
                    ->sortable(),
                TextColumn::make('current_step_sequence')
                    ->label('Current Step')
                    ->sortable(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(80)
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(WorkflowRunStatus::cases())->mapWithKeys(
                        static fn (WorkflowRunStatus $status): array => [$status->value => ucfirst(str_replace('_', ' ', $status->value))]
                    )),
                SelectFilter::make('trigger_source')
                    ->options(collect(WorkflowRunTriggerSource::cases())->mapWithKeys(
                        static fn (WorkflowRunTriggerSource $source): array => [$source->value => ucfirst($source->value)]
                    )),
            ])
            ->recordActions([
                Action::make('view_steps')
                    ->label('Steps')
                    ->icon('heroicon-o-list-bullet')
                    ->modalHeading(static fn (WorkflowRun $record): string => "Run #{$record->id} steps")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('7xl')
                    ->modalContent(static fn (WorkflowRun $record): View => view(
                        'workflow-builder::filament.modals.workflow-run-steps',
                        ['data' => WorkflowRunModalData::fromModel($record)],
                    )),
            ]);
    }
}
