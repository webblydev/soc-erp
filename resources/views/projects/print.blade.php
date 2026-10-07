@php
    $date = fn ($value) => $value?->format('d-M-Y');
    $money = fn ($value) => \App\Support\Money::format($value);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('Project sheet') }} {{ $project->project_number }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 15mm; }</style>
</head>
<body class="bg-white text-sm text-foreground">
    <main class="mx-auto flex max-w-3xl flex-col gap-6 p-6 print:p-0">
        <div class="flex justify-end print:hidden">
            <x-ui.button onclick="window.print()"><x-lucide-printer /> {{ __('Print') }}</x-ui.button>
        </div>

        <x-print.letterhead :company="$company" />

        <div class="flex items-baseline justify-between gap-4">
            <h1 class="text-xl font-semibold">{{ __('Project sheet') }}</h1>
            <span class="font-mono">{{ $project->project_number }}</span>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 print:grid-cols-2">
            <table class="w-full">
                <tbody>
                    @foreach ([
                        __('Project') => $project->name,
                        __('Customer') => $project->customer?->name ?? __('Internal'),
                        __('Phone') => $project->customer?->phone,
                        __('Business line') => $project->businessLine->name,
                        __('Type') => $project->type->name,
                        __('Status') => $project->status->name,
                        __('Phase') => $project->phase?->name,
                    ] as $label => $value)
                        <tr class="border-b"><th class="py-1 pe-2 text-start font-medium text-muted-foreground">{{ $label }}</th><td class="py-1">{{ $value ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <table class="w-full">
                <tbody>
                    @foreach ([
                        __('Site') => $project->site_address,
                        __('Location') => $project->location?->full_path,
                        __('Plot no.') => $project->plot_no,
                        __('Floors / basements') => ($project->floors ?? '—').' / '.($project->basements ?? '—'),
                        __('Start') => $date($project->start_date),
                        __('Expected end') => $date($project->expected_end_date),
                        __('Project manager') => $project->manager?->full_name,
                    ] as $label => $value)
                        <tr class="border-b"><th class="py-1 pe-2 text-start font-medium text-muted-foreground">{{ $label }}</th><td class="py-1">{{ $value ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <section>
            <h2 class="mb-2 font-semibold">{{ __('Services') }}</h2>
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b text-start">
                        <th class="py-1 text-start">{{ __('Service') }}</th>
                        <th class="py-1 text-end">{{ __('Qty') }}</th>
                        <th class="py-1 text-end">{{ __('Rate') }}</th>
                        <th class="py-1 text-end">{{ __('Discount') }}</th>
                        <th class="py-1 text-end">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->services as $line)
                        <tr @class(['border-b', 'text-muted-foreground line-through' => $line->isCancelled()])>
                            <td class="py-1">{{ $line->service->name }}@if ($line->description) — {{ $line->description }}@endif</td>
                            <td class="py-1 text-end tabular-nums">{{ rtrim(rtrim($line->quantity, '0'), '.') }} {{ $line->unit?->symbol }}</td>
                            <td class="py-1 text-end tabular-nums">{{ \App\Support\Money::formatRate($line->rate) }}</td>
                            <td class="py-1 text-end tabular-nums">{{ \App\Support\Money::format($line->discount_amount, false) }}</td>
                            <td class="py-1 text-end tabular-nums">{{ \App\Support\Money::format($line->amount, false) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th colspan="4" class="py-1 text-end">{{ __('Contract value') }}</th><th class="py-1 text-end tabular-nums">{{ $money($project->contract_value) }}</th></tr>
                </tfoot>
            </table>
        </section>

        @if ($canSeeContract && $project->contract)
            <section>
                <h2 class="mb-2 font-semibold">{{ __('Contract') }}</h2>
                <p>
                    {{ $project->contract->status->name }}
                    @if ($project->contract->contract_number) · {{ $project->contract->contract_number }} @endif
                    · {{ __('Agreement') }} {{ $date($project->contract->agreement_date) }}
                    · {{ __('Deed') }} {{ $money($project->contract->deed_amount) }}
                </p>
            </section>
        @endif

        @if ($canSeeContract && $project->schedules->isNotEmpty())
            <section>
                <h2 class="mb-2 font-semibold">{{ __('Payment schedule') }}</h2>
                <table class="w-full border-collapse">
                    <tbody>
                        @foreach ($project->schedules as $line)
                            <tr class="border-b">
                                <td class="py-1">{{ $line->milestone_name }}</td>
                                <td class="py-1">{{ $line->trigger->name }}@if ($line->due_date) · {{ $date($line->due_date) }}@endif</td>
                                <td class="py-1">{{ $line->status->name }}</td>
                                <td class="py-1 text-end tabular-nums">{{ $money($line->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <section>
            <h2 class="mb-2 font-semibold">{{ __('Team') }}</h2>
            <p>{{ $project->activeTeam->map(fn ($member) => $member->employee->full_name.' ('.$member->role->name.')')->implode(', ') ?: '—' }}</p>
        </section>

        <div class="mt-12 grid grid-cols-4 gap-6 text-center print:mt-16">
            @foreach ([__('Prepared by'), __('Checked by'), __('Approved by'), __('Received by')] as $label)
                <div class="border-t pt-1">{{ $label }}</div>
            @endforeach
        </div>
    </main>
</body>
</html>
