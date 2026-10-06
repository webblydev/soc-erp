{{--
    Header cell of a list table's selection column: ticks or clears every row on the current
    page in $wire.selected without a round trip. Pass the page's row ids.
--}}
@props(['ids'])

@php
    $ids = collect($ids)->map(fn ($id) => (string) $id)->values()->all();
@endphp

<x-ui.table-head class="w-10 text-center" data-test="select-all">
    <x-ui.checkbox native :aria-label="__('Select all on this page')"
        x-data="{ ids: {{ \Illuminate\Support\Js::from($ids) }} }"
        x-bind:checked="ids.length > 0 && ids.every((id) => $wire.selected.includes(id))"
        x-bind:indeterminate="ids.some((id) => $wire.selected.includes(id)) && ! ids.every((id) => $wire.selected.includes(id))"
        x-on:change="$wire.selected = $event.target.checked ? [...new Set([...$wire.selected, ...ids])] : $wire.selected.filter((id) => ! ids.includes(id))" />
</x-ui.table-head>
