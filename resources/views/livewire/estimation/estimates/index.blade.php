@php
    $money = fn ($value) => \App\Support\Money::format($value, false);
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
    $user = auth()->user();
    $selectClass = 'h-11 text-base md:h-9 md:text-sm';
@endphp

<div>
    <x-shell.list
        :search-placeholder="__('Search number, title or project')"
        :create-url="$user->can('create', \App\Modules\Estimation\Models\Estimate::class) ? route('estimation.estimates.create', array_filter(['project' => $filters['project'] ?? null])) : null"
        :create-label="__('New estimate')"
        :exportable="$user->can('estimation.estimates.export')"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
        :filter-columns="2"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-revisions">{{ __('Revisions') }}</x-ui.field-label>
                <x-ui.select native id="filter-revisions" wire:model.live="filters.revisions" :class="$selectClass">
                    <option value="">{{ __('Latest revision only') }}</option>
                    <option value="all">{{ __('All revisions') }}</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-kind">{{ __('Kind') }}</x-ui.field-label>
                <x-ui.select native id="filter-kind" wire:model.live="filters.kind" :class="$selectClass">
                    <option value="">{{ __('Any kind') }}</option>
                    @foreach ($kinds as $kind)
                        <option value="{{ $kind->id }}">{{ $kind->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-status">{{ __('Status') }}</x-ui.field-label>
                <x-ui.select native id="filter-status" wire:model.live="filters.status" :class="$selectClass">
                    <option value="">{{ __('Any status') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-project">{{ __('Project') }}</x-ui.field-label>
                <x-ui.select native id="filter-project" wire:model.live="filters.project" :class="$selectClass">
                    <option value="">{{ __('Any project') }}</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->project_number }}">{{ $project->project_number }} — {{ $project->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-from">{{ __('From') }}</x-ui.field-label>
                <x-ui.input type="date" id="filter-from" wire:model.live="filters.from" :class="$selectClass" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-to">{{ __('To') }}</x-ui.field-label>
                <x-ui.input type="date" id="filter-to" wire:model.live="filters.to" :class="$selectClass" />
            </x-ui.field>
        </x-slot:filters>

        <x-slot:desktop>
            <div class="overflow-x-auto">
                <x-ui.table variant="bordered">
                    <x-ui.table-header>
                        <x-ui.table-row>
                            <x-ui.table-head><x-shell.sort-header key="number" :label="__('Estimate #')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head>{{ __('Kind') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Project') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Title') }}</x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="date" :label="__('Date')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head class="text-end">{{ __('Rev.') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Prepared by') }}</x-ui.table-head>
                            <x-ui.table-head class="text-end"><x-shell.sort-header key="total" :label="__('Total')" :$sort :$direction /></x-ui.table-head>
                        </x-ui.table-row>
                    </x-ui.table-header>
                    <x-ui.table-body>
                        @forelse ($rows as $estimate)
                            <x-ui.table-row wire:key="estimate-{{ $estimate->id }}">
                                <x-ui.table-cell class="font-mono text-sm whitespace-nowrap">
                                    <a href="{{ route('estimation.estimates.show', $estimate) }}" wire:navigate class="hover:underline">{{ $estimate->estimate_number }}</a>
                                </x-ui.table-cell>
                                <x-ui.table-cell>{{ $estimate->kind->name }}</x-ui.table-cell>
                                <x-ui.table-cell class="font-mono text-sm whitespace-nowrap">{{ $estimate->project->project_number }}</x-ui.table-cell>
                                <x-ui.table-cell class="font-medium">{{ $estimate->title }}</x-ui.table-cell>
                                <x-ui.table-cell class="tabular-nums whitespace-nowrap">{{ $date($estimate->estimate_date) }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums">{{ $estimate->revision_no }}</x-ui.table-cell>
                                <x-ui.table-cell><x-ui.badge :tone="$estimate->status->color ?? 'neutral'">{{ $estimate->status->name }}</x-ui.badge></x-ui.table-cell>
                                <x-ui.table-cell>{{ $estimate->preparer->full_name }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums whitespace-nowrap">{{ $money($estimate->total_amount) }}</x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell colspan="9" class="py-10 text-center text-muted-foreground">{{ __('No estimates found.') }}</x-ui.table-cell>
                            </x-ui.table-row>
                        @endforelse
                    </x-ui.table-body>
                </x-ui.table>
            </div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $estimate)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-estimate-{{ $estimate->id }}" :href="route('estimation.estimates.show', $estimate)" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base"><span class="truncate">{{ $estimate->title }}</span></x-ui.item-title>
                        <x-ui.item-description class="truncate text-sm"><span class="font-mono">{{ $estimate->estimate_number }}</span> · {{ $estimate->project->project_number }}</x-ui.item-description>
                        <span class="text-sm tabular-nums text-muted-foreground">৳ {{ $money($estimate->total_amount) }}</span>
                    </x-ui.item-content>
                    <x-ui.badge :tone="$estimate->status->color ?? 'neutral'" class="shrink-0 text-sm">{{ $estimate->status->name }}</x-ui.badge>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No estimates found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
