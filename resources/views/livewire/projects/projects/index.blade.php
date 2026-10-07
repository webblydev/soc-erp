@php
    $user = auth()->user();
    $presets = [
        'mine' => __('My projects'), 'active' => __('Active'), 'on_hold' => __('On hold'),
        'approvals_pending' => __('Approvals pending'), 'handed_over' => __('Handed over, not completed'),
    ];
    $money = fn ($value) => \App\Support\Money::format($value, false);
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
@endphp

<div class="flex flex-col gap-4">
    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
        @foreach ($presets as $key => $label)
            <x-ui.button variant="outline" size="sm" class="h-11 shrink-0 md:h-8" wire:click="applyPreset('{{ $key }}')">{{ $label }}</x-ui.button>
        @endforeach
    </div>

    <x-shell.list
        :search-placeholder="__('Search number, name or site')"
        :create-url="$user->can('create', \App\Modules\Projects\Models\Project::class) ? route('projects.projects.create') : null"
        :create-label="__('New project')"
        :exportable="$user->can('projects.projects.export')"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
        :filter-columns="3"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-state">{{ __('State') }}</x-ui.field-label>
                <x-ui.select native id="filter-state" wire:model.live="filters.state" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="open">{{ __('Open') }}</option>
                    <option value="closed">{{ __('Completed or cancelled') }}</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-status">{{ __('Status') }}</x-ui.field-label>
                <x-ui.select native id="filter-status" wire:model.live="filters.status" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Any status') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-phase">{{ __('Phase') }}</x-ui.field-label>
                <x-lookup-select table="project_phases" :placeholder="__('Any phase')" id="filter-phase" wire:model.live="filters.phase" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-line">{{ __('Business line') }}</x-ui.field-label>
                <x-lookup-select table="business_lines" :placeholder="__('All business lines')" id="filter-line" wire:model.live="filters.business_line" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-type">{{ __('Type') }}</x-ui.field-label>
                <x-lookup-select table="project_types" :placeholder="__('All types')" id="filter-type" wire:model.live="filters.type" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-kind">{{ __('Internal / billable') }}</x-ui.field-label>
                <x-ui.select native id="filter-kind" wire:model.live="filters.kind" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Both') }}</option>
                    <option value="billable">{{ __('Billable') }}</option>
                    <option value="internal">{{ __('Internal') }}</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-pm">{{ __('Project manager') }}</x-ui.field-label>
                <x-ui.select native id="filter-pm" wire:model.live="filters.pm" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Anyone') }}</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}">{{ $manager->full_name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-customer">{{ __('Customer') }}</x-ui.field-label>
                <x-ui.select native id="filter-customer" wire:model.live="filters.customer" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Any customer') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-location">{{ __('Location') }}</x-ui.field-label>
                <x-location-select id="filter-location" wire:model.live="filters.location" :placeholder="__('Anywhere')" />
            </x-ui.field>
            <div class="grid grid-cols-2 gap-2">
                <x-ui.field>
                    <x-ui.field-label for="filter-from">{{ __('Start from') }}</x-ui.field-label>
                    <x-ui.input type="date" id="filter-from" wire:model.live="filters.start_from" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="filter-to">{{ __('Start to') }}</x-ui.field-label>
                    <x-ui.input type="date" id="filter-to" wire:model.live="filters.start_to" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
            </div>
            <div class="flex flex-col gap-2">
                @foreach (['overdue' => __('Has overdue tasks'), 'approvals' => __('Approvals pending'), 'pm_inactive' => __('PM no longer active'), 'mine' => __('Only my projects')] as $key => $label)
                    <x-ui.field orientation="horizontal" class="min-h-11 items-center md:min-h-0">
                        <x-ui.checkbox native id="filter-{{ $key }}" wire:model.live="filters.{{ $key }}" value="1" />
                        <x-ui.field-label for="filter-{{ $key }}" class="font-normal">{{ $label }}</x-ui.field-label>
                    </x-ui.field>
                @endforeach
            </div>
        </x-slot:filters>

        <x-slot:desktop>
            <x-shell.bulk-bar :exportable="$user->can('projects.projects.export')" :deletable="$user->can('projects.projects.delete')">
                @can('projects.projects.update')
                    <x-employee-select wire:model="bulkManagerId" class="h-8 w-56 md:h-8" :placeholder="__('New project manager…')" :aria-label="__('New project manager')" />
                    <x-ui.button size="sm" variant="outline" wire:click="bulkChangeManager">{{ __('Change PM') }}</x-ui.button>
                @endcan
            </x-shell.bulk-bar>

            <div class="overflow-x-auto">
                <x-ui.table variant="bordered">
                    <x-ui.table-header>
                        <x-ui.table-row>
                            <x-shell.select-all :ids="$rows->pluck('id')" />
                            <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="number" :label="__('Project #')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head>{{ __('Customer') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Business line') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Type') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Phase') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('PM') }}</x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="start" :label="__('Start')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="end" :label="__('Expected end')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head class="text-end"><x-shell.sort-header key="value" :label="__('Contract value')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head class="text-end">{{ __('Open tasks') }}</x-ui.table-head>
                            <x-ui.table-head class="text-end">{{ __('Overdue') }}</x-ui.table-head>
                            <x-ui.table-head class="text-end">{{ __('Approvals') }}</x-ui.table-head>
                        </x-ui.table-row>
                    </x-ui.table-header>
                    <x-ui.table-body>
                        @forelse ($rows as $project)
                            <x-ui.table-row wire:key="project-{{ $project->id }}">
                                <x-shell.select-row :id="$project->id" :label="$project->name" />
                                <x-shell.row-menu>
                                    <x-shell.row-menu-item icon="eye" :href="route('projects.projects.show', $project)">{{ __('View') }}</x-shell.row-menu-item>
                                    @can('update', $project)
                                        <x-shell.row-menu-item icon="pencil" data-detail-modal :href="route('projects.projects.edit', $project)">{{ __('Edit') }}</x-shell.row-menu-item>
                                    @endcan
                                    @can('delete', $project)
                                        <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $project->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $project->project_number]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                                    @endcan
                                </x-shell.row-menu>
                                <x-ui.table-cell class="font-mono text-sm whitespace-nowrap">
                                    <a href="{{ route('projects.projects.show', $project) }}" wire:navigate class="hover:underline">{{ $project->project_number }}</a>
                                </x-ui.table-cell>
                                <x-ui.table-cell class="max-w-64 truncate font-medium">{{ $project->name }}</x-ui.table-cell>
                                <x-ui.table-cell class="max-w-48 truncate">{{ $project->customer?->name ?? __('Internal') }}</x-ui.table-cell>
                                <x-ui.table-cell>{{ $project->businessLine->name }}</x-ui.table-cell>
                                <x-ui.table-cell>{{ $project->type->name }}</x-ui.table-cell>
                                <x-ui.table-cell><x-ui.badge :tone="$project->status->color ?? 'neutral'">{{ $project->status->name }}</x-ui.badge></x-ui.table-cell>
                                <x-ui.table-cell>{{ $project->phase?->name ?? '—' }}</x-ui.table-cell>
                                <x-ui.table-cell class="whitespace-nowrap">
                                    {{ $project->manager?->full_name ?? '—' }}
                                    @if ($project->manager && ! $project->manager->status->is_active_employment)
                                        <x-ui.badge tone="danger" class="ms-1">{{ __('Inactive') }}</x-ui.badge>
                                    @endif
                                </x-ui.table-cell>
                                <x-ui.table-cell class="tabular-nums whitespace-nowrap">{{ $date($project->start_date) }}</x-ui.table-cell>
                                <x-ui.table-cell class="tabular-nums whitespace-nowrap">{{ $date($project->expected_end_date) }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums">{{ $money($project->contract_value) }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums">{{ $project->open_tasks_count }}</x-ui.table-cell>
                                <x-ui.table-cell @class(['text-end tabular-nums', 'font-medium text-destructive' => $project->overdue_tasks_count > 0])>{{ $project->overdue_tasks_count }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums">{{ $project->pending_approvals_count }}</x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell colspan="16" class="py-10 text-center text-muted-foreground">{{ __('No projects found.') }}</x-ui.table-cell>
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
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $project)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-project-{{ $project->id }}"
                    :href="route('projects.projects.show', $project)" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base"><span class="truncate">{{ $project->name }}</span></x-ui.item-title>
                        <x-ui.item-description class="truncate text-sm">
                            <span class="font-mono">{{ $project->project_number }}</span> · {{ $project->customer?->name ?? __('Internal') }}
                        </x-ui.item-description>
                        <span class="text-sm tabular-nums">{{ \App\Support\Money::format($project->contract_value) }}</span>
                    </x-ui.item-content>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-ui.badge :tone="$project->status->color ?? 'neutral'" class="text-sm">{{ $project->status->name }}</x-ui.badge>
                        @if ($project->overdue_tasks_count > 0)
                            <span class="text-sm text-destructive">{{ trans_choice(':count overdue|:count overdue', $project->overdue_tasks_count) }}</span>
                        @endif
                    </div>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No projects found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
