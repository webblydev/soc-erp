<div>
    <x-shell.list
        :search-placeholder="__('Search number, name, phone or email')"
        :create-url="auth()->user()->can('create', \App\Modules\Crm\Models\Customer::class) ? route('crm.customers.create') : null"
        :create-label="__('New customer')"
        :exportable="auth()->user()->can('crm.customers.export')"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-type">{{ __('Type') }}</x-ui.field-label>
                <x-lookup-select table="customer_types" :placeholder="__('All types')" id="filter-type" wire:model.live="filters.type" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-status">{{ __('Status') }}</x-ui.field-label>
                <x-lookup-select table="customer_statuses" :placeholder="__('All statuses')" id="filter-status" wire:model.live="filters.status" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-manager">{{ __('Account manager') }}</x-ui.field-label>
                <x-ui.select native id="filter-manager" wire:model.live="filters.manager" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Anyone') }}</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-business-line">{{ __('Business line') }}</x-ui.field-label>
                <x-lookup-select table="business_lines" :placeholder="__('All business lines')" id="filter-business-line" wire:model.live="filters.business_line" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-source">{{ __('Source') }}</x-ui.field-label>
                <x-lookup-select table="lead_sources" :placeholder="__('All sources')" id="filter-source" wire:model.live="filters.source" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-location">{{ __('Location') }}</x-ui.field-label>
                <x-location-select id="filter-location" wire:model.live="filters.location" :placeholder="__('Anywhere')" />
            </x-ui.field>
        </x-slot:filters>

        <x-slot:desktop>
            <x-shell.bulk-bar :exportable="auth()->user()->can('crm.customers.export')" :deletable="auth()->user()->can('crm.customers.delete')">
                @can('crm.customers.update')
                    <x-ui.select native wire:model="bulkManagerId" class="h-8 w-56" :aria-label="__('New account manager')">
                        <option value="">{{ __('No account manager') }}</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.button size="sm" variant="outline" wire:click="bulkChangeManager">{{ __('Change account manager') }}</x-ui.button>
                @endcan
            </x-shell.bulk-bar>

            <x-ui.table variant="bordered">
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-shell.select-all :ids="$rows->pluck('id')" />
                        <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="number" :label="__('Customer #')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Company') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Type') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Phone') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Location') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Account manager') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $customer)
                        <x-ui.table-row wire:key="customer-{{ $customer->id }}">
                            <x-shell.select-row :id="$customer->id" :label="$customer->name" />
                            <x-shell.row-menu>
                                <x-shell.row-menu-item icon="eye" data-detail-modal :href="route('crm.customers.show', $customer)">{{ __('View') }}</x-shell.row-menu-item>
                                @can('update', $customer)
                                    <x-shell.row-menu-item icon="pencil" data-detail-modal :href="route('crm.customers.edit', $customer)">{{ __('Edit') }}</x-shell.row-menu-item>
                                @endcan
                                @can('delete', $customer)
                                    <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $customer->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $customer->name]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                                @endcan
                            </x-shell.row-menu>
                            <x-ui.table-cell class="font-mono text-sm">{{ $customer->customer_number }}</x-ui.table-cell>
                            <x-ui.table-cell class="font-medium">
                                <a data-detail-modal href="{{ route('crm.customers.show', $customer) }}" wire:navigate class="hover:underline">{{ $customer->name }}</a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $customer->company_name ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $customer->type->name }}</x-ui.table-cell>
                            <x-ui.table-cell class="tabular-nums">{{ $customer->phone }}</x-ui.table-cell>
                            <x-ui.table-cell class="max-w-48 truncate" title="{{ $customer->location?->full_path }}">{{ $customer->location?->full_path ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $customer->accountManager?->name ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :tone="$customer->status->color ?? 'neutral'">{{ $customer->status->name }}</x-ui.badge></x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="10" class="py-10 text-center text-muted-foreground">{{ __('No customers found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

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
            @forelse ($mobileRows as $customer)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-customer-{{ $customer->id }}"
                    :href="route('crm.customers.show', $customer)" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base"><span class="truncate">{{ $customer->name }}</span></x-ui.item-title>
                        <x-ui.item-description class="flex items-center gap-2 text-sm">
                            <span class="font-mono">{{ $customer->customer_number }}</span>
                            <span class="tabular-nums">{{ $customer->phone }}</span>
                        </x-ui.item-description>
                    </x-ui.item-content>
                    <x-ui.badge :tone="$customer->status->color ?? 'neutral'" class="shrink-0 text-sm">{{ $customer->status->name }}</x-ui.badge>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No customers found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
