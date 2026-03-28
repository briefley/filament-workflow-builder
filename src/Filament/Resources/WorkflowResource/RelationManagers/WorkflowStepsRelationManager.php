<?php

namespace Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\RelationManagers;

use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkflowStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'Steps';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sequence')
                ->required()
                ->numeric()
                ->minValue(1),
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
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
