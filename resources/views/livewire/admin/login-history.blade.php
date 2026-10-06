<div>
    <x-shell.list
        :search-placeholder="__('Search username or IP')"
        exportable
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-user">{{ __('Username') }}</x-ui.field-label>
                <x-ui.input id="filter-user" wire:model.live.debounce.300ms="filters.user" autocapitalize="none" class="h-11 text-base md:h-9 md:text-sm" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-result">{{ __('Result') }}</x-ui.field-label>
                <x-ui.select native id="filter-result" wire:model.live="filters.result" class="h-11 md:h-9">
                    <option value="">{{ __('All') }}</option>
                    <option value="1">{{ __('Succeeded') }}</option>
                    <option value="0">{{ __('Failed') }}</option>
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
            <x-shell.bulk-bar exportable />

            <x-ui.table variant="bordered">
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-shell.select-all :ids="$rows->pluck('id')" />
                        <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="created_at" :label="__('When')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Username attempted') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('User') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Result') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('IP') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Device') }}</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $entry)
                        <x-ui.table-row wire:key="login-{{ $entry->id }}">
                            <x-shell.select-row :id="$entry->id" :label="$entry->username_attempted" />
                            <x-shell.row-menu>
                                <x-shell.row-menu-item icon="list-filter" wire:click="$set('filters.user', {{ \Illuminate\Support\Js::from((string) $entry->username_attempted) }})">{{ __('Only this username') }}</x-shell.row-menu-item>
                                @if ($entry->user && auth()->user()->can('admin.users.update'))
                                    <x-shell.row-menu-item icon="user-pen" data-detail-modal :href="route('admin.users.edit', $entry->user)">{{ __('Open user') }}</x-shell.row-menu-item>
                                @endif
                            </x-shell.row-menu>
                            <x-ui.table-cell class="whitespace-nowrap">{{ $entry->created_at->format('d-M-Y H:i') }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $entry->username_attempted }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $entry->user?->name ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge class="text-sm" :tone="$entry->succeeded ? 'success' : 'danger'">{{ $entry->succeeded ? __('Success') : __('Failed') }}</x-ui.badge>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="font-mono text-sm">{{ $entry->ip_address }}</x-ui.table-cell>
                            <x-ui.table-cell class="max-w-64 truncate text-sm text-muted-foreground" title="{{ $entry->user_agent }}">{{ \Illuminate\Support\Str::limit((string) $entry->user_agent, 40) }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="8" class="py-10 text-center text-muted-foreground">{{ __('No sign-in attempts found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $entry)
                <x-ui.item variant="outline" class="min-h-16" wire:key="m-login-{{ $entry->id }}">
                    <x-ui.item-content>
                        <x-ui.item-title class="text-base">{{ $entry->username_attempted }}</x-ui.item-title>
                        <x-ui.item-description class="text-sm">{{ $entry->created_at->format('d-M-Y H:i') }} · {{ $entry->ip_address }}</x-ui.item-description>
                    </x-ui.item-content>
                    <x-ui.badge class="text-sm" :tone="$entry->succeeded ? 'success' : 'danger'">{{ $entry->succeeded ? __('Success') : __('Failed') }}</x-ui.badge>
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No sign-in attempts found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
