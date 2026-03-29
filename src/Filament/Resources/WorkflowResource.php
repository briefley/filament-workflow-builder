<?php

namespace Briefley\WorkflowBuilder\Filament\Resources;

use Briefley\WorkflowBuilder\Enums\WorkflowScheduleType;
use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\Pages\CreateWorkflow;
use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\Pages\EditWorkflow;
use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\Pages\ListWorkflows;
use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\RelationManagers\WorkflowRunsRelationManager;
use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\RelationManagers\WorkflowStepsRelationManager;
use Briefley\WorkflowBuilder\Models\Workflow;
use Briefley\WorkflowBuilder\Models\WorkflowStep;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Workflow Builder';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Toggle::make('is_enabled')
                ->label('Enabled')
                ->default(true),
            Select::make('schedule_type')
                ->label('Schedule Mode')
                ->options([
                    WorkflowScheduleType::INTERVAL->value => 'Every N minutes',
                    WorkflowScheduleType::DAILY->value => 'Daily at fixed time',
                ])
                ->default(WorkflowScheduleType::INTERVAL->value)
                ->required()
                ->live(),
            TextInput::make('schedule_interval_minutes')
                ->label('Run Every (minutes)')
                ->numeric()
                ->minValue(1)
                ->required(static fn (Get $get): bool => (string) $get('schedule_type') === WorkflowScheduleType::INTERVAL->value)
                ->visible(static fn (Get $get): bool => (string) $get('schedule_type') === WorkflowScheduleType::INTERVAL->value),
            TimePicker::make('schedule_time')
                ->label('Run Daily At')
                ->withoutSeconds()
                ->required(static fn (Get $get): bool => (string) $get('schedule_type') === WorkflowScheduleType::DAILY->value)
                ->visible(static fn (Get $get): bool => (string) $get('schedule_type') === WorkflowScheduleType::DAILY->value)
                ->helperText('Example: 05:00 runs once per day at 05:00.'),
            DateTimePicker::make('next_run_at')
                ->helperText('Leave empty to auto-schedule from now using the selected schedule mode.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_enabled')
                    ->boolean()
                    ->label('Enabled'),
                TextColumn::make('schedule_summary')
                    ->label('Schedule')
                    ->state(static fn (Workflow $record): string => static::formatScheduleSummary($record)),
                TextColumn::make('next_run_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_run_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_enabled')->label('Enabled'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            WorkflowStepsRelationManager::class,
            WorkflowRunsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflows::route('/'),
            'create' => CreateWorkflow::route('/create'),
            'edit' => EditWorkflow::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getStepTypeOptions(): array
    {
        $options = static::configuredStepTypeOptions();

        $fallbackOptions = collect(config('workflow-builder.step_executors', []))
            ->keys()
            ->merge(
                WorkflowStep::query()
                    ->select('step_type')
                    ->distinct()
                    ->pluck('step_type'),
            )
            ->filter(static fn (mixed $stepType): bool => filled($stepType))
            ->map(static fn (mixed $stepType): string => trim((string) $stepType))
            ->filter(static fn (string $stepType): bool => $stepType !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        foreach ($fallbackOptions as $stepType) {
            $options[$stepType] ??= static::formatStepTypeLabel($stepType);
        }

        return $options;
    }

    public static function formatStepTypeLabel(string $stepType): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $stepType));
    }

    public static function formatScheduleSummary(Workflow $workflow): string
    {
        if ($workflow->schedule_type === WorkflowScheduleType::DAILY) {
            $rawTime = trim((string) ($workflow->schedule_time ?? ''));
            $time = substr($rawTime, 0, 5);

            return $time === '' ? 'Daily at 00:00' : "Daily at {$time}";
        }

        $minutes = max(1, (int) ($workflow->schedule_interval_minutes ?? 1));

        return "Every {$minutes} min";
    }

    /**
     * @return array<string, string>
     */
    protected static function configuredStepTypeOptions(): array
    {
        $configuredJobs = config('workflow-builder.workflow_jobs', []);

        if (! is_array($configuredJobs)) {
            return [];
        }

        $options = [];

        foreach ($configuredJobs as $key => $definition) {
            $stepType = null;
            $label = null;

            if (is_string($key) && $key !== '') {
                $stepType = trim($key);
            }

            if (is_string($definition)) {
                $label = trim($definition);
            } elseif (is_array($definition)) {
                if (is_string($definition['step_type'] ?? null) && trim($definition['step_type']) !== '') {
                    $stepType = trim($definition['step_type']);
                }

                if (is_string($definition['label'] ?? null)) {
                    $label = trim($definition['label']);
                }
            }

            if (! is_string($stepType) || $stepType === '') {
                continue;
            }

            $options[$stepType] = $label !== null && $label !== ''
                ? $label
                : static::formatStepTypeLabel($stepType);
        }

        return $options;
    }
}
