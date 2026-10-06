<div>
    <x-shell.list
        :search-placeholder="__('Search code, name, phone or email')"
        :create-url="auth()->user()->can('create', \App\Modules\Hrm\Models\Employee::class) ? route('hrm.employees.create') : null"
        :create-label="__('New employee')"
        :exportable="auth()->user()->can('hrm.employees.export')"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-status">{{ __('Status') }}</x-ui.field-label>
                <x-ui.select native id="filter-status" wire:model.live="filters.status" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Active employment') }}</option>
                    <option value="all">{{ __('All') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-department">{{ __('Department') }}</x-ui.field-label>
                <x-lookup-select table="departments" :placeholder="__('All departments')" id="filter-department" wire:model.live="filters.department" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-designation">{{ __('Designation') }}</x-ui.field-label>
                <x-lookup-select table="designations" :placeholder="__('All designations')" id="filter-designation" wire:model.live="filters.designation" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-type">{{ __('Type') }}</x-ui.field-label>
                <x-lookup-select table="employee_types" :placeholder="__('All types')" id="filter-type" wire:model.live="filters.type" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-branch">{{ __('Branch') }}</x-ui.field-label>
                <x-lookup-select table="branches" :placeholder="__('All branches')" id="filter-branch" wire:model.live="filters.branch" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-manager">{{ __('Manager') }}</x-ui.field-label>
                <x-ui.select native id="filter-manager" wire:model.live="filters.manager" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Anyone') }}</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}">{{ $manager->full_name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
        </x-slot:filters>

        <x-slot:actions>
            <x-ui.segmented-control name="employee-view" wire:model.live="view" :value="$view"
                :options="[['value' => 'list', 'label' => __('List')], ['value' => 'cards', 'label' => __('Cards')]]" />
        </x-slot:actions>

        <x-slot:desktop>
            @if ($view === 'cards')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($rows as $employee)
                        <a data-detail-modal href="{{ route('hrm.employees.show', $employee) }}" wire:navigate wire:key="card-{{ $employee->id }}" class="rounded-xl focus-visible:outline-2 focus-visible:outline-ring">
                            <x-ui.card class="h-full flex-row items-center gap-4 p-4 transition-colors hover:bg-accent/50">
                                <x-employee-avatar :employee="$employee" class="size-12" />
                                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="truncate font-medium">{{ $employee->full_name }}</span>
                                    <span class="truncate text-sm text-muted-foreground">{{ $employee->designation->name }} · {{ $employee->department->name }}</span>
                                    <span class="text-sm tabular-nums text-muted-foreground">{{ $employee->phone }}</span>
                                </div>
                                <x-ui.badge :tone="$employee->status->color ?? 'neutral'" class="shrink-0">{{ $employee->status->name }}</x-ui.badge>
                            </x-ui.card>
                        </a>
                    @empty
                        <p class="col-span-full py-10 text-center text-muted-foreground">{{ __('No employees found.') }}</p>
                    @endforelse
                </div>
            @else
                <x-shell.bulk-bar :exportable="auth()->user()->can('hrm.employees.export')" :deletable="auth()->user()->can('hrm.employees.delete')" />

                <x-ui.table variant="bordered">
                    <x-ui.table-header>
                        <x-ui.table-row>
                            <x-shell.select-all :ids="$rows->pluck('id')" />
                            <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                            <x-ui.table-head class="w-12"><span class="sr-only">{{ __('Photo') }}</span></x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="code" :label="__('Code')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head>{{ __('Designation') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Department') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Phone') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Email') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                        </x-ui.table-row>
                    </x-ui.table-header>
                    <x-ui.table-body>
                        @forelse ($rows as $employee)
                            <x-ui.table-row wire:key="employee-{{ $employee->id }}">
                                <x-shell.select-row :id="$employee->id" :label="$employee->full_name" />
                                <x-shell.row-menu>
                                    <x-shell.row-menu-item icon="eye" data-detail-modal :href="route('hrm.employees.show', $employee)">{{ __('View') }}</x-shell.row-menu-item>
                                    @can('update', $employee)
                                        <x-shell.row-menu-item icon="pencil" data-detail-modal :href="route('hrm.employees.edit', $employee)">{{ __('Edit') }}</x-shell.row-menu-item>
                                    @endcan
                                    @can('delete', $employee)
                                        <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $employee->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $employee->full_name]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                                    @endcan
                                </x-shell.row-menu>
                                <x-ui.table-cell><x-employee-avatar :employee="$employee" class="size-8" /></x-ui.table-cell>
                                <x-ui.table-cell class="font-mono text-sm">{{ $employee->employee_code }}</x-ui.table-cell>
                                <x-ui.table-cell class="font-medium">
                                    <a data-detail-modal href="{{ route('hrm.employees.show', $employee) }}" wire:navigate class="hover:underline">{{ $employee->full_name }}</a>
                                </x-ui.table-cell>
                                <x-ui.table-cell>{{ $employee->designation->name }}</x-ui.table-cell>
                                <x-ui.table-cell>{{ $employee->department->name }}</x-ui.table-cell>
                                <x-ui.table-cell class="tabular-nums">{{ $employee->phone }}</x-ui.table-cell>
                                <x-ui.table-cell class="max-w-48 truncate">{{ $employee->official_email ?? '—' }}</x-ui.table-cell>
                                <x-ui.table-cell><x-ui.badge :tone="$employee->status->color ?? 'neutral'">{{ $employee->status->name }}</x-ui.badge></x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell colspan="10" class="py-10 text-center text-muted-foreground">{{ __('No employees found.') }}</x-ui.table-cell>
                            </x-ui.table-row>
                        @endforelse
                    </x-ui.table-body>
                </x-ui.table>
            @endif

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
            @forelse ($mobileRows as $employee)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-employee-{{ $employee->id }}"
                    :href="route('hrm.employees.show', $employee)" wire:navigate>
                    <x-employee-avatar :employee="$employee" />
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base"><span class="truncate">{{ $employee->full_name }}</span></x-ui.item-title>
                        <x-ui.item-description class="truncate text-sm">{{ $employee->designation->name }} · {{ $employee->department->name }}</x-ui.item-description>
                    </x-ui.item-content>
                    <x-ui.badge :tone="$employee->status->color ?? 'neutral'" class="shrink-0 text-sm">{{ $employee->status->name }}</x-ui.badge>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No employees found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
