@php
    $presets = ['awaiting' => __('Awaiting verification'), 'mine' => __('Mine'), 'verified' => __('Verified'), 'rejected' => __('Rejected')];
    $money = fn ($value) => \App\Support\Money::format($value, false);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
    $user = auth()->user();
    $selectClass = 'h-11 text-base md:h-9 md:text-sm';
@endphp

<div class="flex flex-col gap-4">
    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
        @foreach ($presets as $key => $label)
            <x-ui.button :variant="$preset === $key ? 'default' : 'outline'" size="sm" class="h-11 shrink-0 md:h-8" wire:click="applyPreset('{{ $key }}')">{{ $label }}</x-ui.button>
        @endforeach
    </div>

    <x-shell.list
        :search-placeholder="__('Search MB no., item, location or project')"
        :create-url="$user->can('create', \App\Modules\Estimation\Models\MeasurementEntry::class) ? route('site.mb.create', array_filter(['project' => $filters['project'] ?? null])) : null"
        :create-label="__('New measurement')"
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
                <x-ui.field-label for="filter-status">{{ __('Status') }}</x-ui.field-label>
                <x-ui.select native id="filter-status" wire:model.live="filters.status" :class="$selectClass">
                    <option value="">{{ __('Any status') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-measurer">{{ __('Measured by') }}</x-ui.field-label>
                <x-ui.select native id="filter-measurer" wire:model.live="filters.measured_by" :class="$selectClass">
                    <option value="">{{ __('Anyone') }}</option>
                    @foreach ($measurers as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field>
                    <x-ui.field-label for="filter-from">{{ __('From') }}</x-ui.field-label>
                    <x-ui.input type="date" id="filter-from" wire:model.live="filters.from" :class="$selectClass" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="filter-to">{{ __('To') }}</x-ui.field-label>
                    <x-ui.input type="date" id="filter-to" wire:model.live="filters.to" :class="$selectClass" />
                </x-ui.field>
            </div>
        </x-slot:filters>

        @if ($canVerify)
            <x-slot:actions>
                <x-ui.button variant="outline" class="size-11 md:hidden" size="icon" wire:click="$toggle('selecting')" :aria-label="__('Select entries')">
                    <x-lucide-list-checks class="size-5" />
                </x-ui.button>
            </x-slot:actions>
        @endif

        <x-slot:desktop>
            <x-shell.bulk-bar :exportable="true">
                @if ($canVerify)
                    <x-ui.button size="sm" variant="outline" wire:click="verifySelected"><x-lucide-check /> {{ __('Verify') }}</x-ui.button>
                    <x-ui.button size="sm" variant="outline" x-on:click="$dispatch('open-sheet-mb-reject')"><x-lucide-x /> {{ __('Reject') }}</x-ui.button>
                @endif
            </x-shell.bulk-bar>

            <div class="overflow-x-auto">
                <x-ui.table variant="bordered">
                    <x-ui.table-header>
                        <x-ui.table-row>
                            <x-ui.table-head class="w-10"><x-shell.select-all :ids="$rows->pluck('id')" /></x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="number" :label="__('MB #')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head>{{ __('Project') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Book / page') }}</x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="measured" :label="__('Measured on')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head>{{ __('Item') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Location') }}</x-ui.table-head>
                            <x-ui.table-head class="text-end">{{ __('Qty') }}</x-ui.table-head>
                            <x-ui.table-head class="text-end">{{ __('Rate') }}</x-ui.table-head>
                            <x-ui.table-head class="text-end"><x-shell.sort-header key="amount" :label="__('Amount')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head class="text-end">{{ __('Achieved') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                        </x-ui.table-row>
                    </x-ui.table-header>
                    <x-ui.table-body>
                        @forelse ($rows as $entry)
                            <x-ui.table-row wire:key="mb-{{ $entry->id }}">
                                <x-ui.table-cell><x-shell.select-row :id="$entry->id" :label="$entry->mb_number" /></x-ui.table-cell>
                                <x-ui.table-cell class="font-mono text-sm whitespace-nowrap">
                                    <a data-detail-modal href="{{ route('site.mb.show', $entry) }}" wire:navigate class="hover:underline">{{ $entry->mb_number }}</a>
                                </x-ui.table-cell>
                                <x-ui.table-cell class="font-mono text-sm whitespace-nowrap">{{ $entry->project->project_number }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-sm">{{ collect([$entry->mb_book_no, $entry->mb_page_no])->filter()->implode(' / ') ?: '—' }}</x-ui.table-cell>
                                <x-ui.table-cell class="tabular-nums whitespace-nowrap">{{ $date($entry->measured_on) }}</x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ \Illuminate\Support\Str::limit($entry->description, 60) }}
                                    @if ($entry->estimateLine)<span class="block font-mono text-sm text-muted-foreground">{{ __('BOQ') }} {{ $entry->estimateLine->line_no }}</span>@endif
                                </x-ui.table-cell>
                                <x-ui.table-cell class="text-sm">{{ $entry->location ?? '—' }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums whitespace-nowrap">{{ $qty($entry->quantity) }} {{ $entry->unit->symbol }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums">{{ \App\Support\Money::formatRate($entry->rate) }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums whitespace-nowrap">{{ $money($entry->amount) }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums">{{ $entry->achievement_pct !== null ? round((float) $entry->achievement_pct, 1).'%' : '—' }}</x-ui.table-cell>
                                <x-ui.table-cell><x-ui.badge :tone="$entry->status->color ?? 'neutral'">{{ $entry->status->name }}</x-ui.badge></x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell colspan="12" class="py-10 text-center text-muted-foreground">{{ __('No measurements found.') }}</x-ui.table-cell>
                            </x-ui.table-row>
                        @endforelse
                    </x-ui.table-body>
                </x-ui.table>
            </div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $entry)
                @if ($selecting)
                    <label class="flex min-h-16 items-center gap-3 rounded-md border px-3 py-2 active:bg-accent" wire:key="m-select-{{ $entry->id }}">
                        <x-ui.checkbox native wire:model.live="selected" value="{{ $entry->id }}" class="size-5" />
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate text-base font-medium">{{ $entry->description }}</span>
                            <span class="text-sm text-muted-foreground"><span class="font-mono">{{ $entry->mb_number }}</span> · {{ $qty($entry->quantity) }} {{ $entry->unit->symbol }}</span>
                        </span>
                        <x-ui.badge :tone="$entry->status->color ?? 'neutral'" class="shrink-0 text-sm">{{ $entry->status->name }}</x-ui.badge>
                    </label>
                @else
                    <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-mb-{{ $entry->id }}" :href="route('site.mb.show', $entry)" wire:navigate>
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title class="text-base"><span class="truncate">{{ $entry->description }}</span></x-ui.item-title>
                            <x-ui.item-description class="truncate text-sm"><span class="font-mono">{{ $entry->mb_number }}</span> · {{ $entry->project->project_number }}</x-ui.item-description>
                            <span class="text-sm tabular-nums text-muted-foreground">{{ $qty($entry->quantity) }} {{ $entry->unit->symbol }} · ৳ {{ $money($entry->amount) }}</span>
                        </x-ui.item-content>
                        <x-ui.badge :tone="$entry->status->color ?? 'neutral'" class="shrink-0 text-sm">{{ $entry->status->name }}</x-ui.badge>
                        <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                    </x-ui.item>
                @endif
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No measurements found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>

    @if ($selecting)
        <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-40 flex gap-2 border-t bg-background px-4 py-3 md:hidden">
            <x-ui.button variant="outline" class="h-11 flex-1" x-on:click="$dispatch('open-sheet-mb-reject')">{{ __('Reject') }}</x-ui.button>
            <x-ui.button class="h-11 flex-1" wire:click="verifySelected">{{ __('Verify') }} (<span x-text="$wire.selected.length"></span>)</x-ui.button>
        </div>
    @endif

    <x-shell.sheet id="mb-reject" :title="__('Reject measurements')" :description="__('The measurer corrects a rejected entry and records it again.')">
        <x-ui.field>
            <x-ui.field-label for="mb-reject-reason">{{ __('Reason') }} *</x-ui.field-label>
            <x-ui.textarea id="mb-reject-reason" wire:model="rejectReason" rows="2" class="text-base md:text-sm" />
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-mb-reject')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button variant="destructive" wire:click="rejectSelected">{{ __('Reject') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
