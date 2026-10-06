<div class="flex flex-col gap-4">
    @if ($entry === null)
        {{-- Index: the tables the user may open, grouped by module. Each table is also in the sidebar tree. --}}
        <nav class="flex max-w-2xl flex-col gap-4" aria-label="{{ __('Lookup tables') }}">
            @foreach ($groups as $module => $tables)
                <x-ui.item-group class="gap-1">
                    <p class="px-1 text-sm font-medium uppercase text-muted-foreground">{{ $module }}</p>
                    @foreach ($tables as $key => $definition)
                        <x-ui.item size="sm" variant="outline" :href="route('admin.master-data.show', $key)" wire:navigate class="min-h-11 active:bg-accent">
                            <span class="flex-1 text-sm">{{ __($definition['label']) }}</span>
                            <x-lucide-chevron-right class="size-4 text-muted-foreground" />
                        </x-ui.item>
                    @endforeach
                </x-ui.item-group>
            @endforeach
        </nav>
    @else
    <section class="flex flex-col gap-4">
        <h2 class="text-lg font-semibold">{{ __($entry['label']) }}</h2>

        <x-shell.list
            :search-placeholder="__('Search code, name or description')"
            exportable
            :has-more="$this->hasMoreRows"
            :active-filters="count(array_filter($filters, 'filled'))"
        >
            @if ($can('create'))
                <x-slot:actions>
                    <x-ui.button class="hidden md:inline-flex" wire:click="create"><x-lucide-plus /> {{ __('Add row') }}</x-ui.button>
                </x-slot:actions>
            @endif

            <x-slot:filters>
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
                @php($reorderable = $can('update') && $canReorder)

                <x-shell.bulk-bar exportable :deletable="$can('deactivate')" />

                <x-ui.table variant="bordered">
                    <x-ui.table-header>
                        <x-ui.table-row>
                            <x-shell.select-all :ids="collect($rows->items())->pluck('id')" />
                            <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                            @if ($reorderable)
                                <x-ui.table-head class="w-10"><span class="sr-only">{{ __('Order') }}</span></x-ui.table-head>
                            @endif
                            <x-ui.table-head><x-shell.sort-header key="code" :label="__('Code')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head>{{ __('Description') }}</x-ui.table-head>
                            @foreach ($entry['extra_fields'] as $definition)
                                <x-ui.table-head>{{ __($definition['label']) }}</x-ui.table-head>
                            @endforeach
                            <x-ui.table-head>{{ __('Active') }}</x-ui.table-head>
                        </x-ui.table-row>
                    </x-ui.table-header>
                    <x-ui.table-body :wire:sort="$reorderable ? 'sortOnPage' : null">
                        @forelse ($rows as $row)
                            <x-ui.table-row wire:key="row-{{ $row->id }}" wire:sort:item="{{ $row->id }}">
                                <x-shell.select-row :id="$row->id" :label="$row->name" />
                                <x-shell.row-menu>
                                    <x-shell.row-menu-item :icon="$can('update') ? 'pencil' : 'eye'" wire:click="edit({{ $row->id }})">{{ $can('update') ? __('Edit') : __('View') }}</x-shell.row-menu-item>
                                    @if ($can('deactivate') && ! $row->is_system)
                                        <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $row->id }})" wire:confirm="{{ __('Delete :name? Rows in use cannot be deleted.', ['name' => $row->name]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                                    @endif
                                </x-shell.row-menu>
                                @if ($reorderable)
                                    <x-ui.table-cell class="w-10 text-center">
                                        <span wire:sort:handle class="inline-flex cursor-grab touch-none text-muted-foreground" aria-label="{{ __('Drag to reorder') }}">
                                            <x-lucide-grip-vertical class="size-4" />
                                        </span>
                                    </x-ui.table-cell>
                                @endif
                                <x-ui.table-cell>
                                    @if ($row->color)
                                        <x-ui.badge :tone="$row->color">{{ $row->code }}</x-ui.badge>
                                    @else
                                        <span class="font-mono text-sm">{{ $row->code }}</span>
                                    @endif
                                </x-ui.table-cell>
                                <x-ui.table-cell class="font-medium">
                                    <span class="inline-flex items-center gap-1.5">
                                        <button type="button" wire:click="edit({{ $row->id }})" class="text-start hover:underline">{{ $row->name }}</button>
                                        @if ($row->is_system)
                                            <x-lucide-lock class="size-3.5 text-muted-foreground" :aria-label="__('System row')" />
                                        @endif
                                    </span>
                                </x-ui.table-cell>
                                <x-ui.table-cell class="max-w-xs truncate text-muted-foreground">{{ $row->description }}</x-ui.table-cell>
                                @foreach ($entry['extra_fields'] as $field => $definition)
                                    <x-ui.table-cell @class(['text-end tabular-nums' => $definition['type'] === 'number', 'max-w-xs truncate' => $definition['type'] === 'textarea'])>{{ $display($row, $field) }}</x-ui.table-cell>
                                @endforeach
                                <x-ui.table-cell><x-ui.badge :tone="$row->is_active ? 'success' : 'neutral'">{{ $row->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell :colspan="6 + count($entry['extra_fields']) + ($reorderable ? 1 : 0)" class="py-10 text-center text-muted-foreground">{{ __('No rows found.') }}</x-ui.table-cell>
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
                <div class="flex flex-col gap-2" @if ($can('update') && $canReorder) wire:sort="sort" @endif>
                    @forelse ($mobileRows as $row)
                        <x-ui.item variant="outline" class="min-h-14 gap-2 py-2 ps-1" wire:key="m-row-{{ $row->id }}" wire:sort:item="{{ $row->id }}">
                            @if ($can('update') && $canReorder)
                                <span wire:sort:handle class="flex size-11 cursor-grab touch-none items-center justify-center text-muted-foreground" aria-label="{{ __('Drag to reorder') }}">
                                    <x-lucide-grip-vertical class="size-5" />
                                </span>
                            @endif
                            <button type="button" wire:click="edit({{ $row->id }})" class="flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-md px-2 text-start active:bg-accent">
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="flex items-center gap-2 text-base font-medium">
                                        <span class="truncate">{{ $row->name }}</span>
                                        @if ($row->color)
                                            <x-ui.badge class="text-sm" :tone="$row->color">{{ $row->code }}</x-ui.badge>
                                        @else
                                            <span class="font-mono text-sm text-muted-foreground">{{ $row->code }}</span>
                                        @endif
                                        @if ($row->is_system)
                                            <x-lucide-lock class="size-3.5 text-muted-foreground" :aria-label="__('System row')" />
                                        @endif
                                    </span>
                                    @if ($row->description)
                                        <span class="truncate text-sm text-muted-foreground">{{ $row->description }}</span>
                                    @endif
                                </span>
                                @unless ($row->is_active)
                                    <x-ui.badge class="text-sm" tone="neutral">{{ __('Inactive') }}</x-ui.badge>
                                @endunless
                                <x-lucide-chevron-right class="size-4 text-muted-foreground" />
                            </button>
                        </x-ui.item>
                    @empty
                        <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No rows found.') }}</p>
                    @endforelse
                </div>
            </x-slot:mobile>
        </x-shell.list>

        @if ($can('create'))
            <button type="button" wire:click="create" aria-label="{{ __('Add row') }}"
                class="fixed end-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg active:scale-95 md:hidden">
                <x-lucide-plus class="size-6" />
            </button>
        @endif
    </section>
    @endif

    @if ($entry !== null)
        @php($isSystem = $editingIsSystem)
        @php($canSave = $editingId ? $can('update') : $can('create'))

        <x-shell.sheet id="lookup-row" :title="$editingId ? __('Edit row') : __('Add row')" :description="__($entry['label'])">
            <form wire:submit="save" id="lookup-row-form" class="flex flex-col gap-4 pb-2">
              <fieldset @disabled(! $canSave) class="contents">
                <x-ui.field>
                    <x-ui.field-label for="form-code">{{ __('Code') }} *</x-ui.field-label>
                    <x-ui.input id="form-code" wire:model="form.code" autocapitalize="characters" class="h-11 font-mono text-base md:h-9 md:text-sm" :disabled="$isSystem" />
                    <x-ui.field-error :messages="$errors->get('form.code')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="form-name">{{ __('Name') }} *</x-ui.field-label>
                    <x-ui.input id="form-name" wire:model="form.name" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('form.name')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="form-description">{{ __('Description') }}</x-ui.field-label>
                    <x-ui.input id="form-description" wire:model="form.description" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('form.description')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="form-color">{{ __('Badge colour') }}</x-ui.field-label>
                    <x-ui.select native id="form-color" wire:model="form.color" class="h-11 text-base md:h-9 md:text-sm">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($colors as $color)
                            <option value="{{ $color }}">{{ ucfirst($color) }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('form.color')" />
                </x-ui.field>

                @foreach ($entry['extra_fields'] as $field => $definition)
                    @if ($definition['type'] === 'bool')
                        <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                            <x-ui.switch id="form-{{ $field }}" wire:model="form.{{ $field }}" :checked="(bool) ($form[$field] ?? false)" />
                            <x-ui.field-label for="form-{{ $field }}">{{ __($definition['label']) }}</x-ui.field-label>
                        </x-ui.field>
                    @else
                        <x-ui.field>
                            <x-ui.field-label for="form-{{ $field }}">{{ __($definition['label']) }}{{ ($definition['required'] ?? false) ? ' *' : '' }}</x-ui.field-label>
                            @if ($definition['type'] === 'lookup')
                                <x-lookup-select :table="$definition['table']" :include="$form[$field] ?? null" :placeholder="__('Choose…')" id="form-{{ $field }}" wire:model="form.{{ $field }}" />
                            @elseif ($definition['type'] === 'employee')
                                <x-employee-select :include="$form[$field] ?? null" :placeholder="__('None')" id="form-{{ $field }}" wire:model="form.{{ $field }}" />
                            @elseif ($definition['type'] === 'textarea')
                                <x-ui.textarea id="form-{{ $field }}" wire:model="form.{{ $field }}" rows="3" class="text-base md:text-sm" />
                            @else
                                <x-ui.input id="form-{{ $field }}" wire:model="form.{{ $field }}" :type="$definition['type'] === 'number' ? 'number' : 'text'" :inputmode="$definition['type'] === 'number' ? 'numeric' : null" :autocapitalize="($definition['uppercase'] ?? false) ? 'characters' : null" @class(['h-11 text-base md:h-9 md:text-sm', 'font-mono' => $definition['uppercase'] ?? false]) />
                            @endif
                            <x-ui.field-error :messages="$errors->get('form.'.$field)" />
                        </x-ui.field>
                    @endif
                @endforeach

                <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                    <x-ui.switch id="form-is_active" wire:model="form.is_active" :checked="(bool) ($form['is_active'] ?? true)" :disabled="$isSystem || ! $can('deactivate')" />
                    <x-ui.field-label for="form-is_active">{{ __('Active') }}</x-ui.field-label>
                </x-ui.field>
                <x-ui.field-error :messages="$errors->get('form.is_active')" />
              </fieldset>
            </form>

            <x-slot:footer>
                @if ($editingId && ! $isSystem && $can('deactivate'))
                    <x-ui.button variant="outline" class="text-destructive" x-on:click="$dispatch('confirm-action', { title: @js(__('Delete this row?')), description: @js(__('Rows in use cannot be deleted.')), confirm: () => $wire.delete() })">{{ __('Delete') }}</x-ui.button>
                @endif
                @if ($canSave)
                    <x-ui.button type="submit" form="lookup-row-form">{{ __('Save') }}</x-ui.button>
                @endif
            </x-slot:footer>
        </x-shell.sheet>
    @endif
</div>
