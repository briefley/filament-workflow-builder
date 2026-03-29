<?php

namespace Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\RelationManagers;

use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource;
use Briefley\WorkflowBuilder\Models\WorkflowStep;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class WorkflowStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'Steps';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('sequence')
                ->required()
                ->numeric()
                ->minValue(1)
                ->default(fn (): int => $this->nextSequence())
                ->unique(
                    table: WorkflowStep::class,
                    column: 'sequence',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule->where(
                        'workflow_id',
                        (int) $this->getOwnerRecord()->getKey(),
                    ),
                ),
            Select::make('step_type')
                ->label('Job type')
                ->options(static fn (): array => WorkflowResource::getStepTypeOptions())
                ->required()
                ->searchable()
                ->preload()
                ->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sequence')
            ->reorderable('sequence')
            ->columns([
                TextColumn::make('sequence')
                    ->sortable(),
                TextColumn::make('step_type')
                    ->badge()
                    ->formatStateUsing(static fn (?string $state): string => WorkflowResource::formatStepTypeLabel((string) $state))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private function nextSequence(): int
    {
        $maxSequence = $this->getRelationship()->max('sequence');

        return max(1, ((int) $maxSequence) + 1);
    }
}
