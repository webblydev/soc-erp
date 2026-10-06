<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$workItem ? $workItem->name : __('New work item')"
        :description="__('A BOQ / measurement item with its unit, measurement formula and standard rate.')"
        :cancel-url="route('catalog.work-items.index')"
        :submit-label="$workItem ? __('Save changes') : __('Create work item')">

        <x-shell.form-section :title="__('Details')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="code">{{ __('Code') }} *</x-ui.field-label>
                    <x-ui.input id="code" wire:model="code" autocapitalize="characters" autocomplete="off" class="h-11 font-mono text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('code') ? 'true' : null" />
                    <x-ui.field-description>{{ __('Letters, digits, dot, dash and underscore. Stored in capitals.') }}</x-ui.field-description>
                    <x-ui.field-error :messages="$errors->get('code')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="name">{{ __('Name') }} *</x-ui.field-label>
                    <x-ui.input id="name" wire:model="name" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('name') ? 'true' : null" />
                    <x-ui.field-error :messages="$errors->get('name')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="work_item_category_id">{{ __('Category') }} *</x-ui.field-label>
                    <x-lookup-select table="work_item_categories" :include="$workItem?->work_item_category_id" :placeholder="__('Choose…')" id="work_item_category_id" wire:model="work_item_category_id" />
                    <x-ui.field-error :messages="$errors->get('work_item_category_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="unit_id">{{ __('Unit') }} *</x-ui.field-label>
                    <x-lookup-select table="units" show-code :include="$workItem?->unit_id" :placeholder="__('Choose…')" id="unit_id" wire:model="unit_id" />
                    <x-ui.field-error :messages="$errors->get('unit_id')" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="specification">{{ __('Specification') }}</x-ui.field-label>
                <x-ui.textarea id="specification" wire:model="specification" rows="4" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('specification')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Measurement')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field x-data="{ labels: @js(collect($formulas)->mapWithKeys(fn ($formula) => [$formula->value => $formula->label()])) }">
                    <x-ui.field-label for="measurement_formula">{{ __('Formula') }} *</x-ui.field-label>
                    <x-ui.select native id="measurement_formula" wire:model="measurement_formula" class="h-11 text-base md:h-9 md:text-sm">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($formulas as $formula)
                            <option value="{{ $formula->value }}">{{ $formula->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-description
                        x-text="$wire.measurement_formula === 'manual' ? @js(__('Quantity is typed in.')) : (labels[$wire.measurement_formula] ? @js(__('Quantity = ')) + labels[$wire.measurement_formula] : '')"></x-ui.field-description>
                    <x-ui.field-error :messages="$errors->get('measurement_formula')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="standard_rate">{{ __('Standard rate') }}</x-ui.field-label>
                    <x-ui.input id="standard_rate" wire:model="standard_rate" inputmode="decimal" class="h-11 text-end text-base tabular-nums md:h-9 md:text-sm" :aria-invalid="$errors->has('standard_rate') ? 'true' : null" />
                    <x-ui.field-description>{{ __('e.g. the PWD schedule rate.') }}</x-ui.field-description>
                    <x-ui.field-error :messages="$errors->get('standard_rate')" />
                </x-ui.field>
            </div>
        </x-shell.form-section>

        <x-slot:aside>
            <x-shell.form-section :title="__('Status')">
                <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                    <x-ui.switch id="is_active" wire:model="is_active" :checked="$is_active" />
                    <x-ui.field-label for="is_active">{{ __('Active') }}</x-ui.field-label>
                </x-ui.field>
            </x-shell.form-section>
        </x-slot:aside>
    </x-shell.form-screen>

    @if ($workItem)
        <div class="mx-auto mt-6 w-full max-w-6xl pb-28 md:pb-0">
            <livewire:foundation.history :model="$workItem" :key="'history-'.$workItem->id" />
        </div>
    @endif
</div>
