@php
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
@endphp

<div class="flex flex-col gap-4">
    @if ($canSeeMb)
        <x-ui.card class="gap-3 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold">{{ __('Measurement Book') }}</h2>
                <div class="flex gap-2">
                    <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" :href="route('site.mb.index', ['project' => $project->project_number])" wire:navigate>{{ __('All entries') }}</x-ui.button>
                    @if ($canRecord)
                        <x-ui.button size="sm" class="h-11 md:h-8" :href="route('site.mb.create', ['project' => $project->project_number])" wire:navigate><x-lucide-plus /> {{ __('New measurement') }}</x-ui.button>
                    @endif
                </div>
            </div>
            @if ($progress->isNotEmpty())
                <div class="flex flex-col gap-2">
                    <h3 class="text-sm font-medium text-muted-foreground">{{ __('BOQ progress') }}</h3>
                    @foreach ($progress as $row)
                        <div class="flex flex-col gap-1" wire:key="progress-{{ $row['line']->id }}">
                            <div class="flex justify-between gap-2 text-sm">
                                <span class="truncate"><span class="font-mono">{{ $row['line']->line_no }}</span> {{ $row['line']->description }}</span>
                                <span @class(['shrink-0 tabular-nums', 'text-destructive' => $row['state'] !== 'ok'])>{{ $qty($row['cumulative']) }} / {{ $qty($row['boq']) }} {{ $row['line']->unit->symbol }}</span>
                            </div>
                            <x-ui.progress :value="min(100, (float) ($row['pct'] ?? 0))" class="h-1.5" />
                        </div>
                    @endforeach
                </div>
            @endif
            <x-ui.item-group class="gap-2">
                @forelse ($entries as $entry)
                    <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="site-mb-{{ $entry->id }}" :href="route('site.mb.show', $entry)" wire:navigate>
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title class="text-base"><span class="truncate">{{ $entry->description }}</span></x-ui.item-title>
                            <x-ui.item-description class="text-sm"><span class="font-mono">{{ $entry->mb_number }}</span> · {{ $entry->measured_on->format('d-M-Y') }} · {{ $qty($entry->quantity) }} {{ $entry->unit->symbol }}</x-ui.item-description>
                        </x-ui.item-content>
                        <x-ui.badge :tone="$entry->status->color ?? 'neutral'" class="shrink-0 text-sm">{{ $entry->status->name }}</x-ui.badge>
                        <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                    </x-ui.item>
                @empty
                    <p class="py-6 text-center text-sm text-muted-foreground">{{ __('No measurements yet.') }}</p>
                @endforelse
            </x-ui.item-group>
        </x-ui.card>
    @endif

    @if ($canSeeInspections)
        <x-ui.card class="gap-3 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold">{{ __('Site inspections') }}</h2>
                <div class="flex gap-2">
                    <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" :href="route('site.inspections.index', ['project' => $project->project_number])" wire:navigate>{{ __('All inspections') }}</x-ui.button>
                    @if ($canInspect)
                        <x-ui.button size="sm" class="h-11 md:h-8" :href="route('site.inspections.create', ['project' => $project->project_number])" wire:navigate><x-lucide-plus /> {{ __('New inspection') }}</x-ui.button>
                    @endif
                </div>
            </div>
            <x-ui.item-group class="gap-2">
                @forelse ($inspections as $inspection)
                    <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="site-inspection-{{ $inspection->id }}" :href="route('site.inspections.show', $inspection)" wire:navigate>
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title class="text-base">{{ $inspection->type->name }} · {{ $inspection->inspection_date->format('d-M-Y') }}</x-ui.item-title>
                            <x-ui.item-description class="text-sm"><span class="font-mono">{{ $inspection->inspection_number }}</span>@if ($inspection->open_findings_count > 0) · {{ trans_choice(':count open finding|:count open findings', $inspection->open_findings_count) }}@endif</x-ui.item-description>
                        </x-ui.item-content>
                        <x-ui.badge :tone="$inspection->status->color ?? 'neutral'" class="shrink-0 text-sm">{{ $inspection->status->name }}</x-ui.badge>
                        <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                    </x-ui.item>
                @empty
                    <p class="py-6 text-center text-sm text-muted-foreground">{{ __('No inspections yet.') }}</p>
                @endforelse
            </x-ui.item-group>
        </x-ui.card>
    @endif
</div>
