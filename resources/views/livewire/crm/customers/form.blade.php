<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$customer ? $customer->name : __('New customer')"
        :description="__('Who we work for, how to reach them and who looks after them.')"
        :cancel-url="route('crm.customers.index')"
        :submit-label="$customer ? __('Save changes') : __('Create customer')">

        <x-slot:alerts>
            @if ($errors->has('duplicate_reason') || $duplicate_reason !== '')
                <x-ui.alert variant="destructive">
                    <x-lucide-copy />
                    <x-ui.alert-title>{{ __('Possible duplicate') }}</x-ui.alert-title>
                    <x-ui.alert-description class="flex w-full flex-col gap-3">
                        @foreach ($errors->get('duplicate_reason') as $message)
                            <p>{{ $message }}</p>
                        @endforeach
                        <x-ui.field>
                            <x-ui.field-label for="duplicate_reason">{{ __('Reason') }}</x-ui.field-label>
                            <x-ui.input id="duplicate_reason" wire:model="duplicate_reason" class="h-11 text-base md:h-9 md:text-sm" :placeholder="__('Why save it anyway?')" />
                        </x-ui.field>
                    </x-ui.alert-description>
                </x-ui.alert>
            @endif
        </x-slot:alerts>

        <x-shell.form-section :title="__('Identity')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="customer_type_id">{{ __('Customer type') }} *</x-ui.field-label>
                    <x-lookup-select table="customer_types" :include="$customer?->customer_type_id" :placeholder="__('Choose…')" id="customer_type_id" wire:model="customer_type_id" />
                    <x-ui.field-error :messages="$errors->get('customer_type_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="name">{{ __('Name') }} *</x-ui.field-label>
                    <x-ui.input id="name" wire:model="name" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('name') ? 'true' : null" />
                    <x-ui.field-error :messages="$errors->get('name')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="company_name">{{ __('Company') }}</x-ui.field-label>
                    <x-ui.input id="company_name" wire:model="company_name" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('company_name')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="nid_or_reg_no">{{ __('NID / registration no.') }}</x-ui.field-label>
                    <x-ui.input id="nid_or_reg_no" wire:model="nid_or_reg_no" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('nid_or_reg_no')" />
                </x-ui.field>
            </div>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Contact')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="phone">{{ __('Phone') }} *</x-ui.field-label>
                    <x-ui.input id="phone" type="tel" inputmode="tel" wire:model="phone" class="h-11 text-base tabular-nums md:h-9 md:text-sm" :aria-invalid="$errors->has('phone') ? 'true' : null" />
                    <x-ui.field-error :messages="$errors->get('phone')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="alternate_phone">{{ __('Alternate phone') }}</x-ui.field-label>
                    <x-ui.input id="alternate_phone" type="tel" inputmode="tel" wire:model="alternate_phone" class="h-11 text-base tabular-nums md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('alternate_phone')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="whatsapp">{{ __('WhatsApp') }}</x-ui.field-label>
                    <x-ui.input id="whatsapp" type="tel" inputmode="tel" wire:model="whatsapp" class="h-11 text-base tabular-nums md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('whatsapp')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="email">{{ __('Email') }}</x-ui.field-label>
                    <x-ui.input id="email" type="email" inputmode="email" wire:model="email" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('email')" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="address">{{ __('Address') }}</x-ui.field-label>
                <x-ui.textarea id="address" wire:model="address" rows="2" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('address')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="location_id">{{ __('Location') }}</x-ui.field-label>
                <x-location-select id="location_id" wire:model="location_id" :include="$customer?->location_id" />
                <x-ui.field-error :messages="$errors->get('location_id')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Finance')">
            @unless ($canEditFinance)
                <p class="text-sm text-muted-foreground">{{ __('Only finance users can change these.') }}</p>
            @endunless
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="payment_term_id">{{ __('Payment term') }}</x-ui.field-label>
                    <x-lookup-select table="payment_terms" :include="$customer?->payment_term_id" :placeholder="__('None')" id="payment_term_id" wire:model="payment_term_id" :disabled="! $canEditFinance" />
                    <x-ui.field-error :messages="$errors->get('payment_term_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="credit_limit">{{ __('Credit limit') }}</x-ui.field-label>
                    <x-ui.input id="credit_limit" wire:model="credit_limit" inputmode="decimal" class="h-11 text-end text-base tabular-nums md:h-9 md:text-sm" :disabled="! $canEditFinance" />
                    <x-ui.field-error :messages="$errors->get('credit_limit')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="tin">{{ __('TIN') }}</x-ui.field-label>
                    <x-ui.input id="tin" wire:model="tin" class="h-11 text-base md:h-9 md:text-sm" :disabled="! $canEditFinance" />
                    <x-ui.field-error :messages="$errors->get('tin')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="bin">{{ __('BIN') }}</x-ui.field-label>
                    <x-ui.input id="bin" wire:model="bin" class="h-11 text-base md:h-9 md:text-sm" :disabled="! $canEditFinance" />
                    <x-ui.field-error :messages="$errors->get('bin')" />
                </x-ui.field>
            </div>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Contacts')">
            <x-slot:action>
                <x-ui.button type="button" variant="outline" size="sm" wire:click="addContact">
                    <x-lucide-plus /> {{ __('Add contact') }}
                </x-ui.button>
            </x-slot:action>

            <h2 class="text-base font-semibold md:hidden">{{ __('Contacts') }}</h2>
            <x-ui.field-error :messages="$errors->get('contacts')" />

            @forelse ($contacts as $i => $contact)
                <x-ui.item variant="outline" class="flex-col items-stretch gap-3" wire:key="contact-{{ $i }}">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <x-ui.field>
                            <x-ui.field-label for="contact-name-{{ $i }}">{{ __('Name') }} *</x-ui.field-label>
                            <x-ui.input id="contact-name-{{ $i }}" wire:model="contacts.{{ $i }}.name" class="h-11 text-base md:h-9 md:text-sm" />
                            <x-ui.field-error :messages="$errors->get('contacts.'.$i.'.name')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="contact-designation-{{ $i }}">{{ __('Designation') }}</x-ui.field-label>
                            <x-ui.input id="contact-designation-{{ $i }}" wire:model="contacts.{{ $i }}.designation" class="h-11 text-base md:h-9 md:text-sm" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="contact-phone-{{ $i }}">{{ __('Phone') }}</x-ui.field-label>
                            <x-ui.input id="contact-phone-{{ $i }}" type="tel" inputmode="tel" wire:model="contacts.{{ $i }}.phone" class="h-11 text-base md:h-9 md:text-sm" />
                            <x-ui.field-error :messages="$errors->get('contacts.'.$i.'.phone')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="contact-email-{{ $i }}">{{ __('Email') }}</x-ui.field-label>
                            <x-ui.input id="contact-email-{{ $i }}" type="email" inputmode="email" wire:model="contacts.{{ $i }}.email" class="h-11 text-base md:h-9 md:text-sm" />
                            <x-ui.field-error :messages="$errors->get('contacts.'.$i.'.email')" />
                        </x-ui.field>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <x-ui.button type="button" size="sm" :variant="$contact['is_primary'] ? 'default' : 'outline'" class="h-11 md:h-9" wire:click="makePrimary({{ $i }})" :aria-pressed="$contact['is_primary'] ? 'true' : 'false'">
                            <x-lucide-star /> {{ $contact['is_primary'] ? __('Primary') : __('Make primary') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="ghost" size="icon" class="size-11 md:size-9" wire:click="removeContact({{ $i }})" :aria-label="__('Remove contact')">
                            <x-lucide-trash-2 />
                        </x-ui.button>
                    </div>
                </x-ui.item>
            @empty
                <p class="text-sm text-muted-foreground">{{ __('No contacts yet.') }}</p>
            @endforelse

            <x-ui.button type="button" variant="outline" class="h-11 md:hidden" wire:click="addContact">
                <x-lucide-plus /> {{ __('Add contact') }}
            </x-ui.button>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Notes')">
            <x-ui.field>
                <x-ui.field-label for="notes" class="md:sr-only">{{ __('Notes') }}</x-ui.field-label>
                <x-ui.textarea id="notes" wire:model="notes" rows="3" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('notes')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-slot:aside>
            <x-shell.form-section :title="__('Classification')">
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
                <x-ui.field>
                    <x-ui.field-label for="account_manager_user_id">{{ __('Account manager') }}</x-ui.field-label>
                    <x-ui.select native id="account_manager_user_id" wire:model="account_manager_user_id" class="h-11 text-base md:h-9 md:text-sm">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('account_manager_user_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="customer_status_id">{{ __('Status') }}</x-ui.field-label>
                    <x-lookup-select table="customer_statuses" :include="$customer?->customer_status_id" :placeholder="__('Active')" id="customer_status_id" wire:model="customer_status_id" />
                    <x-ui.field-error :messages="$errors->get('customer_status_id')" />
                </x-ui.field>
                <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                    <x-ui.switch id="is_also_vendor" wire:model="is_also_vendor" :checked="$is_also_vendor" />
                    <x-ui.field-label for="is_also_vendor">{{ __('Also a vendor') }}</x-ui.field-label>
                </x-ui.field>
            </x-shell.form-section>
        </x-slot:aside>
    </x-shell.form-screen>

    @if ($customer)
        <div class="mx-auto mt-6 w-full max-w-6xl pb-28 md:pb-0">
            <livewire:foundation.history :model="$customer" :key="'history-'.$customer->id" />
        </div>
    @endif
</div>
