@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
@endphp

<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$project ? $project->project_number.' · '.$project->name : __('New project')"
        :description="__('The job file: customer, site, people, dates and the services sold.')"
        :cancel-url="$project ? route('projects.projects.show', $project) : route('projects.projects.index')"
        :submit-label="$project ? __('Save changes') : __('Create project')">

        <x-shell.form-section :title="__('General')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="business_line_id">{{ __('Business line') }} *</x-ui.field-label>
                    @if ($project)
                        <p class="text-sm">{{ $project->businessLine->name }} <span class="font-mono text-muted-foreground">({{ $project->businessLine->project_prefix }})</span></p>
                        <x-ui.field-description>{{ __('The business line sets the project number and cannot change.') }}</x-ui.field-description>
                    @else
                        <x-ui.select native id="business_line_id" wire:model="business_line_id" class="{{ $input }}">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($businessLines as $line)
                                <option value="{{ $line->id }}">{{ $line->name }} ({{ $line->project_prefix }})</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field-error :messages="$errors->get('business_line_id')" />
                    @endif
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="project_type_id">{{ __('Project type') }} *</x-ui.field-label>
                    <x-lookup-select table="project_types" :include="$project?->project_type_id" :placeholder="__('Choose…')" id="project_type_id" wire:model.live="project_type_id" />
                    <x-ui.field-error :messages="$errors->get('project_type_id')" />
                </x-ui.field>
            </div>

            <x-ui.field>
                <x-ui.field-label for="name">{{ __('Project name') }} *</x-ui.field-label>
                <x-ui.input id="name" wire:model="name" class="{{ $input }}" :placeholder="__('e.g. Md. Mokbul Hossain, S. Bonosree')" :aria-invalid="$errors->has('name') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('name')" />
            </x-ui.field>

            @unless ($isInternal)
                <div class="flex flex-col gap-3">
                    <x-ui.field-label for="customer-search">{{ __('Customer') }} *</x-ui.field-label>
                    @if ($customer_id)
                        <x-ui.item variant="outline">
                            <x-ui.item-content><x-ui.item-title>{{ $customerName }}</x-ui.item-title></x-ui.item-content>
                            <x-ui.button type="button" variant="ghost" size="icon" class="size-11 md:size-9" wire:click="clearCustomer" :aria-label="__('Change customer')"><x-lucide-x /></x-ui.button>
                        </x-ui.item>
                    @else
                        <div class="flex gap-2">
                            <x-ui.input type="search" id="customer-search" wire:model.live.debounce.300ms="customerSearch" :placeholder="__('Search customers by number, name or phone')" class="{{ $input }} flex-1" />
                            @if ($canCreateCustomer)
                                <x-ui.button type="button" variant="outline" class="h-11 md:h-9" :href="route('crm.customers.create')" wire:navigate><x-lucide-plus /> {{ __('New') }}</x-ui.button>
                            @endif
                        </div>
                        <x-ui.item-group class="gap-2">
                            @foreach ($customerOptions as $option)
                                <x-ui.item variant="outline" class="min-h-11 cursor-pointer active:bg-accent" wire:key="customer-{{ $option->id }}" wire:click="pickCustomer({{ $option->id }})">
                                    <x-ui.item-content>
                                        <x-ui.item-title>{{ $option->name }}</x-ui.item-title>
                                        <x-ui.item-description class="text-sm"><span class="font-mono">{{ $option->customer_number }}</span> · {{ $option->phone }}</x-ui.item-description>
                                    </x-ui.item-content>
                                </x-ui.item>
                            @endforeach
                        </x-ui.item-group>
                    @endif
                    <x-ui.field-error :messages="$errors->get('customer_id')" />
                </div>
            @else
                <x-ui.alert>
                    <x-lucide-building />
                    <x-ui.alert-title>{{ __('Internal project') }}</x-ui.alert-title>
                    <x-ui.alert-description>{{ __('Internal work has no customer and is never invoiced.') }}</x-ui.alert-description>
                </x-ui.alert>
                <x-ui.field-error :messages="$errors->get('customer_id')" />
            @endunless

            @unless ($project)
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="project_status_id">{{ __('Status') }}</x-ui.field-label>
                        <x-ui.select native id="project_status_id" wire:model="project_status_id" class="{{ $input }}">
                            @foreach ($openStatuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field-error :messages="$errors->get('project_status_id')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="project_phase_id">{{ __('Phase') }}</x-ui.field-label>
                        <x-lookup-select table="project_phases" :placeholder="__('None')" id="project_phase_id" wire:model="project_phase_id" />
                        <x-ui.field-error :messages="$errors->get('project_phase_id')" />
                    </x-ui.field>
                </div>
            @endunless

            <x-ui.field>
                <x-ui.field-label for="branch_id">{{ __('Branch') }}</x-ui.field-label>
                <x-lookup-select table="branches" :include="$project?->branch_id" :placeholder="__('None')" id="branch_id" wire:model="branch_id" />
                <x-ui.field-error :messages="$errors->get('branch_id')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="description">{{ __('Description') }}</x-ui.field-label>
                <x-ui.textarea id="description" wire:model="description" rows="3" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('description')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Site')">
            <x-ui.field>
                <x-ui.field-label for="site_address">{{ __('Site address') }}</x-ui.field-label>
                <x-ui.textarea id="site_address" wire:model="site_address" rows="2" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('site_address')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="location_id">{{ __('Location') }}</x-ui.field-label>
                <x-location-select id="location_id" wire:model="location_id" :include="$project?->location_id" />
                <x-ui.field-error :messages="$errors->get('location_id')" />
            </x-ui.field>
            <div class="grid grid-cols-2 gap-4 md:gap-6">
                <x-ui.field>
                    <x-ui.field-label for="latitude">{{ __('Latitude') }}</x-ui.field-label>
                    <x-ui.input id="latitude" wire:model="latitude" inputmode="decimal" class="{{ $input }} tabular-nums" />
                    <x-ui.field-error :messages="$errors->get('latitude')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="longitude">{{ __('Longitude') }}</x-ui.field-label>
                    <x-ui.input id="longitude" wire:model="longitude" inputmode="decimal" class="{{ $input }} tabular-nums" />
                    <x-ui.field-error :messages="$errors->get('longitude')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="plot_no">{{ __('Plot no.') }}</x-ui.field-label>
                    <x-ui.input id="plot_no" wire:model="plot_no" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('plot_no')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="land_area">{{ __('Land area') }}</x-ui.field-label>
                    <div class="flex gap-2">
                        <x-ui.input id="land_area" wire:model="land_area" inputmode="decimal" class="{{ $input }} min-w-0 flex-1 tabular-nums" />
                        <x-ui.select native wire:model="land_area_unit_id" class="{{ $input }} w-28" :aria-label="__('Land area unit')">
                            <option value="">{{ __('Unit') }}</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->symbol }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <x-ui.field-error :messages="[...$errors->get('land_area'), ...$errors->get('land_area_unit_id')]" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="floors">{{ __('Floors') }}</x-ui.field-label>
                    <x-ui.input id="floors" wire:model="floors" inputmode="numeric" class="{{ $input }} tabular-nums" />
                    <x-ui.field-error :messages="$errors->get('floors')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="basements">{{ __('Basements') }}</x-ui.field-label>
                    <x-ui.input id="basements" wire:model="basements" inputmode="numeric" class="{{ $input }} tabular-nums" />
                    <x-ui.field-error :messages="$errors->get('basements')" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="built_up_area_sft">{{ __('Built-up area (sft)') }}</x-ui.field-label>
                <x-ui.input id="built_up_area_sft" wire:model="built_up_area_sft" inputmode="decimal" class="{{ $input }} tabular-nums" />
                <x-ui.field-error :messages="$errors->get('built_up_area_sft')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Services')" :description="__('What was sold. The contract value is their total.')">
            @if ($servicesEditable)
                <div class="flex flex-col gap-3">
                    @foreach ($services as $i => $line)
                        <x-ui.item variant="outline" class="flex-col items-stretch gap-3" wire:key="service-line-{{ $i }}">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto]">
                                <x-ui.field>
                                    <x-ui.field-label for="service-{{ $i }}" class="md:sr-only">{{ __('Service') }}</x-ui.field-label>
                                    <x-ui.select native id="service-{{ $i }}" wire:model.live="services.{{ $i }}.service_id" class="{{ $input }}">
                                        <option value="">{{ __('Choose service…') }}</option>
                                        @foreach ($serviceOptions as $service)
                                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                                        @endforeach
                                    </x-ui.select>
                                    <x-ui.field-error :messages="[...$errors->get('services.'.$i.'.service_id'), ...$errors->get('services.'.$i.'.id')]" />
                                </x-ui.field>
                                <x-ui.button type="button" variant="ghost" size="icon" class="size-11 self-end md:size-9" wire:click="removeService({{ $i }})" :aria-label="__('Remove service')"><x-lucide-trash-2 /></x-ui.button>
                            </div>
                            <x-ui.input wire:model="services.{{ $i }}.description" :placeholder="__('Description')" class="{{ $input }}" :aria-label="__('Description')" />
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                                <x-ui.field>
                                    <x-ui.field-label for="qty-{{ $i }}" class="text-sm">{{ __('Qty') }}</x-ui.field-label>
                                    <x-ui.input id="qty-{{ $i }}" wire:model.live.debounce.500ms="services.{{ $i }}.quantity" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                                    <x-ui.field-error :messages="$errors->get('services.'.$i.'.quantity')" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.field-label for="unit-{{ $i }}" class="text-sm">{{ __('Unit') }}</x-ui.field-label>
                                    <x-ui.select native id="unit-{{ $i }}" wire:model="services.{{ $i }}.unit_id" class="{{ $input }}">
                                        <option value="">—</option>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->symbol }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.field-label for="rate-{{ $i }}" class="text-sm">{{ __('Rate') }}</x-ui.field-label>
                                    <x-ui.input id="rate-{{ $i }}" wire:model.live.debounce.500ms="services.{{ $i }}.rate" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                                    <x-ui.field-error :messages="$errors->get('services.'.$i.'.rate')" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.field-label for="discount-{{ $i }}" class="text-sm">{{ __('Discount') }}</x-ui.field-label>
                                    <x-ui.input id="discount-{{ $i }}" wire:model.live.debounce.500ms="services.{{ $i }}.discount_amount" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                                    <x-ui.field-error :messages="$errors->get('services.'.$i.'.discount_amount')" />
                                </x-ui.field>
                                <div class="col-span-2 flex flex-col gap-1 md:col-span-1">
                                    <span class="text-sm font-medium">{{ __('Amount') }}</span>
                                    <span @class(['flex h-11 items-center justify-end text-base tabular-nums md:h-9 md:text-sm', 'text-muted-foreground line-through' => $line['cancelled'] ?? false])>
                                        {{ $totals['lines'][$i] !== null ? \App\Support\Money::format($totals['lines'][$i], false) : '—' }}
                                    </span>
                                </div>
                            </div>
                            @isset($line['status'])
                                <span class="text-sm text-muted-foreground">{{ $line['status'] }}</span>
                            @endisset
                        </x-ui.item>
                    @endforeach
                    <x-ui.button type="button" variant="outline" class="h-11 self-start md:h-9" wire:click="addService"><x-lucide-plus /> {{ __('Add service') }}</x-ui.button>
                    <x-ui.field-error :messages="$errors->get('services')" />
                </div>
            @else
                <x-ui.alert>
                    <x-lucide-lock />
                    <x-ui.alert-title>{{ __('The contract is signed') }}</x-ui.alert-title>
                    <x-ui.alert-description>{{ __('Change services through an amendment on the Contract tab.') }}</x-ui.alert-description>
                </x-ui.alert>
            @endif
            <div class="flex items-center justify-between border-t pt-4">
                <span class="text-sm font-medium">{{ __('Contract value') }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ \App\Support\Money::format($servicesEditable ? $totals['total'] : $project->contract_value) }}</span>
            </div>
        </x-shell.form-section>

        <x-slot:aside>
            <x-shell.form-section :title="__('People')">
                <x-ui.field>
                    <x-ui.field-label for="project_manager_id">{{ __('Project manager') }} *</x-ui.field-label>
                    <x-employee-select id="project_manager_id" wire:model="project_manager_id" :include="$project?->project_manager_id" :placeholder="__('Choose…')" />
                    <x-ui.field-error :messages="$errors->get('project_manager_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="supervisor_id">{{ __('Supervisor') }}</x-ui.field-label>
                    <x-employee-select id="supervisor_id" wire:model="supervisor_id" :include="$project?->supervisor_id" :placeholder="__('None')" />
                    <x-ui.field-error :messages="$errors->get('supervisor_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="support_officer_id">{{ __('Support officer') }}</x-ui.field-label>
                    <x-employee-select id="support_officer_id" wire:model="support_officer_id" :include="$project?->support_officer_id" :placeholder="__('None')" />
                    <x-ui.field-error :messages="$errors->get('support_officer_id')" />
                </x-ui.field>
                <p class="text-sm text-muted-foreground">{{ __('They are added to the project team.') }}</p>
            </x-shell.form-section>

            <x-shell.form-section :title="__('Dates')">
                <x-ui.field>
                    <x-ui.field-label for="start_date">{{ __('Start') }}</x-ui.field-label>
                    <x-ui.input type="date" id="start_date" wire:model="start_date" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('start_date')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="expected_end_date">{{ __('Expected end') }}</x-ui.field-label>
                    <x-ui.input type="date" id="expected_end_date" wire:model="expected_end_date" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('expected_end_date')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="handover_date">{{ __('Handover') }}</x-ui.field-label>
                    <x-ui.input type="date" id="handover_date" wire:model="handover_date" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('handover_date')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="retention_pct">{{ __('Retention %') }}</x-ui.field-label>
                    <x-ui.input id="retention_pct" wire:model="retention_pct" inputmode="decimal" class="{{ $input }} tabular-nums" />
                    <x-ui.field-error :messages="$errors->get('retention_pct')" />
                </x-ui.field>
            </x-shell.form-section>

            @unless ($project)
                <x-shell.form-section :title="__('Task template')">
                    <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                        <x-ui.switch id="apply-template" wire:model.live="applyTemplate" :checked="$applyTemplate" />
                        <x-ui.field-label for="apply-template">{{ __('Create tasks from a template') }}</x-ui.field-label>
                    </x-ui.field>
                    @if ($applyTemplate)
                        <x-ui.field>
                            <x-ui.field-label for="task_template_id">{{ __('Template') }}</x-ui.field-label>
                            <x-ui.select native id="task_template_id" wire:model.live="task_template_id" class="{{ $input }}">
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.field-description>{{ __('Dates count from the start date; tasks go to the team member with each role, else the PM.') }}</x-ui.field-description>
                            <x-ui.field-error :messages="$errors->get('task_template_id')" />
                        </x-ui.field>
                    @endif
                </x-shell.form-section>
            @endunless

            <x-shell.form-section :title="__('Notes')">
                <x-ui.textarea wire:model="notes" rows="3" class="text-base md:text-sm" :aria-label="__('Notes')" />
                <x-ui.field-error :messages="$errors->get('notes')" />
            </x-shell.form-section>
        </x-slot:aside>
    </x-shell.form-screen>
</div>
