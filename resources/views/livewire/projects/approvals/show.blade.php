@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
    $elapsed = $approval->daysElapsed();
    $typical = $approval->type->typical_days;
@endphp

<x-slot:actions>
    @if ($canManage && ! $approval->isFinal())
        <x-ui.button variant="ghost" size="icon" class="size-11" x-on:click="$dispatch('open-sheet-approval-event')" :aria-label="__('Add event')">
            <x-lucide-plus class="size-5" />
        </x-ui.button>
    @endif
</x-slot:actions>

<div class="flex flex-col gap-6 pb-24 md:pb-0">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-col gap-1">
            <a data-detail-modal href="{{ route('projects.projects.show', ['project' => $approval->project, 'tab' => 'approvals']) }}" wire:navigate class="font-mono text-sm text-muted-foreground hover:underline">{{ $approval->project->project_number }} · {{ $approval->project->name }}</a>
            <h1 class="text-xl font-semibold tracking-tight md:text-2xl">{{ $approval->type->name }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-ui.badge :tone="$approval->status->color ?? 'neutral'" class="text-sm">{{ $approval->status->name }}</x-ui.badge>
                @if ($approval->isOverdue())<x-ui.badge tone="danger" class="text-sm">{{ __('Overdue') }}</x-ui.badge>@endif
                <span class="text-muted-foreground">{{ $approval->authority->name }}@if ($approval->reference_no) · {{ $approval->reference_no }}@endif</span>
            </div>
        </div>
        @if ($canManage)
            <div class="hidden flex-wrap justify-end gap-2 md:flex">
                <x-ui.button size="sm" variant="outline" :href="route('projects.approvals.edit', $approval)" wire:navigate data-detail-modal><x-lucide-pencil /> {{ __('Edit') }}</x-ui.button>
                @unless ($approval->isFinal())
                    <x-ui.button size="sm" x-on:click="$dispatch('open-sheet-approval-event')"><x-lucide-plus /> {{ __('Add event') }}</x-ui.button>
                @endunless
            </div>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Submitted') }}</span>
            <span class="text-sm font-medium tabular-nums">{{ $date($approval->submitted_on) }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Expected') }}</span>
            <span @class(['text-sm font-medium tabular-nums', 'text-destructive' => $approval->isOverdue()])>{{ $date($approval->expected_on) }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Days so far') }}</span>
            <span class="text-sm font-medium tabular-nums">{{ $elapsed ?? '—' }} @if ($typical) / {{ __(':days typical', ['days' => $typical]) }} @endif</span>
            @if ($elapsed !== null && $typical)
                <x-ui.progress :value="min(100, round($elapsed * 100 / $typical))" class="h-1.5" />
            @endif
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Approved') }}</span>
            <span class="text-sm font-medium tabular-nums">{{ $date($approval->approved_on) }}</span>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-ui.card class="p-4 md:p-6">
            <h2 class="text-base font-semibold">{{ __('Trail') }}</h2>
            <ol class="flex flex-col gap-3">
                @forelse ($approval->events as $event)
                    <li class="flex gap-3" wire:key="event-{{ $event->id }}">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"></span>
                        <div class="flex min-w-0 flex-col gap-0.5 text-sm">
                            <span class="font-medium">{{ $event->status->name }} <span class="font-normal tabular-nums text-muted-foreground">{{ $event->event_date->format('d-M-Y') }}</span></span>
                            @if ($event->note)<span class="whitespace-pre-line">{{ $event->note }}</span>@endif
                            @if ($event->attachment)
                                <a href="{{ $event->attachment->downloadUrl() }}" class="flex items-center gap-1 hover:underline"><x-lucide-paperclip class="size-4" /> {{ $event->attachment->original_name }}</a>
                            @endif
                            @if ($event->creator)<span class="text-muted-foreground">{{ $event->creator->name }}</span>@endif
                        </div>
                    </li>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('No events yet.') }}</p>
                @endforelse
            </ol>
        </x-ui.card>

        <x-ui.card class="p-4 md:p-6">
            <h2 class="text-base font-semibold">{{ __('Checklist') }}</h2>
            <div class="flex flex-col gap-1">
                @forelse ($approval->checklist as $item)
                    <x-ui.field orientation="horizontal" class="min-h-11 items-center" wire:key="approval-check-{{ $item->id }}">
                        <x-ui.checkbox native id="approval-check-{{ $item->id }}" :checked="$item->is_done" :disabled="! $canManage"
                            x-on:change="$wire.toggleItem({{ $item->id }}, $event.target.checked)" />
                        <x-ui.field-label for="approval-check-{{ $item->id }}" @class(['font-normal', 'text-muted-foreground line-through' => $item->is_done])>{{ $item->title }}</x-ui.field-label>
                    </x-ui.field>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('No checklist.') }}</p>
                @endforelse
            </div>
            <x-ui.description-list class="mt-4">
                <x-ui.description-item :term="__('Responsible')">{{ $approval->responsible?->full_name ?? '—' }}</x-ui.description-item>
                <x-ui.description-item :term="__('Prepared')">{{ $date($approval->prepared_on) }}</x-ui.description-item>
                <x-ui.description-item :term="__('Valid until')">{{ $date($approval->valid_until) }}</x-ui.description-item>
                <x-ui.description-item :term="__('Authority fee')">{{ $approval->authority_fee !== null ? \App\Support\Money::format($approval->authority_fee) : '—' }}</x-ui.description-item>
            </x-ui.description-list>
            @if ($approval->notes)
                <p class="mt-3 whitespace-pre-line text-sm">{{ $approval->notes }}</p>
            @endif
        </x-ui.card>
    </div>

    <livewire:foundation.attachments :model="$approval" :key="'approval-attachments-'.$approval->id" />

    @if ($canManage && ! $approval->isFinal())
        <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t bg-background px-4 py-3 md:hidden">
            <x-ui.button class="h-11 w-full" x-on:click="$dispatch('open-sheet-approval-event')"><x-lucide-plus /> {{ __('Add event') }}</x-ui.button>
        </div>

        <x-shell.sheet id="approval-event" :title="__('Add event')" :description="__('Record a step: submitted, query raised, approved…')">
            <form id="approval-event-form" wire:submit="addEvent" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="event-status">{{ __('Status') }} *</x-ui.field-label>
                    <x-ui.select native id="event-status" wire:model="eventForm.approval_status_id" class="{{ $input }}">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('eventForm.approval_status_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="event-date">{{ __('Date') }} *</x-ui.field-label>
                    <x-ui.input type="date" id="event-date" wire:model="eventForm.event_date" max="{{ today()->toDateString() }}" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('eventForm.event_date')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="event-note">{{ __('Note') }}</x-ui.field-label>
                    <x-ui.textarea id="event-note" wire:model="eventForm.note" rows="2" class="text-base md:text-sm" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="event-file">{{ __('File') }}</x-ui.field-label>
                    <x-ui.input id="event-file" type="file" wire:model="file" class="{{ $input }}" />
                    <x-ui.field-description>{{ __('Approval needs the approval letter.') }}</x-ui.field-description>
                    <x-ui.field-error :messages="$errors->get('file')" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-approval-event')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="approval-event-form" wire:loading.attr="disabled" wire:target="file,addEvent">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endif
</div>
