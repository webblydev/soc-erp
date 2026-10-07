@php
    $user = auth()->user();
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
    $labels = [
        'IN_PROGRESS' => [__('Start'), 'play'], 'REVIEW' => [__('Submit for review'), 'send'], 'DONE' => [__('Complete'), 'circle-check'],
        'BLOCKED' => [__('Block'), 'octagon-x'], 'CANCELLED' => [__('Cancel'), 'ban'], 'TODO' => [__('Restore'), 'rotate-ccw'],
    ];
    $label = function ($status) use ($labels, $task) {
        if ($status->code === 'IN_PROGRESS' && $task->status->code === 'REVIEW') {
            return [__('Reject review'), 'rotate-ccw'];
        }
        if ($status->code === 'IN_PROGRESS' && $task->status->is_done) {
            return [__('Reopen'), 'rotate-ccw'];
        }
        if ($status->code === 'IN_PROGRESS' && $task->status->code === 'BLOCKED') {
            return [__('Unblock'), 'play'];
        }

        return $labels[$status->code] ?? [$status->name, 'arrow-right'];
    };
    $tabs = ['details' => __('Details'), 'comments' => __('Comments'), 'time' => __('Time'), 'documents' => __('Documents'), 'history' => __('History')];
    $primary = $targets->first(fn ($status) => in_array($status->code, ['IN_PROGRESS', 'REVIEW', 'DONE'], true));
@endphp

<x-slot:actions>
    <x-ui.button variant="ghost" size="icon" class="size-11" x-on:click="$dispatch('open-sheet-task-actions')" :aria-label="__('Task actions')">
        <x-lucide-ellipsis-vertical class="size-5" />
    </x-ui.button>
</x-slot:actions>

