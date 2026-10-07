@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $user = auth()->user();
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2">
        @if ($canCreate)
            <x-ui.button class="h-11 md:h-9" :href="route('projects.tasks.create', ['project' => $project->project_number])" wire:navigate data-detail-modal><x-lucide-plus /> {{ __('Add task') }}</x-ui.button>
            <x-ui.button variant="outline" class="h-11 md:h-9" x-on:click="$dispatch('open-sheet-apply-template')"><x-lucide-layout-template /> {{ __('Apply template') }}</x-ui.button>
        @endif
        <x-ui.segmented-control name="project-task-view" wire:model.live="view" :value="$view" class="ms-auto h-11 md:h-9"
            :options="[['value' => 'list', 'label' => __('List')], ['value' => 'board', 'label' => __('Board')], ['value' => 'timeline', 'label' => __('Timeline')]]" />
    </div>

    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
        <x-ui.select native wire:model.live="assignee" class="{{ $input }}" :aria-label="__('Assignee')">
            <option value="">{{ __('Any assignee') }}</option>
            @foreach ($assignees as $employee)
                <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
            @endforeach
        </x-ui.select>
        <x-lookup-select table="project_phases" :placeholder="__('Any phase')" wire:model.live="phase" :aria-label="__('Phase')" />
        <x-ui.select native wire:model.live="status" class="{{ $input }}" :aria-label="__('Status')">
            <option value="">{{ __('Any status') }}</option>
            @foreach ($statuses as $taskStatus)
                <option value="{{ $taskStatus->id }}">{{ $taskStatus->name }}</option>
            @endforeach
        </x-ui.select>
    </div>

    @if ($view === 'board')
        <div class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <div class="flex gap-3">
                @foreach ($columns as $column)
                    <section class="flex w-72 shrink-0 flex-col gap-2 rounded-xl bg-muted/50 p-2" wire:key="column-{{ $column['status']->id }}">
                        <header class="flex items-center justify-between px-1 text-sm">
                            <span class="font-medium">{{ $column['status']->name }}</span>
                            <span class="text-muted-foreground tabular-nums">{{ $column['tasks']->count() }}</span>
                        </header>
                        <div class="flex min-h-24 flex-col gap-2" wire:sort="moveTask" wire:sort:group="project-tasks" wire:sort:group-id="{{ $column['status']->id }}">
                            @foreach ($column['tasks'] as $task)
                                <x-ui.card class="cursor-grab gap-1 p-3 active:cursor-grabbing" wire:key="board-task-{{ $task->id }}" wire:sort:item="{{ $task->id }}">
                                    <a href="{{ route('projects.tasks.show', $task) }}" wire:navigate data-detail-modal class="font-medium hover:underline">{{ $task->title }}</a>
                                    <span class="font-mono text-sm text-muted-foreground">{{ $task->task_number }}</span>
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <span @class(['tabular-nums', 'text-destructive' => $task->isOverdue()])>{{ $task->due_date?->format('d M') ?? '—' }}</span>
                                        <span class="truncate text-muted-foreground">{{ $task->assignee?->full_name ?? __('Unassigned') }}</span>
                                    </div>
                                </x-ui.card>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @elseif ($view === 'timeline')
        @if ($range)
            <div class="hidden flex-col gap-4 md:flex">
                @foreach ($phases as $phaseName => $group)
                    <section class="flex flex-col gap-1" wire:key="timeline-{{ $loop->index }}">
                        <h3 class="text-sm font-medium">{{ $phaseName }}</h3>
                        @foreach ($group as $task)
                            @php
                                $start = $task->start_date ?? $task->due_date;
                                $end = $task->due_date ?? $task->start_date;
                                $offset = $start ? $range['from']->diffInDays($start) : 0;
                                $span = $start && $end ? max(1, $start->diffInDays($end) + 1) : 1;
                            @endphp
                            <div class="grid grid-cols-[14rem_1fr] items-center gap-3" wire:key="bar-{{ $task->id }}">
                                <a href="{{ route('projects.tasks.show', $task) }}" wire:navigate data-detail-modal class="truncate text-sm hover:underline">{{ $task->title }}</a>
                                <div class="relative h-6 rounded bg-muted">
                                    @if ($start)
                                        <div @class(['absolute inset-y-0 rounded', 'bg-success/70' => $task->status->is_done, 'bg-destructive/70' => ! $task->status->is_done && $task->isOverdue(), 'bg-primary/70' => ! $task->status->is_done && ! $task->isOverdue()])
                                            style="left: {{ $offset * 100 / $range['days'] }}%; width: {{ max(1, $span * 100 / $range['days']) }}%"
                                            title="{{ $start->format('d M') }} – {{ $end?->format('d M') }}"></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endforeach
                <p class="text-sm text-muted-foreground tabular-nums">{{ $range['from']->format('d-M-Y') }} – {{ $range['from']->copy()->addDays($range['days'] - 1)->format('d-M-Y') }}</p>
            </div>
            <div class="flex flex-col gap-4 md:hidden">
                @foreach ($phases as $phaseName => $group)
                    <section class="flex flex-col gap-2" wire:key="m-timeline-{{ $loop->index }}">
                        <h3 class="text-sm font-medium">{{ $phaseName }}</h3>
                        @foreach ($group as $task)
                            <x-ui.item variant="outline" class="min-h-14 active:bg-accent" :href="route('projects.tasks.show', $task)" wire:navigate wire:key="m-bar-{{ $task->id }}">
                                <x-ui.item-content class="min-w-0">
                                    <x-ui.item-title class="text-sm"><span class="truncate">{{ $task->title }}</span></x-ui.item-title>
                                    <x-ui.item-description class="text-sm tabular-nums">{{ $task->start_date?->format('d M') ?? '—' }} → {{ $task->due_date?->format('d M') ?? '—' }}</x-ui.item-description>
                                </x-ui.item-content>
                                <x-ui.badge :tone="$task->status->color ?? 'neutral'" class="shrink-0 text-sm">{{ $task->status->name }}</x-ui.badge>
                            </x-ui.item>
                        @endforeach
                    </section>
                @endforeach
            </div>
        @else
            <p class="py-8 text-center text-sm text-muted-foreground">{{ __('No dated tasks yet.') }}</p>
        @endif
    @else
        <x-ui.item-group class="gap-2">
            @forelse ($tasks as $task)
                <x-ui.item variant="outline" class="min-h-16 active:bg-accent" :href="route('projects.tasks.show', $task)" wire:navigate data-detail-modal wire:key="task-row-{{ $task->id }}">
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base md:text-sm">
                            @if ($task->parent_id)<x-lucide-corner-down-right class="size-4 text-muted-foreground" />@endif
                            <span class="truncate">{{ $task->title }}</span>
                            @if ($task->is_important)<x-lucide-star class="size-4 shrink-0 fill-warning text-warning" />@endif
                        </x-ui.item-title>
                        <x-ui.item-description class="text-sm">
                            <span class="font-mono">{{ $task->task_number }}</span> · {{ $task->type->name }} · {{ $task->assignee?->full_name ?? __('Unassigned') }}
                            @if ($task->checklist_count > 0) · {{ $task->checklist_done_count }}/{{ $task->checklist_count }} @endif
                        </x-ui.item-description>
                    </x-ui.item-content>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-ui.badge :tone="$task->status->color ?? 'neutral'" class="text-sm">{{ $task->status->name }}</x-ui.badge>
                        <span @class(['text-sm tabular-nums', 'text-destructive' => $task->isOverdue(), 'text-muted-foreground' => ! $task->isOverdue()])>{{ $task->due_date?->format('d-M-Y') ?? '—' }}</span>
                    </div>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-8 text-center text-sm text-muted-foreground">{{ __('No tasks yet.') }}</p>
            @endforelse
        </x-ui.item-group>
    @endif

    @if ($canCreate)
        <x-shell.sheet id="apply-template" :title="__('Apply task template')" :description="__('Create the template’s tasks on :number.', ['number' => $project->project_number])">
            <form id="apply-template-form" wire:submit="applyTemplate" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="template-pick">{{ __('Template') }} *</x-ui.field-label>
                    <x-ui.select native id="template-pick" wire:model="templateId" class="{{ $input }}">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('templateId')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="template-start">{{ __('Start date') }}</x-ui.field-label>
                    <x-ui.input type="date" id="template-start" wire:model="templateStart" class="{{ $input }}" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-apply-template')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="apply-template-form">{{ __('Create tasks') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endif
</div>
