@php
    /** @var \Briefley\WorkflowBuilder\DTO\WorkflowRunModalData $data */
@endphp

<div class="space-y-4">
    <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/60">
        <div class="mb-3 flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium {{ $data->statusBadgeClass }}">
                {{ $data->statusLabel }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Run #{{ $data->id }}</span>
        </div>

        <dl class="grid grid-cols-1 gap-3 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Started</dt>
                <dd class="mt-1 text-sm text-gray-800 dark:text-gray-100">{{ $data->startedAt }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Finished</dt>
                <dd class="mt-1 text-sm text-gray-800 dark:text-gray-100">{{ $data->finishedAt }}</dd>
            </div>
        </dl>

        @if ($data->errorMessage !== '')
            <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                {{ $data->errorMessage }}
            </div>
        @endif
    </section>

    <ol class="space-y-3">
        @forelse ($data->steps as $step)
            <li class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-md bg-gray-100 px-2 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            #{{ $step->sequence }}
                        </span>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $step->stepType }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium {{ $step->statusBadgeClass }}">
                        {{ $step->statusLabel }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 gap-3 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-3">
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Attempt</dt>
                        <dd class="mt-1 text-sm text-gray-800 dark:text-gray-100">{{ $step->attempt }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Started</dt>
                        <dd class="mt-1 text-sm text-gray-800 dark:text-gray-100">{{ $step->startedAt }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Finished</dt>
                        <dd class="mt-1 text-sm text-gray-800 dark:text-gray-100">{{ $step->finishedAt }}</dd>
                    </div>
                </dl>

                @if ($step->errorMessage !== '')
                    <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                        {{ $step->errorMessage }}
                    </div>
                @endif
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                No steps were created for this run.
            </li>
        @endforelse
    </ol>
</div>
