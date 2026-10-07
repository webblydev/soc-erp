{{-- Site inspection report (docs/05 §5.8, ES-AC-06) with the v1 Project Visit fields and the findings with photos. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('Site inspection report') }} {{ $inspection->inspection_number }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 12mm; }</style>
</head>
<body class="bg-white text-sm text-foreground">
    <main class="mx-auto flex max-w-3xl flex-col gap-6 p-6 print:p-0">
        <div class="flex justify-end print:hidden">
            <x-ui.button onclick="window.print()"><x-lucide-printer /> {{ __('Print') }}</x-ui.button>
        </div>

        <x-print.letterhead :company="$company" />

        <div class="flex items-baseline justify-between gap-4">
            <h1 class="text-xl font-semibold">{{ __('Site inspection report') }}</h1>
            <span class="font-mono">{{ $inspection->inspection_number }}</span>
        </div>

        <table class="w-full">
            <tbody>
                @foreach ([
                    __('Project') => $inspection->project->name,
                    __('Construction ID') => $inspection->project->project_number,
                    __('Type of inspection') => $inspection->type->name,
                    __('Inspection date') => $inspection->inspection_date->format('d-M-Y'),
                    __('Start / end time') => collect([$inspection->start_time ? substr($inspection->start_time, 0, 5) : null, $inspection->end_time ? substr($inspection->end_time, 0, 5) : null])->filter()->implode(' – '),
                    __('Location') => $inspection->site_address,
                    __('Constructor') => $inspection->contractor_name,
                    __('Project engineer') => $inspection->engineerName(),
                    __('Permittee') => $inspection->permittee_name,
                    __('Field office phone') => $inspection->field_office_phone,
                    __('Client representative') => $inspection->client_representative,
                    __('Weather') => $inspection->weather,
                    __('Workers on site') => $inspection->workers_on_site,
                    __('Status') => $inspection->status->name,
                ] as $label => $value)
                    <tr class="border-b"><th class="w-44 py-1 pe-2 text-start font-medium text-muted-foreground">{{ $label }}</th><td class="py-1">{{ filled($value) ? $value : '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>

        @if ($inspection->work_progress_summary)
            <section class="flex flex-col gap-1">
                <h2 class="font-semibold">{{ __('Description') }}</h2>
                <p class="whitespace-pre-line">{{ $inspection->work_progress_summary }}</p>
            </section>
        @endif

        <section class="flex flex-col gap-2">
            <h2 class="font-semibold">{{ __('Findings') }}</h2>
            <table class="w-full border">
                <thead class="bg-muted/50">
                    <tr>
                        <th class="border px-2 py-1 text-start">#</th>
                        <th class="border px-2 py-1 text-start">{{ __('Location of finding') }}</th>
                        <th class="border px-2 py-1 text-start">{{ __('Description') }}</th>
                        <th class="border px-2 py-1 text-start">{{ __('Severity') }}</th>
                        <th class="border px-2 py-1 text-start">{{ __('Finding by') }}</th>
                        <th class="border px-2 py-1 text-start">{{ __('Responsible / due') }}</th>
                        <th class="border px-2 py-1 text-start">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inspection->findings as $finding)
                        <tr class="align-top">
                            <td class="border px-2 py-1">{{ $loop->iteration }}</td>
                            <td class="border px-2 py-1">{{ $finding->location }}</td>
                            <td class="border px-2 py-1">
                                {{ $finding->description }}
                                @if ($finding->action_required)<span class="block text-muted-foreground">{{ __('Action') }}: {{ $finding->action_required }}</span>@endif
                                @if ($finding->closure_note)<span class="block text-muted-foreground">{{ __('Closed') }}: {{ $finding->closure_note }}</span>@endif
                            </td>
                            <td class="border px-2 py-1">{{ $finding->severity->name }}</td>
                            <td class="border px-2 py-1">{{ $finding->found_by_name }}</td>
                            <td class="border px-2 py-1">{{ $finding->responsibleName() }}@if ($finding->due_date)<span class="block">{{ $finding->due_date->format('d-M-Y') }}</span>@endif</td>
                            <td class="border px-2 py-1">{{ $finding->status->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="border px-2 py-3 text-center text-muted-foreground">{{ __('No findings recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        @php($photos = $inspection->findings->flatMap(fn ($finding) => $finding->attachments->filter(fn ($attachment) => str_starts_with($attachment->mime_type, 'image/'))->map(fn ($attachment) => [$finding, $attachment])))
        @if ($photos->isNotEmpty())
            <section class="flex flex-col gap-2 break-before-page">
                <h2 class="font-semibold">{{ __('Photos') }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($photos as [$finding, $photo])
                        <figure class="flex flex-col gap-1 break-inside-avoid">
                            <img src="{{ $photo->downloadUrl() }}" alt="{{ $photo->title }}" class="aspect-[4/3] w-full rounded object-cover">
                            <figcaption class="text-muted-foreground">{{ $photo->title ?? $finding->location }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mt-12 grid grid-cols-3 gap-6 text-center">
            <div class="border-t pt-1">{{ __('Project engineer') }}</div>
            <div class="border-t pt-1">{{ __('Contractor') }}</div>
            <div class="border-t pt-1">{{ __('Project manager') }}</div>
        </div>
    </main>
</body>
</html>
