<?php

namespace Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource\Pages;

use Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkflows extends ListRecords
{
    protected static string $resource = WorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
