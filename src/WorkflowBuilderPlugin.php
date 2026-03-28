<?php

namespace Briefley\WorkflowBuilder;

use Filament\Contracts\Plugin;
use Filament\Panel;

class WorkflowBuilderPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'workflow-builder';
    }

    public function register(Panel $panel): void
    {
        $resources = array_values(array_filter([
            \Briefley\WorkflowBuilder\Filament\Resources\WorkflowResource::class,
        ], static fn (string $resource): bool => class_exists($resource)));

        if ($resources !== []) {
            $panel->resources($resources);
        }
    }

    public function boot(Panel $panel): void
    {
    }
}
