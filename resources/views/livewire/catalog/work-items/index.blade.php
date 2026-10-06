<div>
    <x-shell.list
        :search-placeholder="__('Search code or name')"
        :create-url="auth()->user()->can('catalog.work_items.create') ? route('catalog.work-items.create') : null"
        :create-label="__('New work item')"
        :exportable="auth()->user()->can('catalog.work_items.export')"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
        <x-slot:actions>
            @can('catalog.master_data.view')
                <x-ui.button variant="outline" :href="route('admin.master-data.show', 'work_item_categories')" wire:navigate class="max-md:size-11 max-md:px-0" :aria-label="__('Categories')">
                    <x-lucide-tags /> <span class="max-md:sr-only">{{ __('Categories') }}</span>
                </x-ui.button>
            @endcan
            @if (Route::has('catalog.work-items.import'))
                @can('catalog.work_items.import')
                    <x-ui.button variant="outline" :href="route('catalog.work-items.import')" wire:navigate class="max-md:size-11 max-md:px-0" :aria-label="__('Import')">
                        <x-lucide-upload /> <span class="max-md:sr-only">{{ __('Import') }}</span>
                    </x-ui.button>
                @endcan
            @endif
        </x-slot:actions>

        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-category">{{ __('Category') }}</x-ui.field-label>
                <x-lookup-select table="work_item_categories" :placeholder="__('All categories')" id="filter-category" wire:model.live="filters.category" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-unit">{{ __('Unit') }}</x-ui.field-label>
                <x-lookup-select table="units" show-code :placeholder="__('All units')" id="filter-unit" wire:model.live="filters.unit" />
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
            <x-shell.bulk-bar :exportable="auth()->user()->can('catalog.work_items.export')" :deletable="auth()->user()->can('catalog.work_items.delete')" />

            <x-ui.table variant="bordered">
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-shell.select-all :ids="$rows->pluck('id')" />
                        <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="code" :label="__('Code')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Category') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Unit') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Formula') }}</x-ui.table-head>
                        <x-ui.table-head class="text-end"><x-shell.sort-header key="standard_rate" :label="__('Standard rate')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Active') }}</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $item)
                        <x-ui.table-row wire:key="work-item-{{ $item->id }}">
                            <x-shell.select-row :id="$item->id" :label="$item->name" />
                            <x-shell.row-menu>
                                @can('catalog.work_items.update')
                                    <x-shell.row-menu-item icon="pencil" data-detail-modal :href="route('catalog.work-items.edit', $item)">{{ __('Edit') }}</x-shell.row-menu-item>
                                @endcan
                                @can('catalog.work_items.delete')
                                    <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $item->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $item->name]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                                @endcan
                            </x-shell.row-menu>
                            <x-ui.table-cell class="font-mono text-sm">{{ $item->code }}</x-ui.table-cell>
                            <x-ui.table-cell class="font-medium">
                                @can('catalog.work_items.update')
                                    <a data-detail-modal href="{{ route('catalog.work-items.edit', $item) }}" wire:navigate class="hover:underline">{{ $item->name }}</a>
                                @else
                                    {{ $item->name }}
                                @endcan
                            </x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :tone="$item->category->color ?? 'neutral'">{{ $item->category->name }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell>{{ $item->unit->symbol }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $item->measurement_formula->label() }}</x-ui.table-cell>
                            <x-ui.table-cell class="text-end tabular-nums">{{ \App\Support\Money::formatRate($item->standard_rate) }}</x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :tone="$item->is_active ? 'success' : 'neutral'">{{ $item->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="9" class="py-10 text-center text-muted-foreground">{{ __('No work items found.') }}</x-ui.table-cell>
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
            @forelse ($mobileRows as $item)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-work-item-{{ $item->id }}"
                    :href="auth()->user()->can('catalog.work_items.update') ? route('catalog.work-items.edit', $item) : null" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="flex items-center gap-2 text-base">
                            <span class="truncate">{{ $item->name }}</span>
                            @unless ($item->is_active)
                                <x-ui.badge tone="neutral" class="text-sm">{{ __('Inactive') }}</x-ui.badge>
                            @endunless
                        </x-ui.item-title>
                        <x-ui.item-description class="text-sm">
                            <span class="font-mono">{{ $item->code }}</span> · {{ $item->unit->symbol }}
                        </x-ui.item-description>
                    </x-ui.item-content>
                    <span class="shrink-0 text-sm tabular-nums">{{ \App\Support\Money::formatRate($item->standard_rate) }}</span>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No work items found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
