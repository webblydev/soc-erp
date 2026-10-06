@php
    $duplicateUrl = fn (array $match) => $match['type'] === 'lead'
        ? route('crm.leads.show', $match['number'])
        : (Route::has('crm.customers.show') ? route('crm.customers.show', $match['number']) : route('crm.customers.edit', $match['number']));
@endphp

<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$lead ? $lead->name : __('New lead')"
        :description="__('An enquiry, the services it is about and who follows it up.')"
        :cancel-url="$lead ? route('crm.leads.show', $lead) : route('crm.leads.index')"
        :submit-label="$lead ? __('Save changes') : __('Create lead')">

        <x-slot:alerts>
            @if ($duplicates !== [])
                <div x-data x-init="if (window.matchMedia('(max-width: 767px)').matches) $dispatch('open-sheet-lead-duplicates')">
                    {{-- Desktop: the panel inline; mobile: a banner that reopens the bottom sheet --}}
                    <x-ui.alert tone="warning" class="max-md:hidden">
                        <x-lucide-copy />
                        <x-ui.alert-title>{{ __('Possible duplicate') }}</x-ui.alert-title>
                        <x-ui.alert-description class="flex w-full flex-col gap-3">
                            @include('livewire.crm.leads.partials.duplicates')
                        </x-ui.alert-description>
                    </x-ui.alert>
                    <x-ui.alert tone="warning" class="md:hidden">
                        <x-lucide-copy />
                        <x-ui.alert-title>{{ trans_choice(':count possible duplicate|:count possible duplicates', count($duplicates)) }}</x-ui.alert-title>
                        <x-ui.alert-description>
                            <x-ui.button type="button" size="sm" variant="outline" class="h-11" x-on:click="$dispatch('open-sheet-lead-duplicates')">{{ __('Review') }}</x-ui.button>
                        </x-ui.alert-description>
                    </x-ui.alert>
                </div>
            @endif
            <x-ui.field-error :messages="$errors->get('duplicates')" />
        </x-slot:alerts>

        <x-shell.form-section :title="__('Contact')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
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
                    <x-ui.field-label for="phone">{{ __('Phone') }} *</x-ui.field-label>
                    <x-ui.input id="phone" type="tel" inputmode="tel" wire:model.live.debounce.500ms="phone" class="h-11 text-base tabular-nums md:h-9 md:text-sm" :aria-invalid="$errors->has('phone') ? 'true' : null" />
                    <x-ui.field-error :messages="$errors->get('phone')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="whatsapp">{{ __('WhatsApp') }}</x-ui.field-label>
                    <x-ui.input id="whatsapp" type="tel" inputmode="tel" wire:model.live.debounce.500ms="whatsapp" :disabled="$whatsappSameAsPhone" class="h-11 text-base tabular-nums md:h-9 md:text-sm" />
                    <x-ui.field orientation="horizontal" class="min-h-11 items-center md:min-h-0">
                        <x-ui.checkbox native id="whatsapp-same" wire:model.live="whatsappSameAsPhone" value="1" />
                        <x-ui.field-label for="whatsapp-same" class="font-normal">{{ __('Same as phone') }}</x-ui.field-label>
                    </x-ui.field>
                    <x-ui.field-error :messages="$errors->get('whatsapp')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="office_phone">{{ __('Office phone') }}</x-ui.field-label>
                    <x-ui.input id="office_phone" type="tel" inputmode="tel" wire:model.live.debounce.500ms="office_phone" class="h-11 text-base tabular-nums md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('office_phone')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="email">{{ __('Email') }}</x-ui.field-label>
                    <x-ui.input id="email" type="email" inputmode="email" wire:model.live.debounce.500ms="email" class="h-11 text-base md:h-9 md:text-sm" />
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
                <x-location-select id="location_id" wire:model="location_id" :include="$lead?->location_id" />
                <x-ui.field-error :messages="$errors->get('location_id')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Enquiry')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="lead_date">{{ __('Lead date') }} *</x-ui.field-label>
                    <x-ui.input id="lead_date" type="date" wire:model="lead_date" max="{{ today()->toDateString() }}" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('lead_date')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="lead_source_id">{{ __('Source') }} *</x-ui.field-label>
                    <x-lookup-select table="lead_sources" :include="$lead?->lead_source_id" :placeholder="__('Choose…')" id="lead_source_id" wire:model.live="lead_source_id" />
                    <x-ui.field-error :messages="$errors->get('lead_source_id')" />
                </x-ui.field>
            </div>

            @if ($requiresReferrer)
                <div class="flex flex-col gap-4 rounded-md border p-4">
                    <x-ui.field>
                        <x-ui.field-label for="referrer_type">{{ __('Referrer') }}</x-ui.field-label>
                        <x-ui.select native id="referrer_type" wire:model.live="referrer_type" class="h-11 text-base md:h-9 md:text-sm">
                            <option value="">{{ __('Choose…') }}</option>
                            <option value="customer">{{ __('Customer') }}</option>
                            <option value="employee">{{ __('Employee') }}</option>
                            <option value="agent">{{ __('Agent') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </x-ui.select>
                    </x-ui.field>

                    @if ($referrer_type === 'customer')
                        @if ($referrer_id)
                            <x-ui.item variant="outline">
                                <x-ui.item-content><x-ui.item-title>{{ $referrer_name }}</x-ui.item-title></x-ui.item-content>
                                <x-ui.button type="button" variant="ghost" size="icon" class="size-11 md:size-9" wire:click="clearReferrer" :aria-label="__('Clear')"><x-lucide-x /></x-ui.button>
                            </x-ui.item>
                        @else
                            <x-ui.input type="search" wire:model.live.debounce.300ms="referrerSearch" :placeholder="__('Search customers by number, name or phone')" class="h-11 text-base md:h-9 md:text-sm" />
                            <x-ui.item-group class="gap-2">
                                @foreach ($referrerCustomers as $customer)
                                    <x-ui.item variant="outline" class="min-h-11 cursor-pointer active:bg-accent" wire:key="ref-{{ $customer->id }}" wire:click="pickReferrerCustomer({{ $customer->id }})">
                                        <x-ui.item-content>
                                            <x-ui.item-title>{{ $customer->name }}</x-ui.item-title>
                                            <x-ui.item-description class="text-sm"><span class="font-mono">{{ $customer->customer_number }}</span> · {{ $customer->phone }}</x-ui.item-description>
                                        </x-ui.item-content>
                                    </x-ui.item>
                                @endforeach
                            </x-ui.item-group>
                        @endif
                    @elseif ($referrer_type === 'employee')
                        <x-ui.select native wire:model="referrer_id" class="h-11 text-base md:h-9 md:text-sm" :aria-label="__('Employee')">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                            @endforeach
                        </x-ui.select>
                    @elseif ($referrer_type !== null && $referrer_type !== '')
                        <x-ui.input wire:model="referrer_name" :placeholder="__('Referrer name')" class="h-11 text-base md:h-9 md:text-sm" :aria-label="__('Referrer name')" />
                    @endif
                    <x-ui.field-error :messages="[...$errors->get('referrer_name'), ...$errors->get('referrer_id')]" />
                </div>
            @endif

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

            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium">{{ __('Services') }} *</h3>
                    <x-ui.button type="button" variant="outline" size="sm" class="h-11 md:h-8" wire:click="addService"><x-lucide-plus /> {{ __('Add service') }}</x-ui.button>
                </div>
                @foreach ($services as $i => $line)
                    <x-ui.item variant="outline" class="flex-col items-stretch gap-3 md:flex-row md:items-start" wire:key="service-row-{{ $i }}">
                        <x-ui.field class="md:flex-1">
                            <x-ui.field-label for="service-{{ $i }}" class="md:sr-only">{{ __('Service') }}</x-ui.field-label>
                            <x-ui.select native id="service-{{ $i }}" wire:model.live="services.{{ $i }}.service_id" class="h-11 text-base md:h-9 md:text-sm">
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($serviceOptions as $service)
                                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.field-error :messages="$errors->get('services.'.$i.'.service_id')" />
                        </x-ui.field>
                        <x-ui.field class="md:w-40">
                            <x-ui.field-label for="service-value-{{ $i }}" class="md:sr-only">{{ __('Estimated value') }}</x-ui.field-label>
                            <x-ui.input id="service-value-{{ $i }}" wire:model.live.debounce.500ms="services.{{ $i }}.estimated_value" inputmode="decimal" :placeholder="__('Estimate')" class="h-11 text-end text-base tabular-nums md:h-9 md:text-sm" />
                            <x-ui.field-error :messages="$errors->get('services.'.$i.'.estimated_value')" />
                        </x-ui.field>
                        <x-ui.field class="md:flex-1">
                            <x-ui.field-label for="service-note-{{ $i }}" class="md:sr-only">{{ __('Note') }}</x-ui.field-label>
                            <x-ui.input id="service-note-{{ $i }}" wire:model="services.{{ $i }}.notes" :placeholder="__('Note')" class="h-11 text-base md:h-9 md:text-sm" />
                        </x-ui.field>
                        <x-ui.button type="button" variant="ghost" size="icon" class="size-11 self-end md:size-9 md:self-start" wire:click="removeService({{ $i }})" :disabled="count($services) <= 1" :aria-label="__('Remove service')">
                            <x-lucide-trash-2 />
                        </x-ui.button>
                    </x-ui.item>
                @endforeach
                <x-ui.field-error :messages="$errors->get('services')" />
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="lead_level_id">{{ __('Level') }}</x-ui.field-label>
                    <x-lookup-select table="lead_levels" :include="$lead?->lead_level_id" :placeholder="__('None')" id="lead_level_id" wire:model="lead_level_id" />
                    <x-ui.field-error :messages="$errors->get('lead_level_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="lead_priority_id">{{ __('Priority') }} *</x-ui.field-label>
                    <x-lookup-select table="lead_priorities" :include="$lead?->lead_priority_id" id="lead_priority_id" wire:model="lead_priority_id" />
                    <x-ui.field-error :messages="$errors->get('lead_priority_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="expected_value">{{ __('Expected value') }}</x-ui.field-label>
                    <x-ui.input id="expected_value" wire:model.live.debounce.500ms="expected_value" inputmode="decimal" class="h-11 text-end text-base tabular-nums md:h-9 md:text-sm" />
                    <x-ui.field-description>{{ __('Filled from the services until you type a value.') }}</x-ui.field-description>
                    <x-ui.field-error :messages="$errors->get('expected_value')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="expected_close_date">{{ __('Expected close date') }}</x-ui.field-label>
                    <x-ui.input id="expected_close_date" type="date" wire:model="expected_close_date" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('expected_close_date')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="site_location_text">{{ __('Site location') }}</x-ui.field-label>
                    <x-ui.input id="site_location_text" wire:model="site_location_text" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('site_location_text')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="land_area">{{ __('Land area') }}</x-ui.field-label>
                    <x-ui.input id="land_area" wire:model="land_area" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('land_area')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="floors_planned">{{ __('Floors planned') }}</x-ui.field-label>
                    <x-ui.input id="floors_planned" wire:model="floors_planned" inputmode="numeric" class="h-11 text-base tabular-nums md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('floors_planned')" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="notes">{{ __('Notes') }}</x-ui.field-label>
                <x-ui.textarea id="notes" wire:model="notes" rows="3" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('notes')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-slot:aside>
            <x-shell.form-section :title="__('Assignment')">
                @if ($lead)
                    <p class="text-sm">{{ $lead->assignee?->name ?? __('Unassigned') }}@if ($lead->team) <span class="text-muted-foreground">· {{ $lead->team->name }}</span>@endif</p>
                    <p class="text-sm text-muted-foreground">{{ __('Reassign from the lead page.') }}</p>
                @else
                    <x-ui.field>
                        <x-ui.field-label for="assigned_to">{{ __('Assigned to') }}</x-ui.field-label>
                        <x-ui.select native id="assigned_to" wire:model="assigned_to" class="h-11 text-base md:h-9 md:text-sm">
                            <option value="">{{ __('Unassigned') }}</option>
                            @foreach ($assignees as $assignee)
                                <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field-error :messages="$errors->get('assigned_to')" />
                    </x-ui.field>
                    @if ($teams->isNotEmpty())
                        <x-ui.field>
                            <x-ui.field-label for="sales_team_id">{{ __('Team') }}</x-ui.field-label>
                            <x-ui.select native id="sales_team_id" wire:model="sales_team_id" class="h-11 text-base md:h-9 md:text-sm">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.field-description>{{ __('Leave Assigned to empty to pick the next team member automatically.') }}</x-ui.field-description>
                            <x-ui.field-error :messages="$errors->get('sales_team_id')" />
                        </x-ui.field>
                    @endif
                @endif
            </x-shell.form-section>

            @unless ($lead)
                <x-shell.form-section :title="__('First follow-up')">
                    <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                        <x-ui.switch id="with-follow-up" wire:model.live="withFollowUp" :checked="$withFollowUp" />
                        <x-ui.field-label for="with-follow-up">{{ __('Schedule a first follow-up') }}</x-ui.field-label>
                    </x-ui.field>
                    @if ($withFollowUp)
                        <x-ui.field>
                            <x-ui.field-label for="follow_up_type_id">{{ __('Type') }}</x-ui.field-label>
                            <x-lookup-select table="activity_types" :placeholder="__('Choose…')" id="follow_up_type_id" wire:model="follow_up_type_id" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="follow_up_at">{{ __('When') }}</x-ui.field-label>
                            <x-ui.input type="datetime-local" id="follow_up_at" wire:model="follow_up_at" class="h-11 text-base md:h-9 md:text-sm" />
                        </x-ui.field>
                        <x-ui.field-error :messages="[...$errors->get('follow_up'), ...$errors->get('follow_up.*')]" />
                    @endif
                </x-shell.form-section>
            @endunless
        </x-slot:aside>
    </x-shell.form-screen>

    @if ($duplicates !== [])
        <x-shell.sheet id="lead-duplicates" :title="__('Possible duplicate')" :description="__('A lead or customer like this one already exists.')" class="md:hidden">
            <div class="flex flex-col gap-3 pb-4">
                @include('livewire.crm.leads.partials.duplicates')
            </div>
        </x-shell.sheet>
    @endif

    @if ($lead)
        <div class="mx-auto mt-6 w-full max-w-6xl pb-28 md:pb-0">
            <livewire:foundation.history :model="$lead" :key="'history-'.$lead->id" />
        </div>
    @endif
</div>
