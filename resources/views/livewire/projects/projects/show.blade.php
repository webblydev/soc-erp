@php
    $user = auth()->user();
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
    $money = fn ($value) => \App\Support\Money::format($value);
    $pmInactive = $project->manager && ! $project->manager->status->is_active_employment;
    $canLog = $user->can('crm.activities.create');

    $actions = array_values(array_filter([
        $user->can('update', $project) ? ['label' => __('Edit'), 'icon' => 'pencil', 'href' => route('projects.projects.edit', $project), 'modal' => true] : null,
        $user->can('changeStatus', $project) ? ['label' => __('Change status'), 'icon' => 'arrow-right-left', 'click' => "\$dispatch('open-sheet-project-status')"] : null,
        $user->can('createTask', $project) && $project->isOpen() ? ['label' => __('Add task'), 'icon' => 'list-plus', 'href' => route('projects.tasks.create', ['project' => $project->project_number]), 'modal' => true, 'primary' => true] : null,
        $canLog ? ['label' => __('Log activity'), 'icon' => 'notebook-pen', 'click' => "\$dispatch('crm-log-activity', { subjectType: 'project', subjectId: {$project->id}, mode: 'log' })"] : null,
        $user->can('manageApprovals', $project) ? ['label' => __('New approval'), 'icon' => 'stamp', 'href' => route('projects.approvals.create', ['project' => $project->project_number]), 'modal' => true] : null,
        ['label' => __('Print project sheet'), 'icon' => 'printer', 'href' => route('projects.projects.print', $project), 'external' => true],
        $user->can('delete', $project) && $project->status->code === \App\Modules\Projects\Models\ProjectStatus::ENQUIRY ? ['label' => __('Delete'), 'icon' => 'trash-2', 'click' => "\$dispatch('open-sheet-project-delete')", 'destructive' => true] : null,
    ]));
    $primary = collect($actions)->firstWhere('primary', true);
    $targetCode = $targets->firstWhere('id', (int) $statusForm['project_status_id'])?->code;
@endphp

<x-slot:actions>
    <x-ui.button variant="ghost" size="icon" class="size-11" x-on:click="$dispatch('open-sheet-project-actions')" :aria-label="__('Project actions')">
        <x-lucide-ellipsis-vertical class="size-5" />
    </x-ui.button>
</x-slot:actions>

