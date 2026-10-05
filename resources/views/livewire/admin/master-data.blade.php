<div class="flex flex-col gap-4 md:grid md:grid-cols-[14rem_1fr] md:gap-6">
    {{-- Table list: always on desktop, only when no table is open on mobile --}}
    <nav @class(['flex flex-col gap-4', 'max-md:hidden' => $table !== null]) aria-label="{{ __('Lookup tables') }}">
        @foreach ($groups as $module => $tables)
            <x-ui.item-group class="gap-1">
                <p class="px-1 text-sm font-medium uppercase text-muted-foreground">{{ $module }}</p>
                @foreach ($tables as $key => $definition)
                    <x-ui.item size="sm" :href="route('admin.master-data.show', $key)" wire:navigate
                        :class="$key === $table ? 'min-h-11 active:bg-accent bg-accent' : 'min-h-11 active:bg-accent'">
                        <span class="flex-1 text-sm">{{ __($definition['label']) }}</span>
                        <x-lucide-chevron-right class="size-4 text-muted-foreground md:hidden" />
                    </x-ui.item>
                @endforeach
            </x-ui.item-group>
        @endforeach
    </nav>

    <section @class(['flex flex-col gap-4', 'max-md:hidden' => $table === null])>
        @if ($entry === null)
            <x-ui.empty class="hidden md:flex">
                <x-ui.empty-header>
                    <x-ui.empty-title>{{ __('Choose a table') }}</x-ui.empty-title>
                    <x-ui.empty-description>{{ __('Pick a lookup table on the left to edit its rows.') }}</x-ui.empty-description>
                </x-ui.empty-header>
            </x-ui.empty>
        @else
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ __($entry['label']) }}</h2>
                @if ($can('create'))
                    <x-ui.button class="hidden md:inline-flex" wire:click="create"><x-lucide-plus /> {{ __('Add row') }}</x-ui.button>
                @endif
            </div>

            <div class="flex flex-col gap-2" @if ($can('update')) wire:sort="sort" @endif>
                @forelse ($rows as $row)
                    <x-ui.item variant="outline" class="min-h-14 gap-2 py-2 ps-1" wire:key="row-{{ $row->id }}" wire:sort:item="{{ $row->id }}">
                        @if ($can('update'))
                            <span wire:sort:handle class="flex size-11 cursor-grab touch-none items-center justify-center text-muted-foreground" aria-label="{{ __('Drag to reorder') }}">
                                <x-lucide-grip-vertical class="size-5" />
                            </span>
                        @endif
                        <button type="button" wire:click="edit({{ $row->id }})" class="flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-md px-2 text-start active:bg-accent">
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="flex items-center gap-2 text-base font-medium md:text-sm">
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
                    <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No rows yet.') }}</p>
                @endforelse
            </div>

            @if ($can('create'))
                <button type="button" wire:click="create" aria-label="{{ __('Add row') }}"
                    class="fixed end-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg active:scale-95 md:hidden">
                    <x-lucide-plus class="size-6" />
                </button>
            @endif
        @endif
    </section>

    @if ($entry !== null)
        @php($isSystem = (bool) ($editingId && $rows->firstWhere('id', $editingId)?->is_system))

        <x-shell.sheet id="lookup-row" :title="$editingId ? __('Edit row') : __('Add row')">
            <form wire:submit="save" id="lookup-row-form" class="flex flex-col gap-4 pb-2">
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
                            @if ($definition['type'] === 'textarea')
                                <x-ui.textarea id="form-{{ $field }}" wire:model="form.{{ $field }}" rows="3" class="text-base md:text-sm" />
                            @else
                                <x-ui.input id="form-{{ $field }}" wire:model="form.{{ $field }}" :type="$definition['type'] === 'number' ? 'number' : 'text'" :inputmode="$definition['type'] === 'number' ? 'numeric' : null" class="h-11 text-base md:h-9 md:text-sm" />
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
            </form>

            <x-slot:footer>
                @if ($editingId && ! $isSystem && $can('deactivate'))
                    <x-ui.button variant="outline" class="text-destructive" wire:click="delete" wire:confirm="{{ __('Delete this row? Rows in use cannot be deleted.') }}">{{ __('Delete') }}</x-ui.button>
                @endif
                <x-ui.button type="submit" form="lookup-row-form">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endif
</div>
