@php
    $user = auth()->user();
    $presets = [
        'my_open' => __('My open leads'), 'today' => __("Today's follow-ups"), 'overdue' => __('Overdue follow-ups'),
        'unassigned' => __('Unassigned'), 'won_month' => __('Won this month'), 'lost_month' => __('Lost this month'),
    ];
    $openStatuses = $statuses->where('is_closed', false);
    $isStale = fn ($lead) => ! $lead->status->is_closed && ($lead->last_activity_at ?? $lead->created_at)->lt($staleCutoff);
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2">
        <div class="-mx-4 flex flex-1 gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
            @foreach ($presets as $key => $label)
                <x-ui.button variant="outline" size="sm" class="h-11 shrink-0 md:h-8" wire:click="applyPreset('{{ $key }}')">{{ $label }}</x-ui.button>
            @endforeach
        </div>
        <x-ui.segmented-control name="lead-view" wire:model.live="view" :value="$view" class="hidden md:inline-flex"
            :options="[['value' => 'list', 'label' => __('List'), 'icon' => 'list'], ['value' => 'kanban', 'label' => __('Kanban'), 'icon' => 'kanban']]" />
    </div>

    <x-shell.list
        :search-placeholder="__('Search number, name, phone or email')"
        :create-url="Route::has('crm.leads.create') && $user->can('create', \App\Modules\Crm\Models\Lead::class) ? route('crm.leads.create') : null"
        :create-label="__('New lead')"
        :exportable="$user->can('crm.leads.export')"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled')) + count($statusFilter)"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-state">{{ __('State') }}</x-ui.field-label>
                <x-ui.select native id="filter-state" wire:model.live="filters.state" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Open') }}</option>
                    <option value="won">{{ __('Won') }}</option>
                    <option value="lost">{{ __('Lost') }}</option>
                    <option value="all">{{ __('All') }}</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field-set>
                <x-ui.field-legend class="text-sm">{{ __('Status') }}</x-ui.field-legend>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($statuses as $status)
                        <x-ui.field orientation="horizontal" class="min-h-11 items-center md:min-h-0" wire:key="sf-{{ $status->id }}">
                            <x-ui.checkbox native id="sf-{{ $status->id }}" wire:model.live="statusFilter" value="{{ $status->id }}" />
                            <x-ui.field-label for="sf-{{ $status->id }}" class="font-normal">{{ $status->name }}</x-ui.field-label>
                        </x-ui.field>
                    @endforeach
                </div>
            </x-ui.field-set>
            @foreach (['source' => ['lead_sources', __('Source')], 'business_line' => ['business_lines', __('Business line')], 'level' => ['lead_levels', __('Level')], 'priority' => ['lead_priorities', __('Priority')]] as $key => [$table, $label])
                <x-ui.field>
                    <x-ui.field-label for="filter-{{ $key }}">{{ $label }}</x-ui.field-label>
                    <x-lookup-select :table="$table" :placeholder="__('Any')" id="filter-{{ $key }}" wire:model.live="filters.{{ $key }}" />
                </x-ui.field>
            @endforeach
            <x-ui.field>
                <x-ui.field-label for="filter-service">{{ __('Service') }}</x-ui.field-label>
                <x-ui.select native id="filter-service" wire:model.live="filters.service" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Any') }}</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-assigned">{{ __('Assigned to') }}</x-ui.field-label>
                <x-ui.select native id="filter-assigned" wire:model.live="filters.assigned" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Anyone') }}</option>
                    <option value="me">{{ __('Me') }}</option>
                    <option value="none">{{ __('Unassigned') }}</option>
                    @foreach ($assignees as $assignee)
                        @continue($assignee->id === $user->id)
                        <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-team">{{ __('Team') }}</x-ui.field-label>
                <x-ui.select native id="filter-team" wire:model.live="filters.team" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Any') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-location">{{ __('Location') }}</x-ui.field-label>
                <x-location-select id="filter-location" wire:model.live="filters.location" :placeholder="__('Anywhere')" />
            </x-ui.field>
            <div class="grid grid-cols-2 gap-2">
                <x-ui.field>
                    <x-ui.field-label for="filter-from">{{ __('Lead date from') }}</x-ui.field-label>
                    <x-ui.input type="date" id="filter-from" wire:model.live="filters.date_from" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="filter-to">{{ __('to') }}</x-ui.field-label>
                    <x-ui.input type="date" id="filter-to" wire:model.live="filters.date_to" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="filter-follow-up">{{ __('Follow-up') }}</x-ui.field-label>
                <x-ui.select native id="filter-follow-up" wire:model.live="filters.follow_up" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Any') }}</option>
                    <option value="today">{{ __('Today') }}</option>
                    <option value="overdue">{{ __('Overdue') }}</option>
                    <option value="none">{{ __('None') }}</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-converted">{{ __('Converted') }}</x-ui.field-label>
                <x-ui.select native id="filter-converted" wire:model.live="filters.converted" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Any') }}</option>
                    <option value="1">{{ __('Yes') }}</option>
                    <option value="0">{{ __('No') }}</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                <x-ui.checkbox native id="filter-stale" wire:model.live="filters.stale" value="1" />
                <x-ui.field-label for="filter-stale" class="font-normal">{{ __('Only stale leads') }}</x-ui.field-label>
            </x-ui.field>
        </x-slot:filters>

        <x-slot:desktop>
            @if ($view === 'kanban')
                <div class="flex gap-4 overflow-x-auto pb-2">
                    @foreach ($columns as $column)
                        <section class="flex w-72 shrink-0 flex-col gap-2 rounded-lg bg-muted/50 p-2" wire:key="col-{{ $column['id'] }}">
                            <header class="flex items-center justify-between px-1">
                                <span class="font-medium">{{ $column['name'] }} <span class="text-muted-foreground">{{ $column['count'] }}</span></span>
                                <span class="text-sm tabular-nums text-muted-foreground">{{ \App\Support\Money::format($column['total'], false) }}</span>
                            </header>
                            <div class="flex min-h-24 flex-col gap-2" wire:sort="moveLead" wire:sort:group="pipeline" wire:sort:group-id="{{ $column['id'] }}">
                                @foreach ($column['leads'] as $lead)
                                    <x-ui.card class="cursor-grab gap-1 p-3 active:cursor-grabbing" wire:key="card-{{ $lead->id }}" wire:sort:item="{{ $lead->id }}">
                                        <a data-detail-modal href="{{ route('crm.leads.show', $lead) }}" wire:navigate class="font-medium hover:underline">{{ $lead->name }}</a>
                                        @if ($lead->company_name)<p class="text-sm text-muted-foreground">{{ $lead->company_name }}</p>@endif
                                        <p class="text-sm tabular-nums">{{ $lead->phone }}</p>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($lead->services->take(3) as $line)
                                                <x-ui.badge variant="secondary" class="text-xs">{{ $line->service->name }}</x-ui.badge>
                                            @endforeach
                                        </div>
                                        <div class="flex items-center justify-between gap-2 text-sm">
                                            <span class="tabular-nums">{{ $lead->expected_value ? \App\Support\Money::format($lead->expected_value, false) : '—' }}</span>
                                            <span @class(['tabular-nums', 'text-destructive' => $lead->next_follow_up_at?->isPast()])>{{ $lead->next_follow_up_at?->format('d M, h:i A') }}</span>
                                            <x-ui.avatar class="size-6"><x-ui.avatar-fallback class="text-xs">{{ $lead->assignee?->initials() ?? '—' }}</x-ui.avatar-fallback></x-ui.avatar>
                                        </div>
                                    </x-ui.card>
                                @endforeach
                            </div>
                            @if ($column['count'] > count($column['leads']))
                                <p class="px-1 text-sm text-muted-foreground">{{ __('+ :n more — use the list view', ['n' => $column['count'] - count($column['leads'])]) }}</p>
                            @endif
                        </section>
                    @endforeach
                </div>
            @else
                <x-shell.bulk-bar :exportable="$user->can('crm.leads.export')" :deletable="$user->can('crm.leads.delete')">
                    @can('crm.leads.assign')
                        <x-ui.select native wire:model="bulkAssigneeId" class="h-8 w-48" :aria-label="__('Assign to')">
                            <option value="">{{ __('Unassigned') }}</option>
                            @foreach ($assignees as $assignee)
                                <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.button size="sm" variant="outline" wire:click="bulkAssign">{{ __('Assign') }}</x-ui.button>
                    @endcan
                    @can('crm.leads.update')
                        <x-ui.select native wire:model="bulkStatusId" class="h-8 w-48" :aria-label="__('New status')">
                            <option value="">{{ __('Choose status…') }}</option>
                            @foreach ($openStatuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.button size="sm" variant="outline" wire:click="bulkChangeStatus">{{ __('Change status') }}</x-ui.button>
                        @endcan
                </x-shell.bulk-bar>

                <div class="overflow-x-auto">
                    <x-ui.table variant="bordered">
                        <x-ui.table-header>
                            <x-ui.table-row>
                                <x-shell.select-all :ids="$rows->pluck('id')" />
                                <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                                <x-ui.table-head><x-shell.sort-header key="number" :label="__('Lead #')" :$sort :$direction /></x-ui.table-head>
                                <x-ui.table-head><x-shell.sort-header key="date" :label="__('Date')" :$sort :$direction /></x-ui.table-head>
                                <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                                <x-ui.table-head>{{ __('Company') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Phone') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Source') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Business line') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Services') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Level') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Priority') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Assigned to') }}</x-ui.table-head>
                                <x-ui.table-head>{{ __('Team') }}</x-ui.table-head>
                                <x-ui.table-head class="text-end"><x-shell.sort-header key="value" :label="__('Expected value')" :$sort :$direction /></x-ui.table-head>
                                <x-ui.table-head><x-shell.sort-header key="follow_up" :label="__('Next follow-up')" :$sort :$direction /></x-ui.table-head>
                                <x-ui.table-head><x-shell.sort-header key="activity" :label="__('Last activity')" :$sort :$direction /></x-ui.table-head>
                                <x-ui.table-head class="text-end">{{ __('Age') }}</x-ui.table-head>
                            </x-ui.table-row>
                        </x-ui.table-header>
                        <x-ui.table-body>
                            @forelse ($rows as $lead)
                                <x-ui.table-row wire:key="lead-{{ $lead->id }}">
                                    <x-shell.select-row :id="$lead->id" :label="$lead->name" />
                                    <x-shell.row-menu>
                                        <x-shell.row-menu-item icon="eye" data-detail-modal :href="route('crm.leads.show', $lead)">{{ __('View') }}</x-shell.row-menu-item>
                                        @can('update', $lead)
                                            <x-shell.row-menu-item icon="pencil" data-detail-modal :href="route('crm.leads.edit', $lead)">{{ __('Edit') }}</x-shell.row-menu-item>
                                        @endcan
                                        @can('delete', $lead)
                                            <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $lead->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $lead->name]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                                        @endcan
                                    </x-shell.row-menu>
                                    <x-ui.table-cell class="font-mono text-sm"><a data-detail-modal href="{{ route('crm.leads.show', $lead) }}" wire:navigate class="hover:underline">{{ $lead->lead_number }}</a></x-ui.table-cell>
                                    <x-ui.table-cell class="whitespace-nowrap tabular-nums">{{ $lead->lead_date->format('d-M-Y') }}</x-ui.table-cell>
                                    <x-ui.table-cell class="font-medium">
                                        <a data-detail-modal href="{{ route('crm.leads.show', $lead) }}" wire:navigate class="hover:underline">{{ $lead->name }}</a>
                                        @if ($isStale($lead))
                                            <x-ui.badge tone="warning" class="ms-1 text-sm">{{ __('Stale') }}</x-ui.badge>
                                        @endif
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>{{ $lead->company_name ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell class="whitespace-nowrap tabular-nums">{{ $lead->phone }}</x-ui.table-cell>
                                    <x-ui.table-cell>{{ $lead->source->name }}</x-ui.table-cell>
                                    <x-ui.table-cell>{{ $lead->businessLine?->name ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($lead->services->take(2) as $line)
                                                <x-ui.badge variant="secondary" class="text-sm">{{ $line->service->name }}</x-ui.badge>
                                            @endforeach
                                            @if ($lead->services->count() > 2)
                                                <x-ui.badge variant="outline" class="text-sm">+{{ $lead->services->count() - 2 }}</x-ui.badge>
                                            @endif
                                        </div>
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>{{ $lead->level?->name ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell><x-ui.badge :tone="$lead->status->color ?? 'info'">{{ $lead->status->name }}</x-ui.badge></x-ui.table-cell>
                                    <x-ui.table-cell><x-ui.badge :tone="$lead->priority->color ?? 'neutral'">{{ $lead->priority->name }}</x-ui.badge></x-ui.table-cell>
                                    <x-ui.table-cell>{{ $lead->assignee?->name ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell>{{ $lead->team?->name ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell class="text-end tabular-nums">{{ $lead->expected_value !== null ? \App\Support\Money::format($lead->expected_value, false) : '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell @class(['whitespace-nowrap tabular-nums', 'text-destructive' => $lead->next_follow_up_at?->isPast()])>{{ $lead->next_follow_up_at?->format('d-M-Y h:i A') ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell class="whitespace-nowrap tabular-nums">{{ $lead->last_activity_at?->format('d-M-Y') ?? '—' }}</x-ui.table-cell>
                                    <x-ui.table-cell class="text-end tabular-nums">{{ (int) $lead->lead_date->diffInDays(today()) }}</x-ui.table-cell>
                                </x-ui.table-row>
                            @empty
                                <x-ui.table-row>
                                    <x-ui.table-cell colspan="19" class="py-10 text-center text-muted-foreground">{{ __('No leads found.') }}</x-ui.table-cell>
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
            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                <x-ui.button size="sm" :variant="$statusFilter === [] ? 'default' : 'outline'" class="h-11 shrink-0" wire:click="$set('statusFilter', [])">
                    {{ __('All open') }} <span class="tabular-nums">{{ array_sum($statusCounts) }}</span>
                </x-ui.button>
                @foreach ($openStatuses as $status)
                    <x-ui.button size="sm" :variant="$statusFilter === [(string) $status->id] ? 'default' : 'outline'" class="h-11 shrink-0"
                        wire:click="$set('statusFilter', ['{{ $status->id }}'])" wire:key="chip-{{ $status->id }}">
                        {{ $status->name }} <span class="tabular-nums">{{ $statusCounts[$status->id] ?? 0 }}</span>
                    </x-ui.button>
                @endforeach
            </div>

            @forelse ($mobileRows as $lead)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-lead-{{ $lead->id }}" :href="route('crm.leads.show', $lead)" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="flex items-center gap-2 text-base">
                            <span class="truncate">{{ $lead->name }}</span>
                            @if ($isStale($lead))
                                <x-ui.badge tone="warning" class="text-sm">{{ __('Stale') }}</x-ui.badge>
                            @endif
                        </x-ui.item-title>
                        <x-ui.item-description class="flex flex-wrap items-center gap-x-2 text-sm">
                            <span class="truncate">{{ $lead->company_name ?? $lead->phone }}</span>
                            @if ($lead->next_follow_up_at)
                                <span @class(['tabular-nums', 'text-destructive' => $lead->next_follow_up_at->isPast()])>{{ $lead->next_follow_up_at->format('d M, h:i A') }}</span>
                            @endif
                        </x-ui.item-description>
                    </x-ui.item-content>
                    <x-ui.badge :tone="$lead->status->color ?? 'info'" class="shrink-0 text-sm">{{ $lead->status->name }}</x-ui.badge>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No leads found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
