@php
    $user = auth()->user();
    $isOpen = $lead->isOpen() && ! $lead->isConverted();
    $isLost = $lead->status->code === \App\Modules\Crm\Models\LeadStatus::LOST;
    $canChangeStatus = $user->can('changeStatus', $lead);
    $logEvent = fn (string $mode) => "\$dispatch('crm-log-activity', { subjectType: 'lead', subjectId: {$lead->id}, mode: '{$mode}' })";
    $statusEvent = fn (string $mode) => "\$dispatch('crm-change-status', { lead: '{$lead->lead_number}', mode: '{$mode}' })";

    /** Header actions, shared by the desktop toolbar and the mobile actions sheet. */
    $actions = array_values(array_filter([
        $user->can('update', $lead) && Route::has('crm.leads.edit') ? ['label' => __('Edit'), 'icon' => 'pencil', 'href' => route('crm.leads.edit', $lead)] : null,
        $user->can('crm.activities.create') ? ['label' => __('Log activity'), 'icon' => 'notebook-pen', 'click' => $logEvent('log'), 'primary' => true] : null,
        $user->can('crm.activities.create') ? ['label' => __('Schedule follow-up'), 'icon' => 'calendar-plus', 'click' => $logEvent('schedule')] : null,
        Route::has('crm.leads.convert') && $user->can('convert', $lead) ? ['label' => __('Mark won → convert'), 'icon' => 'trophy', 'href' => route('crm.leads.convert', $lead)] : null,
        $isOpen && $canChangeStatus ? ['label' => __('Change status'), 'icon' => 'arrow-right-left', 'click' => $statusEvent('status')] : null,
        $isOpen && $user->can('assign', $lead) ? ['label' => __('Assign'), 'icon' => 'user-plus', 'click' => "\$dispatch('open-sheet-lead-assign')"] : null,
        $isOpen && $canChangeStatus ? ['label' => __('Mark lost'), 'icon' => 'circle-x', 'click' => $statusEvent('lost')] : null,
        $isLost && $canChangeStatus ? ['label' => __('Reopen'), 'icon' => 'rotate-ccw', 'click' => $statusEvent('reopen')] : null,
        ['label' => __('Print profile'), 'icon' => 'printer', 'href' => route('crm.leads.print', $lead), 'external' => true],
        $user->can('delete', $lead) ? ['label' => __('Delete'), 'icon' => 'trash-2', 'click' => "\$dispatch('open-sheet-lead-delete')", 'destructive' => true] : null,
    ]));

    $tabs = ['overview' => __('Overview'), 'activities' => __('Activities'), 'documents' => __('Documents'), 'notes' => __('Notes'), 'history' => __('History')];
    $nextIsOverdue = $lead->next_follow_up_at?->isPast() ?? false;
@endphp

<x-slot:actions>
    <x-ui.button variant="ghost" size="icon" class="size-11" x-on:click="$dispatch('open-sheet-lead-actions')" :aria-label="__('Lead actions')">
        <x-lucide-ellipsis-vertical class="size-5" />
    </x-ui.button>
</x-slot:actions>

