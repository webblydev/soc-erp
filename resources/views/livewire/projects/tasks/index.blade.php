@php
    $user = auth()->user();
    $presets = [
        'mine' => __('My tasks'), 'assigned_by_me' => __('Assigned by me'), 'all' => __('All (task record)'),
        'overdue' => __('Overdue'), 'completed' => __('Completed'), 'archived' => __('Archived'),
    ];
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2">
        <div class="-mx-4 flex flex-1 gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
            @foreach ($presets as $key => $label)
                <x-ui.button :variant="$preset === $key ? 'default' : 'outline'" size="sm" class="h-11 shrink-0 md:h-8" wire:click="applyPreset('{{ $key }}')">{{ $label }}</x-ui.button>
            @endforeach
        </div>
        <x-ui.segmented-control name="task-view" wire:model.live="view" :value="$view" class="hidden md:inline-flex"
            :options="[['value' => 'list', 'label' => __('List'), 'icon' => 'list'], ['value' => 'board', 'label' => __('Board'), 'icon' => 'kanban']]" />
    </div>

    <x-shell.list
        :search-placeholder="__('Search task number, title or file no.')"
        :create-url="$user->can('create', \App\Modules\Projects\Models\Task::class) ? route('projects.tasks.create') : null"
        :create-label="__('New task')"
        :exportable="true"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
        :filter-columns="3"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-status">{{ __('Status') }}</x-ui.field-label>
                <x-ui.select native id="filter-status" wire:model.live="filters.status" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Any status') }}</option>
                    @foreach ($statuses as $taskStatus)
                        <option value="{{ $taskStatus->id }}">{{ $taskStatus->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-assignee">{{ __('Assignee') }}</x-ui.field-label>
                <x-ui.select native id="filter-assignee" wire:model.live="filters.assignee" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Anyone') }}</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-project">{{ __('Project') }}</x-ui.field-label>
                <x-ui.select native id="filter-project" wire:model.live="filters.project" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Any project') }}</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->project_number }} · {{ $project->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-type">{{ __('Type') }}</x-ui.field-label>
                <x-lookup-select table="task_types" :placeholder="__('All types')" id="filter-type" wire:model.live="filters.type" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-phase">{{ __('Phase') }}</x-ui.field-label>
                <x-lookup-select table="project_phases" :placeholder="__('Any phase')" id="filter-phase" wire:model.live="filters.phase" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-priority">{{ __('Priority') }}</x-ui.field-label>
                <x-lookup-select table="task_priorities" :placeholder="__('Any priority')" id="filter-priority" wire:model.live="filters.priority" />
            </x-ui.field>
            <div class="grid grid-cols-2 gap-2">
                <x-ui.field>
                    <x-ui.field-label for="filter-due-from">{{ __('Due from') }}</x-ui.field-label>
                    <x-ui.input type="date" id="filter-due-from" wire:model.live="filters.due_from" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="filter-due-to">{{ __('Due to') }}</x-ui.field-label>
                    <x-ui.input type="date" id="filter-due-to" wire:model.live="filters.due_to" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
            </div>
            <x-ui.field orientation="horizontal" class="min-h-11 items-center md:min-h-0">
                <x-ui.checkbox native id="filter-important" wire:model.live="filters.important" value="1" />
                <x-ui.field-label for="filter-important" class="font-normal">{{ __('Important only') }}</x-ui.field-label>
            </x-ui.field>
        </x-slot:filters>

        <x-slot:desktop>
            @if ($view === 'board')
                <div class="flex gap-3 overflow-x-auto pb-2">
                    @foreach ($columns as $column)
                        <section class="flex w-72 shrink-0 flex-col gap-2 rounded-xl bg-muted/50 p-2" wire:key="board-column-{{ $column['status']->id }}">
                            <header class="flex items-center justify-between px-1 text-sm">
                                <span class="font-medium">{{ $column['status']->name }}</span>
                                <span class="tabular-nums text-muted-foreground">{{ $column['count'] }}</span>
                            </header>
                            <div class="flex min-h-24 flex-col gap-2" wire:sort="moveTask" wire:sort:group="tasks" wire:sort:group-id="{{ $column['status']->id }}">
                                @foreach ($column['tasks'] as $task)
                                    <x-ui.card class="cursor-grab gap-1 p-3 active:cursor-grabbing" wire:key="board-card-{{ $task->id }}" wire:sort:item="{{ $task->id }}">
                                        <a data-detail-modal href="{{ route('projects.tasks.show', $task) }}" wire:navigate class="font-medium hover:underline">{{ $task->title }}</a>
                                        <span class="text-sm text-muted-foreground"><span class="font-mono">{{ $task->task_number }}</span>@if ($task->project) · {{ $task->project->project_number }}@endif</span>
                                        <div class="flex items-center justify-between gap-2 text-sm">
                                            <span @class(['tabular-nums', 'text-destructive' => $task->isOverdue()])>{{ $task->due_date?->format('d M') ?? '—' }}</span>
                                            <span class="truncate text-muted-foreground">{{ $task->assignee?->full_name ?? __('Unassigned') }}</span>
                                        </div>
                                    </x-ui.card>
                                @endforeach
                            </div>
                            @if ($column['count'] > count($column['tasks']))
                                <p class="px-1 text-sm text-muted-foreground">{{ __('+ :n more — use the list view', ['n' => $column['count'] - count($column['tasks'])]) }}</p>
                            @endif
                        </section>
                    @endforeach
                </div>
            @else
                <x-shell.bulk-bar :exportable="true" :deletable="$user->can('projects.tasks.delete')">
                    @can('projects.tasks.assign')
                        <x-ui.select native wire:model="bulkAssigneeId" class="h-8 w-48" :aria-label="__('Reassign to')">
                            <option value="">{{ __('Reassign to…') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.button size="sm" variant="outline" wire:click="bulkReassign">{{ __('Reassign') }}</x-ui.button>
                    @endcan
                    <x-ui.select native wire:model="bulkStatusId" class="h-8 w-44" :aria-label="__('New status')">
                        <option value="">{{ __('Status…') }}</option>
                        @foreach ($statuses as $taskStatus)
                            <option value="{{ $taskStatus->id }}">{{ $taskStatus->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.button size="sm" variant="outline" wire:click="bulkChangeStatus">{{ __('Change status') }}</x-ui.button>
                    <x-ui.input type="date" wire:model="bulkDueDate" class="h-8 w-40" :aria-label="__('New due date')" />
                    <x-ui.button size="sm" variant="outline" wire:click="bulkSetDueDate">{{ __('Set due date') }}</x-ui.button>
                    @can('projects.tasks.archive')
                        <x-ui.button size="sm" variant="outline" wire:click="bulkArchive">{{ $preset === 'archived' ? __('Unarchive') : __('Archive') }}</x-ui.button>
                    @endcan
                </x-shell.bulk-bar>

                <div class="overflow-x-auto">
                    <x-ui.table variant="bordered">
                        <x-ui.table-header>
                            <x-ui.table-row>
                                <x-shell.select-all :ids="$rows->pluck('id')" />
                                <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                                <x-ui.table-head><x-shell.sort-header key="number" :label="__('Task #')" :$sort :$direction /></x-ui.table-head>
                                <x-ui.table-head>{{ __('File no.') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Customer') }}</x-ui.table-head>
                                <x-ui.table-head><x-shell.sort-header key="entry" :label="__('Entry date')" :$sort :$direction /></x-ui.table-head>
                                <x-ui.table-head>{{ __('Project') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Project type') }}</x-ui.table-head>
                                <x-ui.table-head><x-shell.sort-header key="title" :label="__('Title')" :$sort :$direction /></x-ui.table-head>
                                <x-ui.table-head>{{ __('Assignee') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Support officer') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Assigned by') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Priority') }}</x-ui.table-head>
                                <x-ui.table-head><x-shell.sort-header key="due" :label="__('Due')" :$sort :$direction /></x-ui.table-head>
                                <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                                <x-ui.table-head><x-shell.sort-header key="completed" :label="__('Completed')" :$sort :$direction /></x-ui.table-head>
                            </x-ui.table-row>
                        </x-ui.table-header>
                        <x-ui.table-body>
                            @forelse ($rows as $task)
                                <x-ui.table-row wire:key="task-{{ $task->id }}">
                                    <x-shell.select-row :id="$task->id" :label="$task->title" />
                                    <x-shell.row-menu>
                                        <x-shell.row-menu-item icon="eye" data-detail-modal :href="route('projects.tasks.show', $task)">{{ __('View') }}</x-shell.row-menu-item>
                                        @can('update', $task)
                                            <x-shell.row-menu-item icon="pencil" data-detail-modal :href="route('projects.tasks.edit', $task)">{{ __('Edit') }}</x-shell.row-menu-item>
                                        @endcan
                                        @can('delete', $task)
                                            <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $task->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $task->task_number]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                                        @endcan
                                    </x-shell.row-menu>
                                    <x-ui.table-cell class="font-mono text-sm whitespace-nowrap">
                                        <a data-detail-modal href="{{ route('projects.tasks.show', $task) }}" wire:navigate class="hover:underline">{{ $task->task_number }}</a>
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>{{ $task->file_number ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell class="max-w-40 truncate">{{ $task->project?->customer?->name ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell class="tabular-nums whitespace-nowrap">{{ $date($task->created_at) }}</x-ui.table-cell>
                                    <x-ui.table-cell class="font-mono text-sm whitespace-nowrap">
                                        @if ($task->project)
                                            <a href="{{ route('projects.projects.show', $task->project) }}" wire:navigate class="hover:underline">{{ $task->project->project_number }}</a>
                                        @else
                                            —
                                        @endif
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>{{ $task->project?->type?->name ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell class="max-w-64 font-medium">
                                        <span class="flex items-center gap-1">
                                            <span class="truncate">{{ $task->title }}</span>
                                            @if ($task->is_important)<x-lucide-star class="size-4 shrink-0 fill-warning text-warning" />@endif
                                        </span>
                                    </x-ui.table-cell>
                                    <x-ui.table-cell class="whitespace-nowrap">{{ $task->assignee?->full_name ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell class="whitespace-nowrap">{{ $task->supportOfficer?->full_name ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell class="whitespace-nowrap">{{ $task->assigner->name }}</x-ui.table-cell>
                                    <x-ui.table-cell><x-ui.badge :tone="$task->priority->color ?? 'neutral'">{{ $task->priority->name }}</x-ui.badge></x-ui.table-cell>
                                    <x-ui.table-cell @class(['tabular-nums whitespace-nowrap', 'font-medium text-destructive' => $task->isOverdue()])>{{ $date($task->due_date) }}</x-ui.table-cell>
                                    <x-ui.table-cell><x-ui.badge :tone="$task->status->color ?? 'neutral'">{{ $task->status->name }}</x-ui.badge></x-ui.table-cell>
                                    <x-ui.table-cell class="tabular-nums whitespace-nowrap">{{ $task->completed_at ? $task->completed_at->format('d-M-Y').' · '.$task->completer?->name : '—' }}</x-ui.table-cell>
                                </x-ui.table-row>
                            @empty
                                <x-ui.table-row>
                                    <x-ui.table-cell colspan="16" class="py-10 text-center text-muted-foreground">{{ __('No tasks found.') }}</x-ui.table-cell>
                                </x-ui.table-row>
                            @endforelse
                        </x-ui.table-body>
                    </x-ui.table>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                    <x-ui.select native wire:model.live="perPage" class="w-36" :aria-label="__('Rows per page')">
                        @foreach (static::PER_PAGE_OPTIONS as $option)
                            <option value="{{ $option }}">{{ __(':count per page', ['count' => $option]) }}</option>
                        @endforeach
                    </x-ui.select>
                    {{ $rows->links() }}
                </div>
            @endif
        </x-slot:desktop>

        <x-slot:mobile>
            <div class="-mx-4 flex gap-2 overflow-x-auto px-4">
                <x-ui.button size="sm" :variant="($filters['status'] ?? '') === '' ? 'default' : 'outline'" class="h-11 shrink-0" wire:click="$set('filters.status', '')">{{ __('Any status') }}</x-ui.button>
                @foreach ($statuses as $taskStatus)
                    <x-ui.button size="sm" :variant="($filters['status'] ?? '') === (string) $taskStatus->id ? 'default' : 'outline'" class="h-11 shrink-0" wire:click="$set('filters.status', '{{ $taskStatus->id }}')">{{ $taskStatus->name }}</x-ui.button>
                @endforeach
            </div>
            @forelse ($mobileRows as $task)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-task-{{ $task->id }}" :href="route('projects.tasks.show', $task)" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base">
                            <span class="truncate">{{ $task->title }}</span>
                            @if ($task->is_important)<x-lucide-star class="size-4 shrink-0 fill-warning text-warning" />@endif
                        </x-ui.item-title>
                        <x-ui.item-description class="truncate text-sm">
                            <span class="font-mono">{{ $task->task_number }}</span>@if ($task->project) · {{ $task->project->project_number }}@endif · {{ $task->assignee?->full_name ?? __('Unassigned') }}
                        </x-ui.item-description>
                    </x-ui.item-content>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-ui.badge :tone="$task->status->color ?? 'neutral'" class="text-sm">{{ $task->status->name }}</x-ui.badge>
                        <span @class(['text-sm tabular-nums', 'text-destructive' => $task->isOverdue(), 'text-muted-foreground' => ! $task->isOverdue()])>{{ $task->due_date?->format('d M') ?? '—' }}</span>
                    </div>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No tasks found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
