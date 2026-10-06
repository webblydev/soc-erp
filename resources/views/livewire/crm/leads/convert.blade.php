@php
    $customerRow = function ($customer) use ($customerId) {
        return ['selected' => (int) $customerId === $customer->id, 'blocked' => $customer->isBlocked()];
    };
@endphp

<div>
    <x-shell.form-screen wire:submit="{{ $step === 1 ? 'next' : 'convert' }}"
        :heading="__('Convert :name', ['name' => $lead->name])"
        :description="__('Link the lead to a customer, then confirm. The lead becomes Won.')"
        :cancel-url="route('crm.leads.show', $lead)"
        :submit-label="$step === 1 ? __('Next') : __('Convert')">

        <x-slot:alerts>
            <x-ui.stepper :value="$step" class="hidden md:flex" wire:key="stepper-{{ $step }}">
                <x-ui.stepper-nav>
                    <x-ui.stepper-item :step="1">
                        <x-ui.stepper-indicator />
                        <x-ui.stepper-title>{{ __('Customer') }}</x-ui.stepper-title>
                        <x-ui.stepper-separator />
                    </x-ui.stepper-item>
                    <x-ui.stepper-item :step="2">
                        <x-ui.stepper-indicator />
                        <x-ui.stepper-title>{{ __('Review & confirm') }}</x-ui.stepper-title>
                    </x-ui.stepper-item>
                </x-ui.stepper-nav>
            </x-ui.stepper>
            <p class="text-sm text-muted-foreground md:hidden">{{ __('Step :step of 2', ['step' => $step]) }}</p>
            <x-ui.field-error :messages="[...$errors->get('lead'), ...$errors->get('customer_id')]" />
        </x-slot:alerts>

        @if ($step === 1)
            <x-shell.form-section :title="__('Customer')">
                <x-ui.segmented-control name="convert-choice" wire:model.live="choice" :value="$choice" size="lg" class="w-full"
                    :options="[['value' => 'link', 'label' => __('Link existing')], ['value' => 'new', 'label' => __('Create new')]]" />

                @if ($choice === 'link')
                    <x-ui.field-error :messages="$errors->get('customerId')" />
                    @if ($suggestions->isNotEmpty())
                        <h3 class="text-sm font-medium">{{ __('Suggested matches') }}</h3>
                    @endif
                    <x-ui.item-group class="gap-2">
                        @foreach ($suggestions->concat($results)->unique('id') as $customer)
                            @php($state = $customerRow($customer))
                            <x-ui.item variant="outline" wire:key="pick-{{ $customer->id }}"
                                @class(['min-h-11', 'cursor-pointer active:bg-accent' => ! $state['blocked'], 'opacity-60' => $state['blocked'], 'border-primary ring-2 ring-primary/30' => $state['selected']])
                                :wire:click="$state['blocked'] ? null : 'pick('.$customer->id.')'">
                                <x-ui.item-content class="min-w-0">
                                    <x-ui.item-title class="text-base md:text-sm">{{ $customer->name }}</x-ui.item-title>
                                    <x-ui.item-description class="text-sm"><span class="font-mono">{{ $customer->customer_number }}</span> · <span class="tabular-nums">{{ $customer->phone }}</span></x-ui.item-description>
                                </x-ui.item-content>
                                <x-ui.badge :tone="$customer->status->color ?? 'neutral'" class="text-sm">{{ $customer->status->name }}</x-ui.badge>
                                @if ($state['selected'])<x-lucide-circle-check class="size-5 text-primary" />@endif
                            </x-ui.item>
                        @endforeach
                    </x-ui.item-group>
                    <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Search customers by number, name or phone')" class="h-11 text-base md:h-9 md:text-sm" />
                @else
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <x-ui.field>
                            <x-ui.field-label for="c-type">{{ __('Customer type') }} *</x-ui.field-label>
                            <x-lookup-select table="customer_types" :placeholder="__('Choose…')" id="c-type" wire:model="customer.customer_type_id" />
                            <x-ui.field-error :messages="$errors->get('customer.customer_type_id')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="c-name">{{ __('Name') }} *</x-ui.field-label>
                            <x-ui.input id="c-name" wire:model="customer.name" class="h-11 text-base md:h-9 md:text-sm" />
                            <x-ui.field-error :messages="$errors->get('customer.name')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="c-company">{{ __('Company') }}</x-ui.field-label>
                            <x-ui.input id="c-company" wire:model="customer.company_name" class="h-11 text-base md:h-9 md:text-sm" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="c-phone">{{ __('Phone') }} *</x-ui.field-label>
                            <x-ui.input id="c-phone" type="tel" inputmode="tel" wire:model="customer.phone" class="h-11 text-base tabular-nums md:h-9 md:text-sm" />
                            <x-ui.field-error :messages="$errors->get('customer.phone')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="c-whatsapp">{{ __('WhatsApp') }}</x-ui.field-label>
                            <x-ui.input id="c-whatsapp" type="tel" inputmode="tel" wire:model="customer.whatsapp" class="h-11 text-base tabular-nums md:h-9 md:text-sm" />
                            <x-ui.field-error :messages="$errors->get('customer.whatsapp')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="c-alt">{{ __('Alternate phone') }}</x-ui.field-label>
                            <x-ui.input id="c-alt" type="tel" inputmode="tel" wire:model="customer.alternate_phone" class="h-11 text-base tabular-nums md:h-9 md:text-sm" />
                            <x-ui.field-error :messages="$errors->get('customer.alternate_phone')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="c-email">{{ __('Email') }}</x-ui.field-label>
                            <x-ui.input id="c-email" type="email" inputmode="email" wire:model="customer.email" class="h-11 text-base md:h-9 md:text-sm" />
                            <x-ui.field-error :messages="$errors->get('customer.email')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="c-nid">{{ __('NID / registration no.') }}</x-ui.field-label>
                            <x-ui.input id="c-nid" wire:model="customer.nid_or_reg_no" class="h-11 text-base md:h-9 md:text-sm" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="c-business-line">{{ __('Business line') }}</x-ui.field-label>
                            <x-ui.select native id="c-business-line" wire:model="customer.business_line_id" class="h-11 text-base md:h-9 md:text-sm">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($businessLines as $line)
                                    <option value="{{ $line->id }}">{{ $line->name }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.field-error :messages="$errors->get('customer.business_line_id')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="c-manager">{{ __('Account manager') }}</x-ui.field-label>
                            <x-ui.select native id="c-manager" wire:model="customer.account_manager_user_id" class="h-11 text-base md:h-9 md:text-sm">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        @if ($canEditFinance)
                            <x-ui.field>
                                <x-ui.field-label for="c-tin">{{ __('TIN') }}</x-ui.field-label>
                                <x-ui.input id="c-tin" wire:model="customer.tin" class="h-11 text-base md:h-9 md:text-sm" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.field-label for="c-term">{{ __('Payment term') }}</x-ui.field-label>
                                <x-lookup-select table="payment_terms" :placeholder="__('None')" id="c-term" wire:model="customer.payment_term_id" />
                            </x-ui.field>
                        @endif
                    </div>
                    <x-ui.field>
                        <x-ui.field-label for="c-address">{{ __('Address') }}</x-ui.field-label>
                        <x-ui.textarea id="c-address" wire:model="customer.address" rows="2" class="text-base md:text-sm" />
                    </x-ui.field>
                    @if ($errors->has('duplicate_reason') || $duplicate_reason !== '')
                        <x-ui.alert tone="warning">
                            <x-lucide-copy />
                            <x-ui.alert-title>{{ __('Possible duplicate') }}</x-ui.alert-title>
                            <x-ui.alert-description class="flex w-full flex-col gap-2">
                                @foreach ($errors->get('duplicate_reason') as $message)<p>{{ $message }}</p>@endforeach
                                <x-ui.input wire:model="duplicate_reason" :placeholder="__('Reason')" class="h-11 text-base md:h-9 md:text-sm" :aria-label="__('Reason')" />
                            </x-ui.alert-description>
                        </x-ui.alert>
                    @endif
                @endif
            </x-shell.form-section>
        @else
            <x-shell.form-section :title="__('Review & confirm')">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-ui.card class="gap-2 p-4">
                        <span class="text-sm text-muted-foreground">{{ __('Lead') }}</span>
                        <p class="font-medium">{{ $lead->name }} <span class="font-mono text-sm text-muted-foreground">{{ $lead->lead_number }}</span></p>
                        <p class="text-sm">{{ $lead->services->pluck('service.name')->implode(', ') ?: '—' }}</p>
                        <p class="text-sm tabular-nums">{{ $lead->expected_value !== null ? \App\Support\Money::format($lead->expected_value) : '—' }}</p>
                    </x-ui.card>
                    <x-ui.card class="gap-2 p-4">
                        <span class="text-sm text-muted-foreground">{{ $choice === 'link' ? __('Existing customer') : __('New customer') }}</span>
                        @if ($choice === 'link' && $linked)
                            <p class="font-medium">{{ $linked->name }} <span class="font-mono text-sm text-muted-foreground">{{ $linked->customer_number }}</span></p>
                            <p class="text-sm tabular-nums">{{ $linked->phone }}</p>
                        @else
                            <p class="font-medium">{{ $customer['name'] }}</p>
                            <p class="text-sm tabular-nums">{{ $customer['phone'] }}</p>
                            @if (filled($customer['email'] ?? null))<p class="text-sm">{{ $customer['email'] }}</p>@endif
                        @endif
                    </x-ui.card>
                </div>
                <p class="text-sm text-muted-foreground">{{ __('The lead becomes Won. Projects will be added here once the Projects module is live.') }}</p>
                <x-ui.button type="button" variant="outline" class="h-11 self-start md:h-9" wire:click="back"><x-lucide-arrow-left /> {{ __('Back') }}</x-ui.button>
            </x-shell.form-section>
        @endif
    </x-shell.form-screen>
</div>
