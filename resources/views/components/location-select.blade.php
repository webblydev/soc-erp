{{--
    A searchable location picker (docs/01 §3.7): active locations by full path, plus the id the
    record already holds so an inactive value still shows (CM-BR-03). Forwards wire:model.
--}}
@props(['include' => null, 'placeholder' => null])

@php
    $options = \App\Modules\Foundation\Models\Location::query()
        ->where(fn ($query) => $query->where('is_active', true)->when($include, fn ($query) => $query->orWhere('id', $include)))
        ->orderBy('full_path')
        ->get(['id', 'full_path'])
        ->map(fn ($location) => ['value' => (string) $location->id, 'label' => $location->full_path])
        ->all();
@endphp

<x-ui.combobox :options="$options" :placeholder="$placeholder ?? __('Choose a location…')" :search-placeholder="__('Search places')" width="w-full" {{ $attributes->twMerge('min-h-11 md:min-h-9') }} />
