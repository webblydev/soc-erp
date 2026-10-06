<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('Lead profile') }} {{ $lead->lead_number }}</title>
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
            <h1 class="text-xl font-semibold">{{ __('Lead profile') }}</h1>
            <span class="font-mono">{{ $lead->lead_number }}</span>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 print:grid-cols-2">
            <table class="w-full">
                <tbody>
                    @foreach ([
                        __('Name') => $lead->name,
                        __('Company') => $lead->company_name,
                        __('Phone') => $lead->phone,
                        __('WhatsApp') => $lead->whatsapp,
                        __('Email') => $lead->email,
                        __('Address') => $lead->address,
                        __('Location') => $lead->location?->full_path,
                    ] as $label => $value)
                        <tr class="border-b"><th class="py-1 pe-2 text-start font-medium text-muted-foreground">{{ $label }}</th><td class="py-1">{{ $value ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <table class="w-full">
                <tbody>
                    @foreach ([
                        __('Lead date') => $lead->lead_date->format('d-M-Y'),
                        __('Source') => $lead->source->name,
                        __('Status') => $lead->status->name,
                        __('Priority') => $lead->priority->name,
                        __('Level') => $lead->level?->name,
                        __('Business line') => $lead->businessLine?->name,
                        __('Assigned to') => $lead->assignee?->name,
                        __('Expected value') => $lead->expected_value !== null ? \App\Support\Money::format($lead->expected_value) : null,
                        __('Land area') => $lead->land_area,
                        __('Floors planned') => $lead->floors_planned,
                    ] as $label => $value)
                        <tr class="border-b"><th class="py-1 pe-2 text-start font-medium text-muted-foreground">{{ $label }}</th><td class="py-1">{{ $value ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <section>
            <h2 class="mb-2 font-semibold">{{ __('Services') }}</h2>
            <table class="w-full border">
                <thead class="bg-muted"><tr><th class="p-2 text-start">{{ __('Service') }}</th><th class="p-2 text-end">{{ __('Estimated value') }}</th><th class="p-2 text-start">{{ __('Note') }}</th></tr></thead>
                <tbody>
                    @foreach ($lead->services as $line)
                        <tr class="border-t"><td class="p-2">{{ $line->service->name }}</td><td class="p-2 text-end tabular-nums">{{ $line->estimated_value !== null ? \App\Support\Money::format($line->estimated_value, false) : '—' }}</td><td class="p-2">{{ $line->notes }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section>
            <h2 class="mb-2 font-semibold">{{ __('Activities') }}</h2>
            <table class="w-full border">
                <thead class="bg-muted"><tr><th class="p-2 text-start">{{ __('Date') }}</th><th class="p-2 text-start">{{ __('Type') }}</th><th class="p-2 text-start">{{ __('Title') }}</th><th class="p-2 text-start">{{ __('Outcome') }}</th><th class="p-2 text-start">{{ __('Owner') }}</th></tr></thead>
                <tbody>
                    @forelse ($lead->activities->sortByDesc(fn ($activity) => $activity->completed_at ?? $activity->scheduled_at) as $activity)
                        <tr class="border-t">
                            <td class="p-2 tabular-nums">{{ ($activity->completed_at ?? $activity->scheduled_at)?->format('d-M-Y h:i A') }}</td>
                            <td class="p-2">{{ $activity->type->name }}</td>
                            <td class="p-2">{{ $activity->title }}</td>
                            <td class="p-2">{{ $activity->outcome?->name ?? '—' }}</td>
                            <td class="p-2">{{ $activity->owner->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-2 text-muted-foreground">{{ __('No activities yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
