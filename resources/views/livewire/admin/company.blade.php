<div class="flex flex-col gap-6 lg:grid lg:grid-cols-[1fr_24rem]">
    <x-shell.form-page wire:submit="save" :submit-label="$readOnly ? null : __('Save company profile')">
        <fieldset @disabled($readOnly) class="flex flex-col gap-6">
            @foreach ([
                'name' => [__('Company name').' *', 'text', null],
                'short_name' => [__('Short name'), 'text', null],
                'phone' => [__('Phone'), 'tel', 'tel'],
                'email' => [__('Email'), 'email', 'email'],
                'website' => [__('Website'), 'url', 'url'],
                'tin' => [__('TIN'), 'text', null],
                'bin' => [__('VAT BIN'), 'text', null],
                'trade_license_no' => [__('Trade license no.'), 'text', null],
                'print_footer' => [__('Print footer'), 'text', null],
            ] as $field => [$label, $type, $inputmode])
                <x-ui.field>
                    <x-ui.field-label for="{{ $field }}">{{ $label }}</x-ui.field-label>
                    <x-ui.input id="{{ $field }}" type="{{ $type }}" :inputmode="$inputmode" wire:model="{{ $field }}" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get($field)" />
                </x-ui.field>
            @endforeach

            <x-ui.field>
                <x-ui.field-label for="address">{{ __('Address') }}</x-ui.field-label>
                <x-ui.textarea id="address" wire:model="address" rows="3" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('address')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="base_currency_id">{{ __('Base currency') }} *</x-ui.field-label>
                <x-ui.select native id="base_currency_id" wire:model="base_currency_id" class="h-11 text-base md:h-9 md:text-sm">
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}">{{ $currency->code }} — {{ $currency->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.field-error :messages="$errors->get('base_currency_id')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="fiscal_year_start_month">{{ __('Fiscal year starts in') }} *</x-ui.field-label>
                <x-ui.select native id="fiscal_year_start_month" wire:model="fiscal_year_start_month" class="h-11 text-base md:h-9 md:text-sm">
                    @foreach (range(1, 12) as $month)
                        <option value="{{ $month }}">{{ \Illuminate\Support\Carbon::create(2026, $month, 1)->format('F') }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.field-error :messages="$errors->get('fiscal_year_start_month')" />
            </x-ui.field>

            @unless ($readOnly)
                <x-ui.field>
                    <x-ui.field-label for="logo">{{ __('Logo (PNG or JPG, up to 1 MB)') }}</x-ui.field-label>
                    <x-ui.input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg" class="h-11 md:h-9" />
                    <x-ui.field-error :messages="$errors->get('logo')" />
                </x-ui.field>
            @endunless
        </fieldset>
    </x-shell.form-page>

    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title>{{ __('Print preview') }}</x-ui.card-title>
        </x-ui.card-header>
        <x-ui.card-content>
            <x-print.letterhead :company="$company" />
        </x-ui.card-content>
    </x-ui.card>
</div>
