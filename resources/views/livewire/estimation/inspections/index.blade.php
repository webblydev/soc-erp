@php
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
    $user = auth()->user();
    $selectClass = 'h-11 text-base md:h-9 md:text-sm';
@endphp

<div>
    <x-shell.list
        :search-placeholder="__('Search number, contractor, permittee or project')"
        :create-url="$user->can('create', \App\Modules\Estimation\Models\SiteInspection::class) ? route('site.inspections.create', array_filter(['project' => $filters['project'] ?? null])) : null"
        :create-label="__('New inspection')"
        :exportable="true"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
        :filter-columns="2"
    >
        <x-slot:filters>
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
                <x-ui.field-label for="filter-type">{{ __('Type') }}</x-ui.field-label>
                <x-ui.select native id="filter-type" wire:model.live="filters.type" :class="$selectClass">
                    <option value="">{{ __('Any type') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
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
            <x-ui.field orientation="horizontal" class="items-center gap-3 self-end">
                <x-ui.checkbox native id="filter-open" wire:model.live="filters.open" value="1" />
                <x-ui.field-label for="filter-open" class="font-normal">{{ __('Has open findings') }}</x-ui.field-label>
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
            <x-shell.bulk-bar :exportable="true" :deletable="$user->can('site.inspections.delete')" />

            <div class="overflow-x-auto">
                <x-ui.table variant="bordered">
                    <x-ui.table-header>
                        <x-ui.table-row>
                            <x-shell.select-all :ids="$rows->pluck('id')" />
                            <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="number" :label="__('Inspection #')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="date" :label="__('Date')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head>{{ __('Project') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Project engineer') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Contractor') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Permittee') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Type') }}</x-ui.table-head>
                            <x-ui.table-head class="text-end">{{ __('Findings') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                        </x-ui.table-row>
                    </x-ui.table-header>
                    <x-ui.table-body>
                        @forelse ($rows as $inspection)
                            @php($code = $inspection->status->code)
                            <x-ui.table-row wire:key="inspection-{{ $inspection->id }}">
                                <x-shell.select-row :id="$inspection->id" :label="$inspection->inspection_number" />
                                <x-shell.row-menu>
                                    <x-shell.row-menu-item icon="eye" data-detail-modal :href="route('site.inspections.show', $inspection)">{{ __('View') }}</x-shell.row-menu-item>
                                    @if ($code !== 'CLOSED' && $user->can('update', $inspection))
                                        <x-shell.row-menu-item icon="pencil" data-detail-modal :href="route('site.inspections.edit', $inspection)">{{ __('Edit') }}</x-shell.row-menu-item>
                                    @endif
                                    @if ($code === 'DRAFT' && $user->can('delete', $inspection))
                                        <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $inspection->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $inspection->inspection_number]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                                    @endif
                                </x-shell.row-menu>
                                <x-ui.table-cell class="font-mono text-sm whitespace-nowrap">
                                    <a data-detail-modal href="{{ route('site.inspections.show', $inspection) }}" wire:navigate class="hover:underline">{{ $inspection->inspection_number }}</a>
                                </x-ui.table-cell>
                                <x-ui.table-cell class="tabular-nums whitespace-nowrap">{{ $date($inspection->inspection_date) }}</x-ui.table-cell>
                                <x-ui.table-cell class="font-mono text-sm whitespace-nowrap">
                                    <a data-detail-modal href="{{ route('projects.projects.show', ['project' => $inspection->project, 'tab' => 'site']) }}" wire:navigate class="hover:underline">{{ $inspection->project->project_number }}</a>
                                </x-ui.table-cell>
                                <x-ui.table-cell>{{ $inspection->engineerName() ?? '—' }}</x-ui.table-cell>
                                <x-ui.table-cell>{{ $inspection->contractor_name ?? '—' }}</x-ui.table-cell>
                                <x-ui.table-cell>{{ $inspection->permittee_name ?? '—' }}</x-ui.table-cell>
                                <x-ui.table-cell>{{ $inspection->type->name }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums">
                                    <span @class(['text-destructive font-medium' => $inspection->open_findings_count > 0])>{{ $inspection->open_findings_count }}</span> / {{ $inspection->findings_count }}
                                </x-ui.table-cell>
                                <x-ui.table-cell><x-ui.badge :tone="$inspection->status->color ?? 'neutral'">{{ $inspection->status->name }}</x-ui.badge></x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell colspan="11" class="py-10 text-center text-muted-foreground">{{ __('No inspections found.') }}</x-ui.table-cell>
                            </x-ui.table-row>
                        @endforelse
                    </x-ui.table-body>
                </x-ui.table>
            </div>
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
            @forelse ($mobileRows as $inspection)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-inspection-{{ $inspection->id }}" :href="route('site.inspections.show', $inspection)" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base"><span class="truncate">{{ $inspection->project->name }}</span></x-ui.item-title>
                        <x-ui.item-description class="truncate text-sm"><span class="font-mono">{{ $inspection->inspection_number }}</span> · {{ $date($inspection->inspection_date) }} · {{ $inspection->type->name }}</x-ui.item-description>
                    </x-ui.item-content>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-ui.badge :tone="$inspection->status->color ?? 'neutral'" class="text-sm">{{ $inspection->status->name }}</x-ui.badge>
                        @if ($inspection->open_findings_count > 0)
                            <x-ui.badge tone="warning" class="text-sm">{{ trans_choice(':count open|:count open', $inspection->open_findings_count) }}</x-ui.badge>
                        @endif
                    </div>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No inspections found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
