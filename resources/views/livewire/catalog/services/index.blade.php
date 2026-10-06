<div>
    <x-shell.list
        :search-placeholder="__('Search code or name')"
        :create-url="auth()->user()->can('catalog.services.create') ? route('catalog.services.create') : null"
        :create-label="__('New service')"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
        @can('catalog.master_data.view')
            <x-slot:actions>
                <x-ui.button variant="outline" :href="route('admin.master-data.show', 'service_categories')" wire:navigate class="max-md:size-11 max-md:px-0" :aria-label="__('Categories')">
                    <x-lucide-tags /> <span class="max-md:sr-only">{{ __('Categories') }}</span>
                </x-ui.button>
            </x-slot:actions>
        @endcan

        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-category">{{ __('Category') }}</x-ui.field-label>
                <x-lookup-select table="service_categories" :placeholder="__('All categories')" id="filter-category" wire:model.live="filters.category" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-business-line">{{ __('Business line') }}</x-ui.field-label>
                <x-lookup-select table="business_lines" :placeholder="__('All business lines')" id="filter-business-line" wire:model.live="filters.business_line" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-active">{{ __('Status') }}</x-ui.field-label>
                <x-ui.select native id="filter-active" wire:model.live="filters.active" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="1">{{ __('Active') }}</option>
                    <option value="0">{{ __('Inactive') }}</option>
                </x-ui.select>
            </x-ui.field>
        </x-slot:filters>

        <x-slot:desktop>
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head><x-shell.sort-header key="code" :label="__('Code')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Category') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Business line') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Pricing') }}</x-ui.table-head>
                        <x-ui.table-head class="text-end"><x-shell.sort-header key="default_rate" :label="__('Default rate')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Active') }}</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $service)
                        <x-ui.table-row wire:key="service-{{ $service->id }}">
                            <x-ui.table-cell class="font-mono text-sm">{{ $service->code }}</x-ui.table-cell>
                            <x-ui.table-cell class="font-medium">
                                @can('catalog.services.update')
                                    <a href="{{ route('catalog.services.edit', $service) }}" wire:navigate class="hover:underline">{{ $service->name }}</a>
                                @else
                                    {{ $service->name }}
                                @endcan
                            </x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :tone="$service->category->color ?? 'neutral'">{{ $service->category->name }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell>{{ $service->businessLine?->name ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $service->pricingBasis->name }}@if ($service->defaultUnit) <span class="text-muted-foreground">/ {{ $service->defaultUnit->symbol }}</span>@endif</x-ui.table-cell>
                            <x-ui.table-cell class="text-end tabular-nums">{{ \App\Support\Money::formatRate($service->default_rate) }}</x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :tone="$service->is_active ? 'success' : 'neutral'">{{ $service->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="7" class="py-10 text-center text-muted-foreground">{{ __('No services found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            <div class="mt-4 flex items-center justify-between gap-4">
                <x-ui.select native wire:model.live="perPage" class="w-36" :aria-label="__('Rows per page')">
                    @foreach (static::PER_PAGE_OPTIONS as $option)
                        <option value="{{ $option }}">{{ __(':count per page', ['count' => $option]) }}</option>
                    @endforeach
                </x-ui.select>
                {{ $rows->links() }}
            </div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $service)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-service-{{ $service->id }}"
                    :href="auth()->user()->can('catalog.services.update') ? route('catalog.services.edit', $service) : null" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="flex items-center gap-2 text-base">
                            <span class="truncate">{{ $service->name }}</span>
                            @unless ($service->is_active)
                                <x-ui.badge tone="neutral" class="text-sm">{{ __('Inactive') }}</x-ui.badge>
                            @endunless
                        </x-ui.item-title>
                        <x-ui.item-description class="flex items-center gap-2 text-sm">
                            <span class="font-mono">{{ $service->code }}</span>
                            <x-ui.badge :tone="$service->category->color ?? 'neutral'" class="text-sm">{{ $service->category->name }}</x-ui.badge>
                        </x-ui.item-description>
                    </x-ui.item-content>
                    <span class="shrink-0 text-sm tabular-nums">{{ \App\Support\Money::formatRate($service->default_rate) }}</span>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No services found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
