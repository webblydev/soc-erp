<div>
    <x-shell.list
        :search-placeholder="__('Search record type or event')"
        :exportable="auth()->user()->can('admin.audit.export')"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-user">{{ __('Username') }}</x-ui.field-label>
                <x-ui.input id="filter-user" wire:model.live.debounce.300ms="filters.user" autocapitalize="none" class="h-11 text-base md:h-9 md:text-sm" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-type">{{ __('Record type') }}</x-ui.field-label>
                <x-ui.select native id="filter-type" wire:model.live="filters.type" class="h-11 md:h-9">
                    <option value="">{{ __('All types') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-record">{{ __('Record id') }}</x-ui.field-label>
                <x-ui.input id="filter-record" type="number" inputmode="numeric" wire:model.live.debounce.300ms="filters.record" class="h-11 text-base md:h-9 md:text-sm" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-event">{{ __('Event') }}</x-ui.field-label>
                <x-ui.select native id="filter-event" wire:model.live="filters.event" class="h-11 md:h-9">
                    <option value="">{{ __('All events') }}</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}">{{ str_replace('_', ' ', $event) }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <div class="grid grid-cols-2 gap-2">
                <x-ui.field>
                    <x-ui.field-label for="filter-from">{{ __('From') }}</x-ui.field-label>
                    <x-ui.input id="filter-from" type="date" wire:model.live="filters.from" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="filter-to">{{ __('To') }}</x-ui.field-label>
                    <x-ui.input id="filter-to" type="date" wire:model.live="filters.to" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
            </div>
        </x-slot:filters>

        <x-slot:desktop>
            <x-shell.bulk-bar :exportable="auth()->user()->can('admin.audit.export')" />

            <x-ui.table variant="bordered">
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-shell.select-all :ids="$rows->pluck('id')" />
                        <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="created_at" :label="__('When')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('User') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Event') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Record') }}</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $entry)
                        <x-ui.table-row wire:key="audit-{{ $entry->id }}">
                            <x-shell.select-row :id="$entry->id" :label="$entry->auditable_type.' #'.$entry->auditable_id" />
                            <x-shell.row-menu>
                                <x-shell.row-menu-item icon="eye" wire:click="show({{ $entry->id }})">{{ __('View changes') }}</x-shell.row-menu-item>
                                <x-shell.row-menu-item icon="history" wire:click="showRecordHistory({{ $entry->id }})">{{ __('History of this record') }}</x-shell.row-menu-item>
                            </x-shell.row-menu>
                            <x-ui.table-cell class="whitespace-nowrap">{{ $entry->created_at->format('d-M-Y H:i') }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $entry->actorLabel() }}</x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge variant="secondary" class="text-sm">{{ str_replace('_', ' ', $entry->event) }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell class="font-mono text-sm">{{ $entry->auditable_type }} #{{ $entry->auditable_id }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="6" class="py-10 text-center text-muted-foreground">{{ __('No entries found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $entry)
                <button type="button" wire:click="show({{ $entry->id }})" wire:key="m-audit-{{ $entry->id }}" class="w-full text-start">
                    <x-ui.item variant="outline" class="min-h-16 active:bg-accent">
                        <x-ui.item-content>
                            <x-ui.item-title class="text-base">{{ $entry->auditable_type }} #{{ $entry->auditable_id }}</x-ui.item-title>
                            <x-ui.item-description class="text-sm">{{ $entry->actorLabel() }} · {{ $entry->created_at->format('d-M-Y H:i') }}</x-ui.item-description>
                        </x-ui.item-content>
                        <x-ui.badge variant="secondary" class="text-sm">{{ str_replace('_', ' ', $entry->event) }}</x-ui.badge>
                        <x-lucide-chevron-right class="size-4 text-muted-foreground" />
                    </x-ui.item>
                </button>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No entries found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>

    <x-shell.sheet id="audit-entry" :title="$openEntry ? $openEntry->auditable_type.' #'.$openEntry->auditable_id : __('Audit entry')"
        :description="$openEntry ? str_replace('_', ' ', $openEntry->event).' · '.$openEntry->actorLabel().' · '.$openEntry->created_at->format('d-M-Y H:i:s') : __('What changed, who changed it and when.')">
        @if ($openEntry)
            <div class="flex flex-col gap-3 pb-4">
                @forelse ($openEntry->fieldChanges() as $field => $change)
                    <div class="rounded-md border p-3 text-sm">
                        <p class="font-medium">{{ $field }}</p>
                        <p class="break-all text-destructive line-through">{{ $change['old'] }}</p>
                        <p class="break-all text-success">{{ $change['new'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('No field changes recorded for this event.') }}</p>
                @endforelse
                <p class="text-sm text-muted-foreground">{{ $openEntry->ip_address }} · {{ \Illuminate\Support\Str::limit((string) $openEntry->user_agent, 80) }}</p>
            </div>
        @endif
    </x-shell.sheet>
</div>
