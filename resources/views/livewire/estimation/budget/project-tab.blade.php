@php($money = fn ($value) => \App\Support\Money::format($value, false))

<div class="flex flex-col gap-4">
    <x-ui.card class="gap-3 p-4 md:p-6">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-base font-semibold">{{ __('Estimates') }}</h2>
            @if ($canCreate)
                <x-ui.button size="sm" class="h-11 md:h-8" :href="route('estimation.estimates.create', ['project' => $project->project_number])" wire:navigate><x-lucide-plus /> {{ __('New estimate') }}</x-ui.button>
            @endif
        </div>
        <x-ui.item-group class="gap-2">
            @forelse ($estimates as $estimate)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="project-estimate-{{ $estimate->id }}" :href="route('estimation.estimates.show', $estimate)" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base"><span class="truncate">{{ $estimate->title }}</span></x-ui.item-title>
                        <x-ui.item-description class="text-sm"><span class="font-mono">{{ $estimate->estimate_number }}</span> · {{ $estimate->kind->name }} · {{ $estimate->estimate_date->format('d-M-Y') }}</x-ui.item-description>
                    </x-ui.item-content>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <span class="text-sm tabular-nums">{{ $money($estimate->total_amount) }}</span>
                        <x-ui.badge :tone="$estimate->status->color ?? 'neutral'" class="text-sm">{{ $estimate->status->name }}</x-ui.badge>
                    </div>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-6 text-center text-sm text-muted-foreground">{{ __('No estimates yet.') }}</p>
            @endforelse
        </x-ui.item-group>
    </x-ui.card>

    @if ($canSeeBudget)
        <x-ui.card class="gap-3 p-4 md:p-6">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold">{{ __('Budget') }}</h2>
                <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" :href="route('estimation.budget.show', $project)" wire:navigate>{{ __('Open budget') }} <x-lucide-chevron-right /></x-ui.button>
            </div>
            @include('livewire.estimation.budget.summary', ['summary' => $summary, 'hasCostSources' => $hasCostSources, 'compact' => true])
        </x-ui.card>
    @endif
</div>