<div class="flex flex-col gap-6 pb-24 md:pb-0">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-col gap-1">
            <span class="font-mono text-sm text-muted-foreground">{{ $project->project_number }}</span>
            <h1 class="text-xl font-semibold tracking-tight md:text-2xl">{{ $project->name }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-ui.badge :tone="$project->status->color ?? 'neutral'" class="text-sm">{{ $project->status->name }}</x-ui.badge>
                @if ($project->phase)
                    <x-ui.badge variant="outline" class="text-sm">{{ $project->phase->name }}</x-ui.badge>
                @endif
                @if ($project->customer)
                    @can('view', $project->customer)
                        <a data-detail-modal href="{{ route('crm.customers.show', $project->customer) }}" wire:navigate class="font-medium hover:underline">{{ $project->customer->name }}</a>
                    @else
                        <span class="font-medium">{{ $project->customer->name }}</span>
                    @endcan
                @else
                    <span class="text-muted-foreground">{{ __('Internal') }}</span>
                @endif
                <span class="text-muted-foreground">· {{ __('PM') }}: {{ $project->manager?->full_name ?? '—' }}</span>
                @if ($pmInactive)
                    <x-ui.badge tone="danger" class="text-sm">{{ __('PM inactive') }}</x-ui.badge>
                @endif
            </div>
        </div>
        <div class="hidden flex-wrap justify-end gap-2 md:flex">
            @foreach ($actions as $action)
                @php($variant = ($action['primary'] ?? false) ? 'default' : (($action['destructive'] ?? false) ? 'destructive' : 'outline'))
                @if (isset($action['href']) && ($action['external'] ?? false))
                    <x-ui.button size="sm" :$variant :href="$action['href']" target="_blank"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @elseif (isset($action['href']))
                    <x-ui.button size="sm" :$variant :href="$action['href']" wire:navigate :data-detail-modal="($action['modal'] ?? false) ?: null"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @else
                    <x-ui.button size="sm" :$variant x-on:click="{{ $action['click'] }}"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @endif
            @endforeach
        </div>
    </div>

    @if ($pmInactive && $user->can('update', $project))
        <x-ui.alert tone="warning">
            <x-lucide-user-x />
            <x-ui.alert-title>{{ __(':name is no longer an active employee', ['name' => $project->manager->full_name]) }}</x-ui.alert-title>
            <x-ui.alert-description>{{ __('Choose a new project manager (PRJ-BR-10).') }}</x-ui.alert-description>
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Contract value') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ $money($project->contract_value) }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Tasks done') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ $figures['tasks_done'] }} / {{ $figures['tasks_total'] }}</span>
            <x-ui.progress :value="$figures['tasks_total'] > 0 ? round($figures['tasks_done'] * 100 / $figures['tasks_total']) : 0" class="h-1.5" />
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Overdue tasks') }}</span>
            <span @class(['text-lg font-semibold tabular-nums', 'text-destructive' => $figures['tasks_overdue'] > 0])>{{ $figures['tasks_overdue'] }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Approvals pending') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ $figures['approvals_pending'] }}</span>
        </x-ui.card>
        @can('viewBudget', $project)
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Budget') }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ $money($project->budget_cost) }}</span>
            </x-ui.card>
        @endcan
        <x-ui.card class="col-span-2 gap-1 p-4 md:col-span-1">
            <span class="text-sm text-muted-foreground">{{ __('Next milestone') }}</span>
            @if ($figures['next_milestone'])
                <span class="truncate text-sm font-medium">{{ $figures['next_milestone']->milestone_name }}</span>
                <span class="text-sm tabular-nums">{{ $money($figures['next_milestone']->amount) }} · {{ $figures['next_milestone']->status->name }}</span>
            @else
                <span class="text-sm font-medium">—</span>
            @endif
        </x-ui.card>
    </div>

    <div class="flex min-w-0 flex-col gap-4">
        <div class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <x-ui.segmented-control name="project-tab" wire:model.live="tab" :value="$tab" class="h-11 md:h-9"
                :options="collect($tabs)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->all()" />
        </div>

        @if ($tab === 'overview')
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <x-ui.card class="p-4 md:p-6 lg:col-span-2">
                    <h2 class="text-base font-semibold">{{ __('Details') }}</h2>
                    <x-ui.description-list>
                        <x-ui.description-item :term="__('Business line')">{{ $project->businessLine->name }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Type')">{{ $project->type->name }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Branch')">{{ $project->branch?->name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Start')">{{ $date($project->start_date) }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Expected end')">{{ $date($project->expected_end_date) }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Handover')">{{ $date($project->handover_date) }}</x-ui.description-item>
                        @if ($project->actual_end_date)
                            <x-ui.description-item :term="__('Completed')">{{ $date($project->actual_end_date) }}</x-ui.description-item>
                        @endif
                        @if ($project->holdReason)
                            <x-ui.description-item :term="__('On hold because')">{{ $project->holdReason->name }}</x-ui.description-item>
                        @endif
                        @if ($project->cancel_reason)
                            <x-ui.description-item :term="__('Cancel reason')">{{ $project->cancel_reason }}</x-ui.description-item>
                        @endif
                        <x-ui.description-item :term="__('Completion')">{{ rtrim(rtrim((string) $project->completion_pct, '0'), '.') }} %</x-ui.description-item>
                        @if ($project->sourceLead)
                            <x-ui.description-item :term="__('From lead')">
                                @can('view', $project->sourceLead)
                                    <a data-detail-modal href="{{ route('crm.leads.show', $project->sourceLead) }}" wire:navigate class="font-mono hover:underline">{{ $project->sourceLead->lead_number }}</a>
                                @else
                                    <span class="font-mono">{{ $project->sourceLead->lead_number }}</span>
                                @endcan
                            </x-ui.description-item>
                        @endif
                    </x-ui.description-list>

                    <h2 class="mt-4 text-base font-semibold">{{ __('Site') }}</h2>
                    <x-ui.description-list>
                        <x-ui.description-item :term="__('Address')">{{ $project->site_address ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Location')">{{ $project->location?->full_path ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Plot no.')">{{ $project->plot_no ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Land area')">{{ $project->land_area !== null ? rtrim(rtrim($project->land_area, '0'), '.').' '.($project->landAreaUnit?->symbol ?? '') : '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Floors / basements')">{{ $project->floors ?? '—' }} / {{ $project->basements ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Built-up area')">{{ $project->built_up_area_sft !== null ? \App\Support\Money::format($project->built_up_area_sft, false).' sft' : '—' }}</x-ui.description-item>
                        @if ($project->latitude && $project->longitude)
                            <x-ui.description-item :term="__('Map pin')">
                                <a href="https://www.google.com/maps?q={{ $project->latitude }},{{ $project->longitude }}" target="_blank" rel="noopener" class="tabular-nums hover:underline">{{ $project->latitude }}, {{ $project->longitude }}</a>
                            </x-ui.description-item>
                        @endif
                    </x-ui.description-list>

                    @if ($project->description || $project->notes)
                        <h2 class="mt-4 text-base font-semibold">{{ __('Notes') }}</h2>
                        <p class="whitespace-pre-line text-sm">{{ trim($project->description."\n\n".$project->notes) }}</p>
                    @endif
                </x-ui.card>

                <x-ui.card class="p-4 md:p-6">
                    <h2 class="text-base font-semibold">{{ __('Team') }}</h2>
                    <x-ui.item-group class="gap-2">
                        @forelse ($project->activeTeam as $member)
                            <x-ui.item variant="outline" size="sm" wire:key="overview-member-{{ $member->id }}">
                                <x-employee-avatar :employee="$member->employee" class="size-8" />
                                <x-ui.item-content class="min-w-0">
                                    <x-ui.item-title class="text-sm"><span class="truncate">{{ $member->employee->full_name }}</span></x-ui.item-title>
                                    <x-ui.item-description class="text-sm">{{ $member->role->name }}</x-ui.item-description>
                                </x-ui.item-content>
                            </x-ui.item>
                        @empty
                            <p class="text-sm text-muted-foreground">{{ __('No team members yet.') }}</p>
                        @endforelse
                    </x-ui.item-group>
                </x-ui.card>
            </div>

            <x-ui.card class="p-4 md:p-6">
                <h2 class="text-base font-semibold">{{ __('Services') }}</h2>
                <x-ui.item-group class="gap-2">
                    @forelse ($project->services as $line)
                        <x-ui.item variant="outline" class="flex-col items-stretch gap-2 md:flex-row md:items-center" wire:key="overview-service-{{ $line->id }}">
                            <x-ui.item-content class="min-w-0">
                                <x-ui.item-title @class(['text-sm', 'line-through' => $line->isCancelled()])>{{ $line->service->name }}</x-ui.item-title>
                                <x-ui.item-description class="text-sm tabular-nums">
                                    {{ rtrim(rtrim($line->quantity, '0'), '.') }} {{ $line->unit?->symbol }} × {{ \App\Support\Money::formatRate($line->rate) }}
                                    @if ((float) $line->discount_amount > 0) − {{ \App\Support\Money::format($line->discount_amount, false) }} @endif
                                    @if ($line->description) · {{ $line->description }} @endif
                                </x-ui.item-description>
                            </x-ui.item-content>
                            <div class="flex items-center justify-between gap-3 md:justify-end">
                                @if (! $line->isCancelled() && $user->can('update', $project))
                                    <x-ui.select native class="h-11 w-40 text-base md:h-8 md:text-sm" :aria-label="__('Delivery status')"
                                        x-on:change="$wire.setServiceStatus({{ $line->id }}, $event.target.value)">
                                        @foreach (['NOT_STARTED' => __('Not started'), 'IN_PROGRESS' => __('In progress'), 'DELIVERED' => __('Delivered')] as $code => $label)
                                            <option value="{{ $code }}" @selected($line->status->code === $code)>{{ $label }}</option>
                                        @endforeach
                                    </x-ui.select>
                                @else
                                    <x-ui.badge :tone="$line->status->color ?? 'neutral'" class="text-sm">{{ $line->status->name }}</x-ui.badge>
                                @endif
                                <span class="text-sm font-medium tabular-nums">{{ \App\Support\Money::format($line->amount, false) }}</span>
                            </div>
                        </x-ui.item>
                    @empty
                        <p class="text-sm text-muted-foreground">{{ __('No services yet.') }}</p>
                    @endforelse
                </x-ui.item-group>
            </x-ui.card>
        @elseif ($tab === 'contract')
            <livewire:projects.contract-tab :project="$project" :key="'contract-'.$project->id" />
        @elseif ($tab === 'team')
            <livewire:projects.team-tab :project="$project" :key="'team-'.$project->id" />
        @elseif ($tab === 'tasks')
            <livewire:projects.tasks-tab :project="$project" :key="'tasks-'.$project->id" />
        @elseif ($tab === 'approvals')
            @can('manageApprovals', $project)
                <x-ui.button class="h-11 self-start md:h-9" :href="route('projects.approvals.create', ['project' => $project->project_number])" wire:navigate data-detail-modal><x-lucide-plus /> {{ __('New approval') }}</x-ui.button>
            @endcan
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @forelse ($approvals as $approval)
                    <a href="{{ route('projects.approvals.show', $approval) }}" wire:navigate data-detail-modal wire:key="approval-{{ $approval->id }}" class="rounded-xl focus-visible:outline-2 focus-visible:outline-ring">
                        <x-ui.card class="h-full gap-2 p-4 transition-colors hover:bg-accent/50 active:bg-accent">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex min-w-0 flex-col">
                                    <span class="font-medium">{{ $approval->type->name }}</span>
                                    <span class="text-sm text-muted-foreground">{{ $approval->authority->name }} @if ($approval->reference_no) · {{ $approval->reference_no }} @endif</span>
                                </div>
                                <x-ui.badge :tone="$approval->status->color ?? 'neutral'" class="shrink-0 text-sm">{{ $approval->status->name }}</x-ui.badge>
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm tabular-nums text-muted-foreground">
                                <span>{{ __('Submitted') }}: {{ $date($approval->submitted_on) }}</span>
                                <span>{{ __('Expected') }}: {{ $date($approval->expected_on) }}</span>
                                @if ($approval->daysElapsed() !== null)
                                    <span>{{ __(':days days (typical :typical)', ['days' => $approval->daysElapsed(), 'typical' => $approval->type->typical_days ?? '—']) }}</span>
                                @endif
                            </div>
                            @if ($approval->isOverdue())
                                <x-ui.badge tone="danger" class="text-sm">{{ __('Overdue') }}</x-ui.badge>
                            @endif
                        </x-ui.card>
                    </a>
                @empty
                    <p class="col-span-full py-8 text-center text-sm text-muted-foreground">{{ __('No approvals tracked yet.') }}</p>
                @endforelse
            </div>
        @elseif ($tab === 'estimates')
            <livewire:estimation.project-estimates-tab :project="$project" :key="'estimates-'.$project->id" />
        @elseif ($tab === 'activities')
            @if ($canLog)
                <x-ui.button class="h-11 self-start md:h-9" x-on:click="$dispatch('crm-log-activity', { subjectType: 'project', subjectId: {{ $project->id }}, mode: 'log' })"><x-lucide-plus /> {{ __('Log activity') }}</x-ui.button>
            @endif
            @include('livewire.crm.activities.partials.timeline', ['activities' => $activities, 'showSubject' => false])
        @elseif ($tab === 'documents')
            <livewire:foundation.attachments :model="$project" :key="'attachments-'.$project->id" />
        @elseif ($tab === 'notes')
            <livewire:foundation.notes :model="$project" :key="'notes-'.$project->id" />
        @else
            <x-ui.card class="p-4 md:p-6">
                <h2 class="text-base font-semibold">{{ __('Status history') }}</h2>
                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ($statusHistory as $entry)
                        <li class="flex flex-wrap gap-x-2" wire:key="status-history-{{ $entry->id }}">
                            <span class="tabular-nums text-muted-foreground">{{ $entry->changed_at->format('d-M-Y h:i A') }}</span>
                            <span>
                                @if ($entry->from_status_id !== $entry->to_status_id)
                                    {{ $entry->fromStatus?->name ?? __('New') }} → {{ $entry->toStatus?->name }}
                                @endif
                                @if ($entry->from_phase_id !== $entry->to_phase_id)
                                    · {{ __('Phase') }}: {{ $entry->fromPhase?->name ?? '—' }} → {{ $entry->toPhase?->name ?? '—' }}
                                @endif
                            </span>
                            @if ($entry->changer)<span class="text-muted-foreground">{{ $entry->changer->name }}</span>@endif
                            @if ($entry->reason)<span class="w-full text-muted-foreground">{{ $entry->reason }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
            <livewire:foundation.history :model="$project" :key="'history-'.$project->id" />
        @endif
    </div>

    @if ($primary)
        <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t bg-background px-4 py-3 md:hidden">
            <x-ui.button class="h-11 w-full" :href="$primary['href']" wire:navigate><x-dynamic-component :component="'lucide-'.$primary['icon']" /> {{ $primary['label'] }}</x-ui.button>
        </div>
    @endif

    <x-shell.sheet id="project-actions" :title="__('Project actions')" :description="$project->project_number.' · '.$project->name">
        <div class="flex flex-col gap-2 pb-4">
            @foreach ($actions as $action)
                @php($tone = ($action['destructive'] ?? false) ? 'text-destructive' : '')
                @if (isset($action['href']) && ($action['external'] ?? false))
                    <x-ui.button class="h-11 justify-start {{ $tone }}" variant="outline" :href="$action['href']" target="_blank"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @elseif (isset($action['href']))
                    <x-ui.button class="h-11 justify-start {{ $tone }}" variant="outline" :href="$action['href']" wire:navigate><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @else
                    <x-ui.button class="h-11 justify-start {{ $tone }}" variant="outline" x-on:click="$dispatch('close-sheet-project-actions'); {{ $action['click'] }}"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
                @endif
            @endforeach
        </div>
    </x-shell.sheet>

    @can('changeStatus', $project)
        <x-shell.sheet id="project-status" :title="__('Change status')" :description="__('Move :number to another status or phase.', ['number' => $project->project_number])">
            <form id="project-status-form" wire:submit="changeStatus" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="status-target">{{ __('Status') }}</x-ui.field-label>
                    <x-ui.select native id="status-target" wire:model.live="statusForm.project_status_id" class="{{ $input }}">
                        <option value="{{ $project->project_status_id }}">{{ $project->status->name }} ({{ __('current') }})</option>
                        @foreach ($targets as $target)
                            <option value="{{ $target->id }}">{{ $target->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('statusForm.project_status_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="status-phase">{{ __('Phase') }}</x-ui.field-label>
                    <x-lookup-select table="project_phases" :include="$project->project_phase_id" :placeholder="__('None')" id="status-phase" wire:model="statusForm.project_phase_id" />
                    <x-ui.field-error :messages="$errors->get('statusForm.project_phase_id')" />
                </x-ui.field>

                @if ($targetCode === 'ON_HOLD')
                    <x-ui.field>
                        <x-ui.field-label for="status-hold">{{ __('Hold reason') }} *</x-ui.field-label>
                        <x-lookup-select table="hold_reasons" :placeholder="__('Choose…')" id="status-hold" wire:model="statusForm.hold_reason_id" />
                        <x-ui.field-error :messages="$errors->get('statusForm.hold_reason_id')" />
                    </x-ui.field>
                @elseif ($targetCode === 'CANCELLED')
                    <x-ui.field>
                        <x-ui.field-label for="status-cancel">{{ __('Cancel reason') }} *</x-ui.field-label>
                        <x-ui.textarea id="status-cancel" wire:model="statusForm.cancel_reason" rows="2" class="text-base md:text-sm" />
                        <x-ui.field-error :messages="$errors->get('statusForm.cancel_reason')" />
                    </x-ui.field>
                @elseif ($targetCode === 'HANDED_OVER')
                    <x-ui.field>
                        <x-ui.field-label for="status-handover">{{ __('Handover date') }} *</x-ui.field-label>
                        <x-ui.input type="date" id="status-handover" wire:model="statusForm.handover_date" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('statusForm.handover_date')" />
                    </x-ui.field>
                @elseif ($targetCode === 'COMPLETED')
                    <x-ui.field>
                        <x-ui.field-label for="status-end">{{ __('Actual end date') }} *</x-ui.field-label>
                        <x-ui.input type="date" id="status-end" wire:model="statusForm.actual_end_date" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('statusForm.actual_end_date')" />
                    </x-ui.field>
                    <p class="text-sm text-muted-foreground">{{ __('All tasks must be done or cancelled and all approvals final.') }}</p>
                    @if ($errors->has('statusForm.completion'))
                        <x-ui.alert tone="danger">
                            <x-lucide-circle-alert />
                            <x-ui.alert-title>{{ __('The project cannot be completed yet') }}</x-ui.alert-title>
                            <x-ui.alert-description>
                                <ul class="list-disc ps-4">
                                    @foreach ($errors->get('statusForm.completion') as $problem)
                                        <li>{{ $problem }}</li>
                                    @endforeach
                                </ul>
                            </x-ui.alert-description>
                        </x-ui.alert>
                    @endif
                    @if ($user->can('close', $project) && $user->can('projects.projects.view_all'))
                        <x-ui.field>
                            <x-ui.field-label for="status-override">{{ __('Override reason (management)') }}</x-ui.field-label>
                            <x-ui.textarea id="status-override" wire:model="statusForm.override_reason" rows="2" class="text-base md:text-sm" />
                        </x-ui.field>
                    @endif
                @endif

                <x-ui.field>
                    <x-ui.field-label for="status-reason">{{ $project->status->is_closed ? __('Why reopen?') : __('Note') }}{{ $project->status->is_closed ? ' *' : '' }}</x-ui.field-label>
                    <x-ui.textarea id="status-reason" wire:model="statusForm.reason" rows="2" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('statusForm.reason')" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-project-status')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="project-status-form">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endcan

    @can('delete', $project)
        <x-shell.confirm id="project-delete" :title="__('Delete project?')" :description="__('Only an empty enquiry can be deleted. Its number is not reused.')">
            <x-ui.field-error :messages="$errors->get('project')" />
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-project-delete')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="destructive" wire:click="deleteProject">{{ __('Delete') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.confirm>
    @endcan

    <x-shell.confirm id="activity-delete" :title="__('Delete activity?')" :description="__('The activity is removed from the timeline.')">
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-activity-delete')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button variant="destructive" wire:click="deleteActivity">{{ __('Delete') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.confirm>
</div>
