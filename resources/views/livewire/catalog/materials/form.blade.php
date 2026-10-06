<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$material ? $material->name : __('New material')"
        :description="__('A construction material with its unit and standard rate.')"
        :cancel-url="route('catalog.materials.index')"
        :submit-label="$material ? __('Save changes') : __('Create material')">

        <x-shell.form-section :title="__('Details')">
            <div class="grid gap-6 md:grid-cols-2">
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
                    <x-ui.field-label for="material_category_id">{{ __('Category') }} *</x-ui.field-label>
                    <x-lookup-select table="material_categories" :include="$material?->material_category_id" :placeholder="__('Choose…')" id="material_category_id" wire:model="material_category_id" />
                    <x-ui.field-error :messages="$errors->get('material_category_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="unit_id">{{ __('Unit') }} *</x-ui.field-label>
                    <x-lookup-select table="units" show-code :include="$material?->unit_id" :placeholder="__('Choose…')" id="unit_id" wire:model="unit_id" />
                    <x-ui.field-error :messages="$errors->get('unit_id')" />
                </x-ui.field>
            </div>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Pricing')">
            <div class="grid gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="standard_rate">{{ __('Standard rate') }}</x-ui.field-label>
                    <x-ui.input id="standard_rate" wire:model="standard_rate" inputmode="decimal" class="h-11 text-end text-base tabular-nums md:h-9 md:text-sm" :aria-invalid="$errors->has('standard_rate') ? 'true' : null" />
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

    @if ($material)
        <div class="mx-auto mt-6 w-full max-w-6xl pb-28 md:pb-0">
            <livewire:foundation.history :model="$material" :key="'history-'.$material->id" />
        </div>
    @endif
</div>
