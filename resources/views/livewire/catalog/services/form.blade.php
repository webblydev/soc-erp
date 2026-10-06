<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$service ? $service->name : __('New service')"
        :description="__('What SOC sells, with its default pricing for quotations and invoices.')"
        :cancel-url="route('catalog.services.index')"
        :submit-label="$service ? __('Save changes') : __('Create service')">

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
                    <x-ui.field-label for="service_category_id">{{ __('Category') }} *</x-ui.field-label>
                    <x-lookup-select table="service_categories" :include="$service?->service_category_id" :placeholder="__('Choose…')" id="service_category_id" wire:model="service_category_id" />
                    <x-ui.field-error :messages="$errors->get('service_category_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="business_line_id">{{ __('Business line') }}</x-ui.field-label>
                    <x-ui.select native id="business_line_id" wire:model="business_line_id" class="h-11 text-base md:h-9 md:text-sm">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($businessLines as $line)
                            <option value="{{ $line->id }}">{{ $line->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('business_line_id')" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="description">{{ __('Description') }}</x-ui.field-label>
                <x-ui.textarea id="description" wire:model="description" rows="3" class="text-base md:text-sm" />
                <x-ui.field-description>{{ __('Shown on quotations and invoices.') }}</x-ui.field-description>
                <x-ui.field-error :messages="$errors->get('description')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Pricing')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <x-ui.field>
                    <x-ui.field-label for="pricing_basis_id">{{ __('Pricing basis') }} *</x-ui.field-label>
                    <x-lookup-select table="pricing_bases" :include="$service?->pricing_basis_id" :placeholder="__('Choose…')" id="pricing_basis_id" wire:model="pricing_basis_id" />
                    <x-ui.field-error :messages="$errors->get('pricing_basis_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="default_unit_id">{{ __('Default unit') }}</x-ui.field-label>
                    <x-lookup-select table="units" :include="$service?->default_unit_id" :placeholder="__('None')" id="default_unit_id" wire:model="default_unit_id" />
                    <x-ui.field-error :messages="$errors->get('default_unit_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="default_rate">{{ __('Default rate') }}</x-ui.field-label>
                    <x-ui.input id="default_rate" wire:model="default_rate" inputmode="decimal" class="h-11 text-end text-base tabular-nums md:h-9 md:text-sm" :aria-invalid="$errors->has('default_rate') ? 'true' : null" />
                    <x-ui.field-error :messages="$errors->get('default_rate')" />
                </x-ui.field>
            </div>
        </x-shell.form-section>

        <x-slot:aside>
            <x-shell.form-section :title="__('Status')">
                <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                    <x-ui.switch id="is_active" wire:model="is_active" :checked="$is_active" />
                    <x-ui.field-label for="is_active">{{ __('Active') }}</x-ui.field-label>
                </x-ui.field>
                <x-ui.field orientation="horizontal" class="min-h-11 items-start">
                    <x-ui.switch id="requires_approval_tracking" wire:model="requires_approval_tracking" :checked="$requires_approval_tracking" />
                    <x-ui.field-content>
                        <x-ui.field-label for="requires_approval_tracking">{{ __('Requires approval tracking') }}</x-ui.field-label>
                        <x-ui.field-description>{{ __('Creates an approval checklist when added to a project.') }}</x-ui.field-description>
                    </x-ui.field-content>
                </x-ui.field>
            </x-shell.form-section>
        </x-slot:aside>
    </x-shell.form-screen>

    @if ($service)
        <div class="mx-auto mt-6 w-full max-w-6xl pb-28 md:pb-0">
            <livewire:foundation.history :model="$service" :key="'history-'.$service->id" />
        </div>
    @endif
</div>
