@php
    $money = fn ($value) => $value === null ? '—' : \App\Support\Money::format($value, false);
    $qty = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    $signed = fn ($value) => (str_starts_with((string) $value, '-') ? '' : '+').$money($value);
    $tones = ['added' => 'success', 'removed' => 'danger', 'changed' => 'warning', 'same' => 'neutral'];
    $labels = ['added' => __('Added'), 'removed' => __('Removed'), 'changed' => __('Changed'), 'same' => __('Same')];
@endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
        <span class="font-mono text-sm text-muted-foreground">{{ $estimate->estimate_number }}</span>
        <h1 class="text-xl font-semibold tracking-tight md:text-2xl">{{ __('Compare revisions') }}</h1>
    </div>

    <div class="grid grid-cols-1 gap-3 md:max-w-md">
        <x-ui.field>
            <x-ui.field-label for="compare-with">{{ __('Compare :number with', ['number' => $estimate->estimate_number]) }}</x-ui.field-label>
            <x-ui.select native id="compare-with" wire:model.live="with" class="h-11 text-base md:h-9 md:text-sm">
                @foreach ($family->where('id', '!=', $estimate->id) as $revision)
                    <option value="{{ $revision->estimate_number }}">{{ $revision->estimate_number }} ({{ __('Rev. :n', ['n' => $revision->revision_no]) }})</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <label class="flex min-h-11 items-center gap-3 text-sm"><x-ui.switch wire:model.live="showSame" /> {{ __('Show unchanged lines') }}</label>
    </div>

    @if ($result === null)
        <p class="py-10 text-center text-sm text-muted-foreground">{{ __('This estimate has no other revision.') }}</p>
    @else
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ $result['from']->estimate_number }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ $money($result['from']->total_amount) }}</span>
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ $result['to']->estimate_number }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ $money($result['to']->total_amount) }}</span>
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Subtotal difference') }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ $signed($result['totals']['subtotal']) }}</span>
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Total difference') }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ $signed($result['totals']['total']) }}</span>
            </x-ui.card>
        </div>

        @foreach (['lines' => __('Work lines'), 'materials' => __('Materials')] as $group => $heading)
            @php($rows = collect($result[$group])->when(! $showSame, fn ($rows) => $rows->where('state', '!=', 'same')))
            @if (collect($result[$group])->isNotEmpty())
                <x-ui.card class="gap-3 p-4 md:p-6">
                    <h2 class="text-base font-semibold">{{ $heading }}</h2>
                    <div class="hidden overflow-x-auto md:block">
                        <x-ui.table variant="bordered">
                            <x-ui.table-header>
                                <x-ui.table-row>
                                    <x-ui.table-head>{{ __('No.') }}</x-ui.table-head>
                                    <x-ui.table-head>{{ __('Description') }}</x-ui.table-head>
                                    <x-ui.table-head>{{ __('Change') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Qty before') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Qty after') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Rate before') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Rate after') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Amount before') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Amount after') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Difference') }}</x-ui.table-head>
                                </x-ui.table-row>
                            </x-ui.table-header>
                            <x-ui.table-body>
                                @forelse ($rows as $row)
                                    <x-ui.table-row wire:key="{{ $group }}-{{ $loop->index }}">
                                        <x-ui.table-cell class="font-mono text-sm">{{ $row['line_no'] ?? '—' }}</x-ui.table-cell>
                                        <x-ui.table-cell>{{ $row['description'] }}</x-ui.table-cell>
                                        <x-ui.table-cell><x-ui.badge :tone="$tones[$row['state']]">{{ $labels[$row['state']] }}</x-ui.badge></x-ui.table-cell>
                                        <x-ui.table-cell class="text-end tabular-nums">{{ $qty($row['from']['quantity'] ?? null) }}</x-ui.table-cell>
                                        <x-ui.table-cell class="text-end tabular-nums">{{ $qty($row['to']['quantity'] ?? null) }} {{ $row['unit'] }}</x-ui.table-cell>
                                        <x-ui.table-cell class="text-end tabular-nums">{{ \App\Support\Money::formatRate($row['from']['rate'] ?? null) }}</x-ui.table-cell>
                                        <x-ui.table-cell class="text-end tabular-nums">{{ \App\Support\Money::formatRate($row['to']['rate'] ?? null) }}</x-ui.table-cell>
                                        <x-ui.table-cell class="text-end tabular-nums">{{ $money($row['from']['amount'] ?? null) }}</x-ui.table-cell>
                                        <x-ui.table-cell class="text-end tabular-nums">{{ $money($row['to']['amount'] ?? null) }}</x-ui.table-cell>
                                        <x-ui.table-cell class="text-end tabular-nums font-medium">{{ $signed($row['amount_change']) }}</x-ui.table-cell>
                                    </x-ui.table-row>
                                @empty
                                    <x-ui.table-row><x-ui.table-cell colspan="10" class="py-6 text-center text-muted-foreground">{{ __('No changes.') }}</x-ui.table-cell></x-ui.table-row>
                                @endforelse
                            </x-ui.table-body>
                        </x-ui.table>
                    </div>
                    <x-ui.item-group class="gap-2 md:hidden">
                        @forelse ($rows as $row)
                            <x-ui.item variant="outline" class="min-h-16 py-2" wire:key="m-{{ $group }}-{{ $loop->index }}">
                                <x-ui.item-content class="min-w-0">
                                    <x-ui.item-title class="text-base"><span class="truncate">{{ $row['description'] }}</span></x-ui.item-title>
                                    <x-ui.item-description class="text-sm tabular-nums">{{ $qty($row['from']['quantity'] ?? null) }} → {{ $qty($row['to']['quantity'] ?? null) }} {{ $row['unit'] }}</x-ui.item-description>
                                    <span class="text-sm tabular-nums">{{ $signed($row['amount_change']) }}</span>
                                </x-ui.item-content>
                                <x-ui.badge :tone="$tones[$row['state']]" class="shrink-0 text-sm">{{ $labels[$row['state']] }}</x-ui.badge>
                            </x-ui.item>
                        @empty
                            <p class="py-6 text-center text-sm text-muted-foreground">{{ __('No changes.') }}</p>
                        @endforelse
                    </x-ui.item-group>
                </x-ui.card>
            @endif
        @endforeach
    @endif
</div>