<div class="flex flex-col gap-6 pb-24 md:pb-0">
    {{-- Header --}}
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-col gap-1">
            <span class="font-mono text-sm text-muted-foreground max-md:hidden">{{ $lead->lead_number }}</span>
            <h1 class="truncate text-xl font-semibold tracking-tight md:text-2xl">{{ $lead->name }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-ui.badge :tone="$lead->status->color ?? 'info'" class="text-sm">{{ $lead->status->name }}</x-ui.badge>
                <x-ui.badge :tone="$lead->priority->color ?? 'neutral'" class="text-sm">{{ $lead->priority->name }}</x-ui.badge>
                <span class="text-muted-foreground">{{ $lead->assignee?->name ?? __('Unassigned') }}</span>
            </div>
        </div>
        <div class="hidden flex-wrap justify-end gap-2 md:flex">
            @foreach ($actions as $action)
                @php($variant = ($action['primary'] ?? false) ? 'default' : (($action['destructive'] ?? false) ? 'destructive' : 'outline'))
                @if (isset($action['href']) && ($action['external'] ?? false))
                    <x-ui.button size="sm" :$variant :href="$action['href']" target="_blank"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @elseif (isset($action['href']))
                    <x-ui.button size="sm" :$variant :href="$action['href']" wire:navigate><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @else
                    <x-ui.button size="sm" :$variant x-on:click="{{ $action['click'] }}"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @endif
            @endforeach
        </div>
    </div>

    @if ($lead->isConverted() && $lead->convertedCustomer)
        <x-ui.alert tone="success">
            <x-lucide-badge-check />
            <x-ui.alert-title>{{ __('Converted to customer :name on :date', ['name' => $lead->convertedCustomer->name, 'date' => $lead->converted_at?->format('d-M-Y')]) }}</x-ui.alert-title>
            @if ($user->can('view', $lead->convertedCustomer))
                <x-ui.alert-description>
                    <a data-detail-modal href="{{ route('crm.customers.show', $lead->convertedCustomer) }}" wire:navigate class="font-medium underline">{{ $lead->convertedCustomer->customer_number }}</a>
                </x-ui.alert-description>
            @endif
        </x-ui.alert>
    @endif

    {{-- Pipeline bar --}}
    @if ($isOpen)
        <nav class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0" aria-label="{{ __('Pipeline') }}">
            <ol class="flex w-max gap-2 md:w-full">
                @foreach ($statuses as $status)
                    @php($isCurrent = $status->id === $lead->lead_status_id)
                    <li class="md:flex-1" wire:key="step-{{ $status->id }}">
                        <button type="button" @disabled(! $canChangeStatus || $isCurrent)
                            @if ($canChangeStatus && ! $isCurrent) x-on:click="$dispatch('crm-change-status', { lead: @js($lead->lead_number), mode: 'status', status: {{ $status->id }} })" @endif
                            @class([
                                'flex h-11 w-full items-center justify-center whitespace-nowrap rounded-full border px-4 text-sm font-medium md:h-9',
                                'border-primary bg-primary text-primary-foreground' => $isCurrent,
                                'bg-background hover:bg-accent active:bg-accent' => ! $isCurrent,
                            ])>
                            {{ $status->name }}
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Summary rail (cards on mobile, right column on desktop) --}}
        <aside class="grid grid-cols-2 gap-3 lg:order-last lg:grid-cols-1">
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Age') }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ trans_choice(':count day|:count days', $ageDays) }}</span>
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Days in status') }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ $daysInStatus ?? '—' }}</span>
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Last activity') }}</span>
                <span class="text-sm font-medium tabular-nums">{{ $lead->last_activity_at?->format('d-M-Y h:i A') ?? '—' }}</span>
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Next follow-up') }}</span>
                <span @class(['text-sm font-medium tabular-nums', 'text-destructive' => $nextIsOverdue])>{{ $lead->next_follow_up_at?->format('d-M-Y h:i A') ?? '—' }}</span>
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Expected value') }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ $lead->expected_value !== null ? \App\Support\Money::format($lead->expected_value) : '—' }}</span>
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Customer') }}</span>
                <span class="truncate text-sm font-medium">{{ $lead->convertedCustomer?->name ?? __('Not converted') }}</span>
            </x-ui.card>
        </aside>

        <div class="flex min-w-0 flex-col gap-4 lg:col-span-2">
            <div class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                <x-ui.segmented-control name="lead-tab" wire:model.live="tab" :value="$tab" class="h-11 md:h-9"
                    :options="collect($tabs)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->all()" />
            </div>

            @if ($tab === 'overview')
                <x-ui.card class="p-4 md:p-6">
                    <h2 class="text-base font-semibold">{{ __('Contact') }}</h2>
                    <x-ui.description-list>
                        <x-ui.description-item :term="__('Phone')"><a href="tel:{{ $lead->phone }}" class="tabular-nums hover:underline">{{ $lead->phone }}</a></x-ui.description-item>
                        @if ($lead->whatsapp)
                            <x-ui.description-item :term="__('WhatsApp')"><a href="https://wa.me/88{{ $lead->whatsapp }}" target="_blank" rel="noopener" class="tabular-nums hover:underline">{{ $lead->whatsapp }}</a></x-ui.description-item>
                        @endif
                        <x-ui.description-item :term="__('Office phone')">{{ $lead->office_phone ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Email')">{{ $lead->email ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Company')">{{ $lead->company_name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Address')">{{ $lead->address ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Location')">{{ $lead->location?->full_path ?? '—' }}</x-ui.description-item>
                    </x-ui.description-list>

                    <h2 class="mt-4 text-base font-semibold">{{ __('Enquiry') }}</h2>
                    <x-ui.description-list>
                        <x-ui.description-item :term="__('Lead date')">{{ $lead->lead_date->format('d-M-Y') }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Source')">{{ $lead->source->name }}</x-ui.description-item>
                        @if ($lead->referrerLabel())
                            <x-ui.description-item :term="__('Referrer')">
                                @if ($lead->referrer_type === 'employee' && $lead->referrerEmployee && auth()->user()->can('view', $lead->referrerEmployee))
                                    <a data-detail-modal href="{{ route('hrm.employees.show', $lead->referrerEmployee) }}" wire:navigate class="hover:underline">{{ $lead->referrerLabel() }}</a>
                                @else
                                    {{ $lead->referrerLabel() }}
                                @endif
                            </x-ui.description-item>
                        @endif
                        <x-ui.description-item :term="__('Business line')">{{ $lead->businessLine?->name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Level')">{{ $lead->level?->name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Team')">{{ $lead->team?->name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Expected close')">{{ $lead->expected_close_date?->format('d-M-Y') ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Site location')">{{ $lead->site_location_text ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Land area')">{{ $lead->land_area ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Floors planned')">{{ $lead->floors_planned ?? '—' }}</x-ui.description-item>
                        @if ($lead->lostReason)
                            <x-ui.description-item :term="__('Lost reason')">{{ $lead->lostReason->name }}{{ $lead->lost_note ? ' — '.$lead->lost_note : '' }}</x-ui.description-item>
                        @endif
                    </x-ui.description-list>

                    <h2 class="mt-4 text-base font-semibold">{{ __('Services') }}</h2>
                    <x-ui.item-group class="gap-2">
                        @foreach ($lead->services as $line)
                            <x-ui.item variant="outline" wire:key="service-{{ $line->id }}">
                                <x-ui.item-content>
                                    <x-ui.item-title class="text-sm">{{ $line->service->name }}</x-ui.item-title>
                                    @if ($line->notes)
                                        <x-ui.item-description class="text-sm">{{ $line->notes }}</x-ui.item-description>
                                    @endif
                                </x-ui.item-content>
                                <span class="text-sm tabular-nums">{{ $line->estimated_value !== null ? \App\Support\Money::format($line->estimated_value, false) : '—' }}</span>
                            </x-ui.item>
                        @endforeach
                    </x-ui.item-group>

                    @if ($lead->notes)
                        <h2 class="mt-4 text-base font-semibold">{{ __('Notes') }}</h2>
                        <p class="whitespace-pre-line text-sm">{{ $lead->notes }}</p>
                    @endif
                </x-ui.card>
            @elseif ($tab === 'activities')
                @can('crm.activities.create')
                    <x-ui.button class="h-11 self-start md:h-9" x-on:click="{{ $logEvent('log') }}"><x-lucide-plus /> {{ __('Log activity') }}</x-ui.button>
                @endcan
                @include('livewire.crm.activities.partials.timeline', ['activities' => $activities, 'showSubject' => false])
            @elseif ($tab === 'documents')
                <livewire:foundation.attachments :model="$lead" :key="'attachments-'.$lead->id" />
            @elseif ($tab === 'notes')
                <livewire:foundation.notes :model="$lead" :key="'notes-'.$lead->id" />
            @else
                <x-ui.card class="p-4 md:p-6">
                    <h2 class="text-base font-semibold">{{ __('Status history') }}</h2>
                    <ul class="flex flex-col gap-2 text-sm">
                        @forelse ($statusHistory as $row)
                            <li wire:key="sh-{{ $row->id }}">
                                <span class="font-medium">{{ $row->fromStatus?->name ?? '—' }} → {{ $row->toStatus->name }}</span>
                                <span class="text-muted-foreground">· {{ $row->changer?->name }} · {{ $row->changed_at->format('d-M-Y h:i A') }}</span>
                                @if ($row->note)<p class="text-muted-foreground">{{ $row->note }}</p>@endif
                            </li>
                        @empty
                            <li class="text-muted-foreground">{{ __('No status changes yet.') }}</li>
                        @endforelse
                    </ul>
                    <h2 class="mt-4 text-base font-semibold">{{ __('Assignment history') }}</h2>
                    <ul class="flex flex-col gap-2 text-sm">
                        @forelse ($assignmentHistory as $row)
                            <li wire:key="ah-{{ $row->id }}">
                                <span class="font-medium">{{ $row->fromUser?->name ?? __('Unassigned') }} → {{ $row->toUser?->name ?? __('Unassigned') }}</span>
                                <span class="text-muted-foreground">· {{ $row->assigner?->name }} · {{ $row->assigned_at->format('d-M-Y h:i A') }}</span>
                                @if ($row->reason)<p class="text-muted-foreground">{{ $row->reason }}</p>@endif
                            </li>
                        @empty
                            <li class="text-muted-foreground">{{ __('No assignments yet.') }}</li>
                        @endforelse
                    </ul>
                </x-ui.card>
                <livewire:foundation.history :model="$lead" :key="'history-'.$lead->id" />
            @endif
        </div>
    </div>

    {{-- Mobile actions sheet --}}
    <x-shell.sheet id="lead-actions" :title="__('Lead actions')">
        <div class="flex flex-col gap-2 pb-4">
            @foreach ($actions as $action)
                @php($variant = ($action['destructive'] ?? false) ? 'destructive' : 'outline')
                @if (isset($action['href']) && ($action['external'] ?? false))
                    <x-ui.button class="h-11 justify-start" :$variant :href="$action['href']" target="_blank"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @elseif (isset($action['href']))
                    <x-ui.button class="h-11 justify-start" :$variant :href="$action['href']" wire:navigate><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @else
                    <x-ui.button class="h-11 justify-start" :$variant x-on:click="$dispatch('close-sheet-lead-actions'); {{ $action['click'] }}"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @endif
            @endforeach
        </div>
    </x-shell.sheet>

    @can('assign', $lead)
        <x-shell.sheet id="lead-assign" :title="__('Assign lead')">
            <form wire:submit="assign" id="lead-assign-form" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="assign-to">{{ __('Assign to') }}</x-ui.field-label>
                    <x-ui.select native id="assign-to" wire:model="assignTo" class="h-11 text-base md:h-9 md:text-sm">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="[...$errors->get('assigned_to'), ...$errors->get('lead')]" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="assign-reason">{{ __('Reason') }}</x-ui.field-label>
                    <x-ui.input id="assign-reason" wire:model="assignReason" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-lead-assign')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="lead-assign-form">{{ __('Assign') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endcan

    @can('delete', $lead)
        <x-shell.sheet id="lead-delete" :title="__('Delete lead?')" :description="__('The lead is hidden from lists. Its history is kept.')">
            <x-ui.field-error :messages="$errors->get('lead')" />
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-lead-delete')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="destructive" wire:click="deleteLead">{{ __('Delete') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endcan

    <x-shell.sheet id="activity-delete" :title="__('Delete activity?')">
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-activity-delete')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button variant="destructive" wire:click="deleteActivity">{{ __('Delete') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
