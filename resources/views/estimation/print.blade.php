{{-- Estimate prints (docs/05 §5.2, spec E20): measurement sheet, abstract of cost, material statement. --}}
@php
    $money = fn ($value) => \App\Support\Money::format($value, false);
    $qty = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    $titles = ['measurement' => __('Measurement sheet'), 'abstract' => __('Abstract of cost'), 'materials' => __('Material statement')];
    $groups = $estimate->lines->groupBy(fn ($line) => (string) $line->section?->name);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $titles[$layout] }} {{ $estimate->estimate_number }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4 {{ $layout === 'measurement' ? 'landscape' : 'portrait' }}; margin: 12mm; }</style>
</head>
<body class="bg-white text-sm text-foreground">
    <main class="mx-auto flex max-w-5xl flex-col gap-6 p-6 print:p-0">
        <div class="flex justify-end print:hidden">
            <x-ui.button onclick="window.print()"><x-lucide-printer /> {{ __('Print') }}</x-ui.button>
        </div>

        <x-print.letterhead :company="$company" />

        <div class="flex items-baseline justify-between gap-4">
            <h1 class="text-xl font-semibold">{{ $titles[$layout] }}</h1>
            <span class="font-mono">{{ $estimate->estimate_number }} · {{ __('Rev. :n', ['n' => $estimate->revision_no]) }}</span>
        </div>

        <table class="w-full">
            <tbody>
                @foreach ([
                    __('Name of work') => $estimate->title,
                    __('Project') => $estimate->project->project_number.' · '.$estimate->project->name,
                    __('Site') => $estimate->site_address,
                    __('Date') => $estimate->estimate_date->format('d-M-Y'),
                    __('Status') => $estimate->status->name,
                    __('Prepared by') => $estimate->preparer->full_name,
                    __('Checked by') => $estimate->checker?->full_name,
                ] as $label => $value)
                    <tr class="border-b"><th class="w-40 py-1 pe-2 text-start font-medium text-muted-foreground">{{ $label }}</th><td class="py-1">{{ $value ?: '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>

        @if ($layout === 'materials')
            <table class="w-full border">
                <thead class="bg-muted/50">
                    <tr>
                        <th class="border px-2 py-1 text-start">#</th>
                        <th class="border px-2 py-1 text-start">{{ __('Material') }}</th>
                        <th class="border px-2 py-1 text-end">{{ __('Est. qty') }}</th>
                        <th class="border px-2 py-1 text-end">{{ __('Wastage %') }}</th>
                        <th class="border px-2 py-1 text-end">{{ __('Total qty') }}</th>
                        <th class="border px-2 py-1 text-start">{{ __('Unit') }}</th>
                        <th class="border px-2 py-1 text-end">{{ __('Rate') }}</th>
                        <th class="border px-2 py-1 text-end">{{ __('Amount') }}</th>
                        <th class="border px-2 py-1 text-start">{{ __('Purpose') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($estimate->materialLines as $line)
                        <tr>
                            <td class="border px-2 py-1">{{ $loop->iteration }}</td>
                            <td class="border px-2 py-1">{{ $line->displayName() }}</td>
                            <td class="border px-2 py-1 text-end tabular-nums">{{ $qty($line->estimated_qty) }}</td>
                            <td class="border px-2 py-1 text-end tabular-nums">{{ $qty($line->wastage_pct) }}</td>
                            <td class="border px-2 py-1 text-end tabular-nums">{{ $qty($line->total_qty) }}</td>
                            <td class="border px-2 py-1">{{ $line->unit->symbol }}</td>
                            <td class="border px-2 py-1 text-end tabular-nums">{{ $line->rate !== null ? \App\Support\Money::formatRate($line->rate) : '' }}</td>
                            <td class="border px-2 py-1 text-end tabular-nums">{{ $line->rate !== null ? $money($line->amount) : '' }}</td>
                            <td class="border px-2 py-1">{{ $line->purpose }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <table class="w-full border">
                <thead class="bg-muted/50">
                    <tr>
                        <th class="border px-2 py-1 text-start">{{ __('No.') }}</th>
                        <th class="border px-2 py-1 text-start">{{ __('Description of work') }}</th>
                        @if ($layout === 'measurement')
                            <th class="border px-2 py-1 text-start">{{ __('Level / location') }}</th>
                            <th class="border px-2 py-1 text-end">{{ __('Nos') }}</th>
                            <th class="border px-2 py-1 text-end">{{ __('L') }}</th>
                            <th class="border px-2 py-1 text-end">{{ __('W') }}</th>
                            <th class="border px-2 py-1 text-end">{{ __('H') }}</th>
                        @endif
                        <th class="border px-2 py-1 text-end">{{ __('Quantity') }}</th>
                        <th class="border px-2 py-1 text-start">{{ __('Unit') }}</th>
                        @if ($layout === 'abstract')
                            <th class="border px-2 py-1 text-end">{{ __('Rate') }}</th>
                            <th class="border px-2 py-1 text-end">{{ __('Amount') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $sectionName => $lines)
                        @if ($sectionName !== '')
                            <tr><td colspan="{{ $layout === 'measurement' ? 9 : 6 }}" class="border bg-muted/30 px-2 py-1 font-semibold">{{ $sectionName }}</td></tr>
                        @endif
                        @foreach ($lines as $line)
                            <tr>
                                <td class="border px-2 py-1 font-mono">{{ $line->line_no }}</td>
                                <td class="border px-2 py-1">{{ $line->deduction ? __('Deduct: ') : '' }}{{ $line->description }}</td>
                                @if ($layout === 'measurement')
                                    <td class="border px-2 py-1">{{ collect([$line->level, $line->location])->filter()->implode(' · ') }}</td>
                                    @foreach (['nos', 'length', 'width', 'height'] as $dimension)
                                        <td class="border px-2 py-1 text-end tabular-nums">{{ $line->quantity_is_manual ? '' : $qty($line->{$dimension}) }}</td>
                                    @endforeach
                                @endif
                                <td class="border px-2 py-1 text-end tabular-nums">{{ $line->deduction ? '−' : '' }}{{ $qty($line->quantity) }}</td>
                                <td class="border px-2 py-1">{{ $line->unit->symbol }}</td>
                                @if ($layout === 'abstract')
                                    <td class="border px-2 py-1 text-end tabular-nums">{{ \App\Support\Money::formatRate($line->rate) }}</td>
                                    <td class="border px-2 py-1 text-end tabular-nums">{{ $money($line->amount) }}</td>
                                @endif
                            </tr>
                        @endforeach
                        @if ($layout === 'abstract' && $sectionName !== '')
                            <tr><td colspan="5" class="border px-2 py-1 text-end font-medium">{{ __('Subtotal :section', ['section' => $sectionName]) }}</td><td class="border px-2 py-1 text-end font-medium tabular-nums">{{ $money($lines->reduce(fn ($sum, $line) => bcadd($sum, (string) $line->amount, 2), '0')) }}</td></tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($layout !== 'measurement' && ($layout === 'abstract' || $estimate->totalsFromMaterials()))
            <table class="ms-auto w-full max-w-sm">
                <tbody>
                    <tr class="border-b"><th class="py-1 text-start font-medium">{{ __('Subtotal') }}</th><td class="py-1 text-end tabular-nums">{{ $money($estimate->subtotal) }}</td></tr>
                    @foreach (['overhead' => __('Overhead'), 'profit' => __('Profit'), 'vat' => __('VAT')] as $part => $label)
                        @if ((float) $estimate->{$part.'_amount'} != 0)
                            <tr class="border-b"><th class="py-1 text-start font-medium">{{ $label }} {{ (float) $estimate->{$part.'_pct'} }}%</th><td class="py-1 text-end tabular-nums">{{ $money($estimate->{$part.'_amount'}) }}</td></tr>
                        @endif
                    @endforeach
                    <tr><th class="py-1 text-start text-base font-semibold">{{ __('Total') }}</th><td class="py-1 text-end text-base font-semibold tabular-nums">{{ \App\Support\Money::format($estimate->total_amount) }}</td></tr>
                </tbody>
            </table>
        @endif

        <div class="mt-12 grid grid-cols-3 gap-6 text-center">
            <div class="border-t pt-1">{{ __('Prepared by') }}</div>
            <div class="border-t pt-1">{{ __('Checked by') }}</div>
            <div class="border-t pt-1">{{ __('Approved by') }}</div>
        </div>
    </main>
</body>
</html>