<div class="flex flex-col gap-6 pb-24 md:pb-0">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-col gap-1">
            <span class="font-mono text-sm text-muted-foreground">{{ $task->task_number }}@if ($task->project) · {{ $task->project->project_number }}@endif</span>
            <h1 class="flex items-center gap-2 text-xl font-semibold tracking-tight md:text-2xl">
                {{ $task->title }}
                @if ($task->is_important)<x-lucide-star class="size-5 shrink-0 fill-warning text-warning" />@endif
            </h1>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-ui.badge :tone="$task->status->color ?? 'neutral'" class="text-sm">{{ $task->status->name }}</x-ui.badge>
                <x-ui.badge :tone="$task->priority->color ?? 'neutral'" class="text-sm">{{ $task->priority->name }}</x-ui.badge>
                @if ($task->isOverdue())<x-ui.badge tone="danger" class="text-sm">{{ __('Overdue') }}</x-ui.badge>@endif
                @if ($task->archived_at)<x-ui.badge tone="neutral" class="text-sm">{{ __('Archived') }}</x-ui.badge>@endif
            </div>
        </div>
        <div class="hidden flex-wrap justify-end gap-2 md:flex">
            @foreach ($targets as $status)
                @php([$text, $icon] = $label($status))
                <x-ui.button size="sm" :variant="in_array($status->code, ['DONE', 'REVIEW'], true) ? 'default' : 'outline'" wire:click="moveTo({{ $status->id }})"><x-dynamic-component :component="'lucide-'.$icon" /> {{ $text }}</x-ui.button>
            @endforeach
            @can('update', $task)
                <x-ui.button size="sm" variant="outline" :href="route('projects.tasks.edit', $task)" wire:navigate data-detail-modal><x-lucide-pencil /> {{ __('Edit') }}</x-ui.button>
            @endcan
            @can('logTime', $task)
                <x-ui.button size="sm" variant="outline" x-on:click="$dispatch('open-sheet-task-time')"><x-lucide-timer /> {{ __('Log time') }}</x-ui.button>
            @endcan
        </div>
    </div>

    @if ($task->blocked_reason && $task->status->code === 'BLOCKED')
        <x-ui.alert tone="danger">
            <x-lucide-octagon-x />
            <x-ui.alert-title>{{ __('Blocked') }}</x-ui.alert-title>
            <x-ui.alert-description>{{ $task->blocked_reason }}</x-ui.alert-description>
        </x-ui.alert>
    @endif

    @if ($unfinishedPredecessors->isNotEmpty() && $task->isOpen())
        <x-ui.alert tone="warning">
            <x-lucide-git-branch />
            <x-ui.alert-title>{{ __('Waiting for other tasks') }}</x-ui.alert-title>
            <x-ui.alert-description class="flex flex-col gap-1">
                @foreach ($unfinishedPredecessors as $predecessor)
                    <a href="{{ route('projects.tasks.show', $predecessor) }}" wire:navigate class="hover:underline">{{ $predecessor->task_number }} · {{ $predecessor->title }} ({{ $predecessor->status->name }})</a>
                @endforeach
            </x-ui.alert-description>
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Assignee') }}</span>
            <span class="truncate text-sm font-medium">{{ $task->assignee?->full_name ?? __('Unassigned') }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Due') }}</span>
            <span @class(['text-sm font-medium tabular-nums', 'text-destructive' => $task->isOverdue()])>{{ $date($task->due_date) }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Hours') }}</span>
            <span class="text-sm font-medium tabular-nums">{{ rtrim(rtrim($task->actual_hours, '0'), '.') ?: '0' }} / {{ $task->estimated_hours !== null ? rtrim(rtrim($task->estimated_hours, '0'), '.') : '—' }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Checklist') }}</span>
            <span class="text-sm font-medium tabular-nums">{{ $task->checklist->where('is_done', true)->count() }} / {{ $task->checklist->count() }}</span>
        </x-ui.card>
    </div>

    <div class="flex min-w-0 flex-col gap-4">
        <div class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <x-ui.segmented-control name="task-tab" wire:model.live="tab" :value="$tab" class="h-11 md:h-9"
                :options="collect($tabs)->map(fn ($text, $key) => ['value' => $key, 'label' => $text])->values()->all()" />
        </div>

        @if ($tab === 'details')
            <x-ui.card class="p-4 md:p-6">
                <x-ui.description-list>
                    <x-ui.description-item :term="__('Project')">
                        @if ($task->project)
                            <a href="{{ route('projects.projects.show', $task->project) }}" wire:navigate class="hover:underline">{{ $task->project->project_number }} · {{ $task->project->name }}</a>
                        @else
                            {{ __('General task') }}
                        @endif
                    </x-ui.description-item>
                    @if ($task->project?->customer)
                        <x-ui.description-item :term="__('Customer')">{{ $task->project->customer->name }}</x-ui.description-item>
                    @endif
                    @if ($task->parent)
                        <x-ui.description-item :term="__('Main task')"><a href="{{ route('projects.tasks.show', $task->parent) }}" wire:navigate class="hover:underline">{{ $task->parent->task_number }} · {{ $task->parent->title }}</a></x-ui.description-item>
                    @endif
                    <x-ui.description-item :term="__('Type')">{{ $task->type->name }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Phase')">{{ $task->phase?->name ?? '—' }}</x-ui.description-item>
                    <x-ui.description-item :term="__('File no.')">{{ $task->file_number ?? '—' }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Support officer')">{{ $task->supportOfficer?->full_name ?? '—' }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Reviewer')">{{ $task->reviewer?->full_name ?? '—' }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Assigned by')">{{ $task->assigner->name }} · {{ $task->created_at->format('d-M-Y') }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Start')">{{ $date($task->start_date) }}</x-ui.description-item>
                    @if ($task->completed_at)
                        <x-ui.description-item :term="__('Completed')">{{ $task->completed_at->format('d-M-Y h:i A') }} · {{ $task->completer?->name }}</x-ui.description-item>
                    @endif
                    <x-ui.description-item :term="__('Watchers')">{{ $task->watchers->pluck('name')->implode(', ') ?: '—' }}</x-ui.description-item>
                </x-ui.description-list>
                @if ($task->description)
                    <p class="mt-4 whitespace-pre-line text-sm">{{ $task->description }}</p>
                @endif
            </x-ui.card>

            <x-ui.card class="p-4 md:p-6">
                <h2 class="text-base font-semibold">{{ __('Checklist') }}</h2>
                <div class="flex flex-col gap-1">
                    @forelse ($task->checklist as $item)
                        <x-ui.field orientation="horizontal" class="min-h-11 items-center" wire:key="check-{{ $item->id }}">
                            <x-ui.checkbox native id="check-{{ $item->id }}" :checked="$item->is_done" :disabled="! $canChange"
                                x-on:change="$wire.toggleItem({{ $item->id }}, $event.target.checked)" />
                            <x-ui.field-label for="check-{{ $item->id }}" @class(['font-normal', 'text-muted-foreground line-through' => $item->is_done])>
                                {{ $item->title }}
                                @if ($item->is_done && $item->doer)<span class="text-sm text-muted-foreground no-underline">· {{ $item->doer->name }}</span>@endif
                            </x-ui.field-label>
                        </x-ui.field>
                    @empty
                        <p class="text-sm text-muted-foreground">{{ __('No checklist.') }}</p>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card class="p-4 md:p-6">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold">{{ __('Sub-tasks') }}</h2>
                    @if (! $task->parent_id && $user->can('create', \App\Modules\Projects\Models\Task::class))
                        <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" :href="route('projects.tasks.create', ['parent' => $task->task_number])" wire:navigate><x-lucide-plus /> {{ __('Add sub-task') }}</x-ui.button>
                    @endif
                </div>
                <x-ui.item-group class="gap-2">
                    @forelse ($task->subtasks as $subtask)
                        <x-ui.item variant="outline" class="min-h-14 active:bg-accent" :href="route('projects.tasks.show', $subtask)" wire:navigate wire:key="subtask-{{ $subtask->id }}">
                            <x-ui.item-content class="min-w-0">
                                <x-ui.item-title class="text-sm"><span class="truncate">{{ $subtask->title }}</span></x-ui.item-title>
                                <x-ui.item-description class="text-sm">{{ $subtask->assignee?->full_name ?? __('Unassigned') }} · {{ $date($subtask->due_date) }}</x-ui.item-description>
                            </x-ui.item-content>
                            <x-ui.badge :tone="$subtask->status->color ?? 'neutral'" class="shrink-0 text-sm">{{ $subtask->status->name }}</x-ui.badge>
                        </x-ui.item>
                    @empty
                        <p class="text-sm text-muted-foreground">{{ __('No sub-tasks.') }}</p>
                    @endforelse
                </x-ui.item-group>
            </x-ui.card>
        @elseif ($tab === 'comments')
            <form wire:submit="addComment" class="flex flex-col gap-2">
                <x-ui.textarea wire:model="comment" rows="3" class="text-base md:text-sm" :placeholder="__('Write a comment. Mention someone with @username.')" :aria-label="__('Comment')" />
                <x-ui.field-error :messages="$errors->get('comment')" />
                <x-ui.button type="submit" class="h-11 self-end md:h-9"><x-lucide-send /> {{ __('Post') }}</x-ui.button>
            </form>
            <x-ui.item-group class="gap-2">
                @forelse ($comments as $entry)
                    <x-ui.item variant="outline" class="items-start" wire:key="comment-{{ $entry->id }}">
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title class="text-sm">{{ $entry->user->name }} <span class="font-normal text-muted-foreground">{{ $entry->created_at->diffForHumans() }}</span></x-ui.item-title>
                            <x-ui.item-description class="whitespace-pre-line text-sm text-foreground">{{ $entry->body }}</x-ui.item-description>
                        </x-ui.item-content>
                        @if ($entry->user_id === $user->id || $user->can('projects.tasks.delete'))
                            <x-ui.button variant="ghost" size="icon" class="size-11 shrink-0 md:size-8" wire:click="deleteComment({{ $entry->id }})" wire:confirm="{{ __('Delete this comment?') }}" :aria-label="__('Delete comment')"><x-lucide-trash-2 /></x-ui.button>
                        @endif
                    </x-ui.item>
                @empty
                    <p class="py-8 text-center text-sm text-muted-foreground">{{ __('No comments yet.') }}</p>
                @endforelse
            </x-ui.item-group>
        @elseif ($tab === 'time')
            @can('logTime', $task)
                <x-ui.button class="h-11 self-start md:h-9" x-on:click="$dispatch('open-sheet-task-time')"><x-lucide-timer /> {{ __('Log time') }}</x-ui.button>
            @endcan
            <x-ui.item-group class="gap-2">
                @forelse ($timeLogs as $log)
                    <x-ui.item variant="outline" wire:key="time-{{ $log->id }}">
                        <x-ui.item-content>
                            <x-ui.item-title class="text-sm">{{ $log->employee->full_name }} · <span class="tabular-nums">{{ rtrim(rtrim($log->hours, '0'), '.') }} h</span></x-ui.item-title>
                            <x-ui.item-description class="text-sm tabular-nums">{{ $log->work_date->format('d-M-Y') }}@if ($log->note) · {{ $log->note }}@endif</x-ui.item-description>
                        </x-ui.item-content>
                    </x-ui.item>
                @empty
                    <p class="py-8 text-center text-sm text-muted-foreground">{{ __('No time logged yet.') }}</p>
                @endforelse
            </x-ui.item-group>
        @elseif ($tab === 'documents')
            <livewire:foundation.attachments :model="$task" :key="'attachments-'.$task->id" />
        @else
            <livewire:foundation.history :model="$task" :key="'history-'.$task->id" />
        @endif
    </div>

    @if ($primary)
        @php([$primaryText, $primaryIcon] = $label($primary))
        <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t bg-background px-4 py-3 md:hidden">
            <x-ui.button class="h-11 w-full" wire:click="moveTo({{ $primary->id }})"><x-dynamic-component :component="'lucide-'.$primaryIcon" /> {{ $primaryText }}</x-ui.button>
        </div>
    @endif

    <x-shell.sheet id="task-actions" :title="__('Task actions')" :description="$task->task_number.' · '.$task->title">
        <div class="flex flex-col gap-2 pb-4">
            @foreach ($targets as $status)
                @php([$text, $icon] = $label($status))
                <x-ui.button class="h-11 justify-start" variant="outline" x-on:click="$dispatch('close-sheet-task-actions')" wire:click="moveTo({{ $status->id }})"><x-dynamic-component :component="'lucide-'.$icon" /> {{ $text }}</x-ui.button>
            @endforeach
            @can('update', $task)
                <x-ui.button class="h-11 justify-start" variant="outline" :href="route('projects.tasks.edit', $task)" wire:navigate><x-lucide-pencil /> {{ __('Edit') }}</x-ui.button>
            @endcan
            @can('logTime', $task)
                <x-ui.button class="h-11 justify-start" variant="outline" x-on:click="$dispatch('close-sheet-task-actions'); $dispatch('open-sheet-task-time')"><x-lucide-timer /> {{ __('Log time') }}</x-ui.button>
            @endcan
            @can('archive', $task)
                @if (! $task->isOpen())
                    <x-ui.button class="h-11 justify-start" variant="outline" x-on:click="$dispatch('close-sheet-task-actions')" wire:click="archive"><x-lucide-archive /> {{ $task->archived_at ? __('Unarchive') : __('Archive') }}</x-ui.button>
                @endif
            @endcan
            @can('delete', $task)
                <x-ui.button class="h-11 justify-start text-destructive" variant="outline" x-on:click="$dispatch('close-sheet-task-actions'); $dispatch('open-sheet-task-delete')"><x-lucide-trash-2 /> {{ __('Delete') }}</x-ui.button>
            @endcan
        </div>
    </x-shell.sheet>

    <x-shell.sheet id="task-block" :title="__('Block task')" :description="__('Say what is holding :number up.', ['number' => $task->task_number])">
        <form id="task-block-form" wire:submit="block" class="flex flex-col gap-4">
            <x-ui.field>
                <x-ui.field-label for="blocked-reason">{{ __('Reason') }} *</x-ui.field-label>
                <x-ui.textarea id="blocked-reason" wire:model="blockedReason" rows="2" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('blockedReason')" />
            </x-ui.field>
        </form>
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-task-block')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="task-block-form">{{ __('Block') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    <x-shell.confirm id="task-confirm-complete" :title="__('Complete with open checklist items?')" :description="__('Some checklist items are not ticked yet.')">
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-task-confirm-complete')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button wire:click="confirmComplete">{{ __('Complete anyway') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.confirm>

    @can('logTime', $task)
        <x-shell.sheet id="task-time" :title="__('Log time')" :description="__('Hours you spent on :number.', ['number' => $task->task_number])">
            <form id="task-time-form" wire:submit="logTime" class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.field>
                        <x-ui.field-label for="time-date">{{ __('Date') }} *</x-ui.field-label>
                        <x-ui.input type="date" id="time-date" wire:model="timeForm.work_date" max="{{ today()->toDateString() }}" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('timeForm.work_date')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="time-hours">{{ __('Hours') }} *</x-ui.field-label>
                        <x-ui.input id="time-hours" wire:model="timeForm.hours" inputmode="decimal" class="{{ $input }} tabular-nums" />
                        <x-ui.field-error :messages="$errors->get('timeForm.hours')" />
                    </x-ui.field>
                </div>
                <x-ui.field>
                    <x-ui.field-label for="time-note">{{ __('Note') }}</x-ui.field-label>
                    <x-ui.input id="time-note" wire:model="timeForm.note" class="{{ $input }}" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-task-time')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="task-time-form">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endcan

    @can('delete', $task)
        <x-shell.confirm id="task-delete" :title="__('Delete task?')" :description="__('The task and its sub-tasks are hidden from lists.')">
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-task-delete')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="destructive" wire:click="deleteTask">{{ __('Delete') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.confirm>
    @endcan
</div>
