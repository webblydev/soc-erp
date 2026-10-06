{{-- Selection cell of one list-table row; bound to the component's $selected (WithBulkActions). --}}
@props(['id', 'label'])

<x-ui.table-cell class="w-10 text-center">
    <x-ui.checkbox native wire:model="selected" value="{{ $id }}" :aria-label="__('Select :name', ['name' => $label])" />
</x-ui.table-cell>
