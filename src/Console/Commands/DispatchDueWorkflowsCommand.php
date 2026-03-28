<?php

namespace Briefley\WorkflowBuilder\Console\Commands;

use Briefley\WorkflowBuilder\Jobs\DispatchDueWorkflowsJob;
use Illuminate\Console\Command;

class DispatchDueWorkflowsCommand extends Command
{
    protected $signature = 'workflow-builder:dispatch-due';

    protected $description = 'Scan and dispatch due workflows.';

    public function handle(): int
    {
        DispatchDueWorkflowsJob::dispatchSync();

        $this->components->info('Workflow dispatcher scan completed.');

        return self::SUCCESS;
    }
}
