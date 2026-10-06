<div>
    <x-shell.list
        :search-placeholder="__('Search team name')"
        :create-url="auth()->user()->can('crm.teams.manage') ? route('crm.teams.create') : null"
        :create-label="__('New team')"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
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
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head><x-shell.sort-header key="name" :label="__('Team')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Manager') }}</x-ui.table-head>
                        <x-ui.table-head class="text-end">{{ __('Members') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Business line') }}</x-ui.table-head>
                        <x-ui.table-head class="text-end"><x-shell.sort-header key="monthly_target_amount" :label="__('Monthly target')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head class="text-end">{{ __('Open leads') }}</x-ui.table-head>
                        <x-ui.table-head class="text-end">{{ __('Won this month') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Active') }}</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $team)
                        <x-ui.table-row wire:key="team-{{ $team->id }}">
                            <x-ui.table-cell class="font-medium">
                                @can('crm.teams.manage')
                                    <a href="{{ route('crm.teams.edit', $team) }}" wire:navigate class="hover:underline">{{ $team->name }}</a>
                                @else
                                    {{ $team->name }}
                                @endcan
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $team->manager?->name ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell class="text-end tabular-nums">{{ $team->active_members_count }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $team->businessLine?->name ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell class="text-end tabular-nums">{{ $team->monthly_target_amount !== null ? \App\Support\Money::format($team->monthly_target_amount, false) : '—' }}</x-ui.table-cell>
                            <x-ui.table-cell class="text-end tabular-nums">{{ $team->open_leads_count }}</x-ui.table-cell>
                            <x-ui.table-cell class="text-end tabular-nums">{{ $team->won_this_month_count }}</x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :tone="$team->is_active ? 'success' : 'neutral'">{{ $team->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="8" class="py-10 text-center text-muted-foreground">{{ __('No sales teams found.') }}</x-ui.table-cell>
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
            @forelse ($mobileRows as $team)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-team-{{ $team->id }}"
                    :href="auth()->user()->can('crm.teams.manage') ? route('crm.teams.edit', $team) : null" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="flex items-center gap-2 text-base">
                            <span class="truncate">{{ $team->name }}</span>
                            @unless ($team->is_active)
                                <x-ui.badge tone="neutral" class="text-sm">{{ __('Inactive') }}</x-ui.badge>
                            @endunless
                        </x-ui.item-title>
                        <x-ui.item-description class="text-sm">
                            {{ $team->manager?->name ?? __('No manager') }} · {{ trans_choice(':count member|:count members', $team->active_members_count) }}
                        </x-ui.item-description>
                    </x-ui.item-content>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No sales teams found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
