{{-- Budget summary rows (spec E12). Needs $summary and $hasCostSources; $compact hides the line drill-down. --}}
@php
    $money = fn ($value) => \App\Support\Money::format($value, false);
    $variance = function ($pct) {
        if ($pct === null) {
            return ['—', 'text-muted-foreground'];
        }

        return [($pct > 0 ? '+' : '').$pct.'%', match (true) { $pct <= 0 => 'text-success', $pct <= 10 => 'text-warning', default => 'text-destructive' }];
    };
    $compact ??= false;
@endphp

@if ($summary['rows'] === [])
    <p class="py-6 text-center text-sm text-muted-foreground">{{ __('No budget yet. Approve a BOQ or material estimate, or add lines by hand.') }}</p>
@else
    <div class="hidden overflow-x-auto md:block">
        <x-ui.table variant="bordered">
            <x-ui.table-header>
                <x-ui.table-row>
                    <x-ui.table-head>{{ __('Cost category') }}</x-ui.table-head>
                    <x-ui.table-head class="text-end">{{ __('Budget') }}</x-ui.table-head>
                    <x-ui.table-head class="text-end">{{ __('Committed') }}</x-ui.table-head>
                    <x-ui.table-head class="text-end">{{ __('Actual') }}</x-ui.table-head>
                    <x-ui.table-head class="text-end">{{ __('Remaining') }}</x-ui.table-head>
                    <x-ui.table-head class="text-end">{{ __('Variance') }}</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>
            <x-ui.table-body>
                @foreach ($summary['rows'] as $row)
                    @php([$varianceText, $varianceTone] = $variance($row['variance_pct'] === null ? null : (float) $row['variance_pct']))
                    <x-ui.table-row wire:key="budget-{{ $row['category']->id }}">
                        <x-ui.table-cell class="font-medium">
                            @if ($compact)
                                {{ $row['category']->name }}
                            @else
                                <details>
                                    <summary class="cursor-pointer">{{ $row['category']->name }} <span class="text-sm text-muted-foreground">({{ $row['lines']->count() }})</span></summary>
                                    <ul class="mt-2 flex flex-col gap-1 text-sm font-normal">
                                        @foreach ($row['lines'] as $line)
                                            <li class="flex justify-between gap-4">
                                                <span>
                                                    {{ $line->displayName() }}
                                                    @if ($line->budget_qty) <span class="text-muted-foreground">· {{ (float) $line->budget_qty }} {{ $line->unit?->symbol }}</span>@endif
                                                    @if ($line->sourceEstimate)
                                                        · <a href="{{ route('estimation.estimates.show', $line->sourceEstimate) }}" wire:navigate class="font-mono hover:underline">{{ $line->sourceEstimate->estimate_number }}</a>
                                                    @endif
                                                </span>
                                                <span class="tabular-nums">{{ $money($line->budget_amount) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-end tabular-nums">{{ $money($row['budget']) }}</x-ui.table-cell>
                        <x-ui.table-cell class="text-end tabular-nums">{{ $money($row['committed']) }}</x-ui.table-cell>
                        <x-ui.table-cell class="text-end tabular-nums">{{ $money($row['actual']) }}</x-ui.table-cell>
                        <x-ui.table-cell class="text-end tabular-nums">{{ $money($row['remaining']) }}</x-ui.table-cell>
                        <x-ui.table-cell class="text-end tabular-nums {{ $varianceTone }}">{{ $varianceText }}</x-ui.table-cell>
                    </x-ui.table-row>
                @endforeach
                <x-ui.table-row class="font-semibold">
                    <x-ui.table-cell>{{ __('Total') }}</x-ui.table-cell>
                    <x-ui.table-cell class="text-end tabular-nums">{{ $money($summary['totals']['budget']) }}</x-ui.table-cell>
                    <x-ui.table-cell class="text-end tabular-nums">{{ $money($summary['totals']['committed']) }}</x-ui.table-cell>
                    <x-ui.table-cell class="text-end tabular-nums">{{ $money($summary['totals']['actual']) }}</x-ui.table-cell>
                    <x-ui.table-cell class="text-end tabular-nums">{{ $money($summary['totals']['remaining']) }}</x-ui.table-cell>
                    <x-ui.table-cell class="text-end tabular-nums">{{ $variance($summary['totals']['variance_pct'] === null ? null : (float) $summary['totals']['variance_pct'])[0] }}</x-ui.table-cell>
                </x-ui.table-row>
            </x-ui.table-body>
        </x-ui.table>
    </div>
    <x-ui.item-group class="gap-2 md:hidden">
        @foreach ($summary['rows'] as $row)
            @php([$varianceText, $varianceTone] = $variance($row['variance_pct'] === null ? null : (float) $row['variance_pct']))
            <x-ui.item variant="outline" class="min-h-16 py-2" wire:key="m-budget-{{ $row['category']->id }}">
                <x-ui.item-content class="min-w-0">
                    <x-ui.item-title class="text-base">{{ $row['category']->name }}</x-ui.item-title>
                    <x-ui.item-description class="text-sm tabular-nums">{{ __('Actual') }} {{ $money($row['actual']) }} · {{ __('Remaining') }} {{ $money($row['remaining']) }}</x-ui.item-description>
                </x-ui.item-content>
                <div class="flex shrink-0 flex-col items-end">
                    <span class="text-sm font-medium tabular-nums">{{ $money($row['budget']) }}</span>
                    <span class="text-sm tabular-nums {{ $varianceTone }}">{{ $varianceText }}</span>
                </div>
            </x-ui.item>
        @endforeach
        <div class="flex justify-between px-1 text-base font-semibold"><span>{{ __('Total budget') }}</span><span class="tabular-nums">{{ $money($summary['totals']['budget']) }}</span></div>
    </x-ui.item-group>
    @unless ($hasCostSources)
        <p class="text-sm text-muted-foreground">{{ __('Committed and actual costs will come from Purchases and Accounting.') }}</p>
    @endunless
@endif
