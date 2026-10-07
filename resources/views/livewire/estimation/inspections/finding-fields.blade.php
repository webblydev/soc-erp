{{-- Finding fields bound under $prefix (e.g. "findings.0" or "newFinding"). Needs $followUpSeverities, $projectHasCustomer. --}}
@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $finding = data_get($this, $prefix);
    $followUp = in_array((int) ($finding['finding_severity_id'] ?? 0), $followUpSeverities, true);
    $fieldError = fn ($field) => $errors->get($errorPrefix.'.'.$field);
@endphp
<x-ui.field>
    <x-ui.field-label for="{{ $idPrefix }}-location">{{ __('Location of finding') }}</x-ui.field-label>
    <x-ui.input id="{{ $idPrefix }}-location" wire:model="{{ $prefix }}.location" class="{{ $input }}" :placeholder="__('e.g. 1st floor roof slab')" />
</x-ui.field>
<x-ui.field>
    <x-ui.field-label for="{{ $idPrefix }}-description">{{ __('Description') }} *</x-ui.field-label>
    <x-ui.textarea id="{{ $idPrefix }}-description" wire:model="{{ $prefix }}.description" rows="2" class="text-base md:text-sm" />
    <x-ui.field-error :messages="$fieldError('description')" />
</x-ui.field>
<div class="grid grid-cols-2 gap-3">
    <x-ui.field>
        <x-ui.field-label for="{{ $idPrefix }}-severity">{{ __('Severity') }} *</x-ui.field-label>
        <x-lookup-select table="finding_severities" :include="$finding['finding_severity_id'] ?? null" id="{{ $idPrefix }}-severity" wire:model.live="{{ $prefix }}.finding_severity_id" />
        <x-ui.field-error :messages="$fieldError('finding_severity_id')" />
    </x-ui.field>
    <x-ui.field>
        <x-ui.field-label for="{{ $idPrefix }}-category">{{ __('Category') }}</x-ui.field-label>
        <x-lookup-select table="finding_categories" :include="$finding['finding_category_id'] ?? null" :placeholder="__('None')" id="{{ $idPrefix }}-category" wire:model="{{ $prefix }}.finding_category_id" />
    </x-ui.field>
</div>
<x-ui.field>
    <x-ui.field-label for="{{ $idPrefix }}-finding">{{ __('Finding') }}</x-ui.field-label>
    <x-ui.textarea id="{{ $idPrefix }}-finding" wire:model="{{ $prefix }}.finding" rows="2" class="text-base md:text-sm" />
</x-ui.field>
<x-ui.field>
    <x-ui.field-label for="{{ $idPrefix }}-action">{{ __('Action required') }}</x-ui.field-label>
    <x-ui.textarea id="{{ $idPrefix }}-action" wire:model="{{ $prefix }}.action_required" rows="2" class="text-base md:text-sm" />
</x-ui.field>
<div class="grid grid-cols-1 gap-3 md:grid-cols-2">
    <x-ui.field>
        <x-ui.field-label for="{{ $idPrefix }}-responsible-type">{{ __('Responsible') }}{{ $followUp ? ' *' : '' }}</x-ui.field-label>
        <x-ui.select native id="{{ $idPrefix }}-responsible-type" wire:model.live="{{ $prefix }}.responsible_type" class="{{ $input }}">
            <option value="">{{ __('Nobody yet') }}</option>
            <option value="employee">{{ __('Employee') }}</option>
            @if ($projectHasCustomer)<option value="customer">{{ __('Customer') }}</option>@endif
            <option value="contractor">{{ __('Contractor') }}</option>
        </x-ui.select>
        <x-ui.field-error :messages="$fieldError('responsible_type')" />
    </x-ui.field>
    @if (($finding['responsible_type'] ?? '') === 'employee')
        <x-ui.field>
            <x-ui.field-label for="{{ $idPrefix }}-responsible">{{ __('Employee') }} *</x-ui.field-label>
            <x-employee-select id="{{ $idPrefix }}-responsible" wire:model="{{ $prefix }}.responsible_id" :include="$finding['responsible_id'] ?? null" :placeholder="__('Choose…')" />
            <x-ui.field-error :messages="$fieldError('responsible_id')" />
        </x-ui.field>
    @endif
</div>
<div class="grid grid-cols-2 gap-3">
    <x-ui.field>
        <x-ui.field-label for="{{ $idPrefix }}-due">{{ __('Due date') }}{{ $followUp ? ' *' : '' }}</x-ui.field-label>
        <x-ui.input type="date" id="{{ $idPrefix }}-due" wire:model="{{ $prefix }}.due_date" class="{{ $input }}" />
        <x-ui.field-error :messages="$fieldError('due_date')" />
    </x-ui.field>
    <x-ui.field>
        <x-ui.field-label for="{{ $idPrefix }}-found-by">{{ __('Found by') }}</x-ui.field-label>
        <x-ui.input id="{{ $idPrefix }}-found-by" wire:model="{{ $prefix }}.found_by_name" class="{{ $input }}" />
    </x-ui.field>
</div>
