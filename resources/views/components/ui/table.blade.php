@props(['variant' => 'default'])   {{-- default | card (bordered, rounded, on bg-card) | bordered (ERP grid: every cell ruled, muted header, zebra rows) --}}

@php
    $container = match ($variant) {
        'card' => 'relative w-full overflow-x-auto rounded-lg border bg-card shadow-xs',
        'bordered' => 'relative w-full overflow-x-auto rounded-md border bg-card',
        default => 'relative w-full overflow-x-auto',
    };

    $table = $variant === 'bordered'
        ? 'w-full caption-bottom border-collapse text-sm [&_th]:border-e [&_th:last-child]:border-e-0 [&_td]:border-e [&_td:last-child]:border-e-0 [&_thead_tr]:bg-muted [&_thead_tr]:hover:bg-muted [&_th]:h-9 [&_th]:px-3 [&_th]:text-xs [&_th]:font-semibold [&_th]:tracking-wide [&_th]:text-muted-foreground [&_th]:uppercase [&_td]:px-3 [&_td]:py-1.5 [&_tbody_tr:nth-child(even)]:bg-muted/30 [&_tbody_tr:hover]:bg-muted/60 [&_tbody_tr:has(input[data-slot=checkbox]:checked)]:bg-primary/10!'
        : 'w-full caption-bottom text-sm';
@endphp

<div data-slot="table-container" data-variant="{{ $variant }}" class="{{ $container }}">
    <table data-slot="table" {{ $attributes->twMerge($table) }}>
        {{ $slot }}
    </table>
</div>
