@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
@endphp

<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$employee ? $employee->full_name : __('New employee')"
        :description="__('Personal, contact and job details of a member of staff.')"
        :cancel-url="$employee ? route('hrm.employees.show', $employee) : route('hrm.employees.index')"
        :submit-label="$employee ? __('Save changes') : __('Create employee')">

        @if ($canViewFull)
            <x-shell.form-section :title="__('Personal')">
                <div class="flex items-center gap-4">
                    @if ($photo)
                        <x-ui.avatar class="size-16"><x-ui.avatar-image :src="$photo->temporaryUrl()" :alt="__('New photo')" /></x-ui.avatar>
                    @elseif ($employee)
                        <x-employee-avatar :employee="$employee" class="size-16" />
                    @endif
                    <x-ui.field class="flex-1">
                        <x-ui.field-label for="photo">{{ __('Photo') }}</x-ui.field-label>
                        <x-ui.input id="photo" type="file" accept="image/png,image/jpeg" wire:model="photo" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('photo')" />
                    </x-ui.field>
                </div>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="first_name">{{ __('First name') }} *</x-ui.field-label>
                        <x-ui.input id="first_name" wire:model="first_name" autocomplete="given-name" class="{{ $input }}" :aria-invalid="$errors->has('first_name') ? 'true' : null" />
                        <x-ui.field-error :messages="$errors->get('first_name')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="last_name">{{ __('Last name') }}</x-ui.field-label>
                        <x-ui.input id="last_name" wire:model="last_name" autocomplete="family-name" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('last_name')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="father_name">{{ __('Father’s name') }}</x-ui.field-label>
                        <x-ui.input id="father_name" wire:model="father_name" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('father_name')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="mother_name">{{ __('Mother’s name') }}</x-ui.field-label>
                        <x-ui.input id="mother_name" wire:model="mother_name" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('mother_name')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="gender_id">{{ __('Gender') }}</x-ui.field-label>
                        <x-lookup-select table="genders" :include="$employee?->gender_id" :placeholder="__('Not set')" id="gender_id" wire:model="gender_id" />
                        <x-ui.field-error :messages="$errors->get('gender_id')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="date_of_birth">{{ __('Date of birth') }}</x-ui.field-label>
                        <x-ui.input id="date_of_birth" type="date" wire:model="date_of_birth" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('date_of_birth')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="marital_status_id">{{ __('Marital status') }}</x-ui.field-label>
                        <x-lookup-select table="marital_statuses" :include="$employee?->marital_status_id" :placeholder="__('Not set')" id="marital_status_id" wire:model="marital_status_id" />
                        <x-ui.field-error :messages="$errors->get('marital_status_id')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="blood_group_id">{{ __('Blood group') }}</x-ui.field-label>
                        <x-lookup-select table="blood_groups" :include="$employee?->blood_group_id" :placeholder="__('Not set')" id="blood_group_id" wire:model="blood_group_id" />
                        <x-ui.field-error :messages="$errors->get('blood_group_id')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="nid_number">{{ __('NID') }}</x-ui.field-label>
                        <x-ui.input id="nid_number" inputmode="numeric" wire:model="nid_number" class="{{ $input }} tabular-nums" />
                        <x-ui.field-error :messages="$errors->get('nid_number')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="tin">{{ __('TIN') }}</x-ui.field-label>
                        <x-ui.input id="tin" inputmode="numeric" wire:model="tin" class="{{ $input }} tabular-nums" />
                        <x-ui.field-error :messages="$errors->get('tin')" />
                    </x-ui.field>
                </div>
            </x-shell.form-section>
        @else
            <x-shell.form-section :title="__('Name')">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="first_name">{{ __('First name') }} *</x-ui.field-label>
                        <x-ui.input id="first_name" wire:model="first_name" class="{{ $input }}" :aria-invalid="$errors->has('first_name') ? 'true' : null" />
                        <x-ui.field-error :messages="$errors->get('first_name')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="last_name">{{ __('Last name') }}</x-ui.field-label>
                        <x-ui.input id="last_name" wire:model="last_name" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('last_name')" />
                    </x-ui.field>
                </div>
            </x-shell.form-section>
        @endif

        <x-shell.form-section :title="__('Contact & emergency')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="phone">{{ __('Phone') }} *</x-ui.field-label>
                    <x-ui.input id="phone" type="tel" inputmode="tel" wire:model="phone" class="{{ $input }} tabular-nums" :aria-invalid="$errors->has('phone') ? 'true' : null" />
                    <x-ui.field-error :messages="$errors->get('phone')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="official_email">{{ __('Official email') }}</x-ui.field-label>
                    <x-ui.input id="official_email" type="email" inputmode="email" wire:model="official_email" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('official_email')" />
                </x-ui.field>
                @if ($canViewFull)
                    <x-ui.field>
                        <x-ui.field-label for="personal_email">{{ __('Personal email') }}</x-ui.field-label>
                        <x-ui.input id="personal_email" type="email" inputmode="email" wire:model="personal_email" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('personal_email')" />
                    </x-ui.field>
                @endif
            </div>
            @if ($canViewFull)
                <x-ui.field>
                    <x-ui.field-label for="present_address">{{ __('Present address') }}</x-ui.field-label>
                    <x-ui.textarea id="present_address" wire:model="present_address" rows="2" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('present_address')" />
                </x-ui.field>
                <x-ui.field>
                    <div class="flex items-center justify-between gap-2">
                        <x-ui.field-label for="permanent_address">{{ __('Permanent address') }}</x-ui.field-label>
                        <x-ui.button type="button" variant="ghost" size="sm" class="h-11 md:h-8" wire:click="sameAsPresent">{{ __('Same as present') }}</x-ui.button>
                    </div>
                    <x-ui.textarea id="permanent_address" wire:model="permanent_address" rows="2" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('permanent_address')" />
                </x-ui.field>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <x-ui.field>
                        <x-ui.field-label for="emergency_contact_name">{{ __('Emergency contact') }}</x-ui.field-label>
                        <x-ui.input id="emergency_contact_name" wire:model="emergency_contact_name" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('emergency_contact_name')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="emergency_contact_relation">{{ __('Relation') }}</x-ui.field-label>
                        <x-ui.input id="emergency_contact_relation" wire:model="emergency_contact_relation" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('emergency_contact_relation')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="emergency_contact_phone">{{ __('Emergency phone') }}</x-ui.field-label>
                        <x-ui.input id="emergency_contact_phone" type="tel" inputmode="tel" wire:model="emergency_contact_phone" class="{{ $input }} tabular-nums" />
                        <x-ui.field-error :messages="$errors->get('emergency_contact_phone')" />
                    </x-ui.field>
                </div>
            @endif
        </x-shell.form-section>

        @if ($canViewSalary)
            <x-shell.form-section :title="__('Bank & salary')">
                @unless ($canEditSalary)
                    <p class="text-sm text-muted-foreground">{{ __('Only HR can change these.') }}</p>
                @endunless
                @if ($employee && $canEditSalary)
                    <p class="text-sm text-muted-foreground">{{ __('A salary change is recorded in the employment history.') }}</p>
                @endif
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="gross_salary">{{ __('Gross salary') }}</x-ui.field-label>
                        <x-ui.input id="gross_salary" inputmode="decimal" wire:model="gross_salary" class="{{ $input }} tabular-nums" :disabled="! $canEditSalary" />
                        <x-ui.field-error :messages="$errors->get('gross_salary')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="mobile_wallet_no">{{ __('Mobile wallet') }}</x-ui.field-label>
                        <x-ui.input id="mobile_wallet_no" type="tel" inputmode="tel" wire:model="mobile_wallet_no" class="{{ $input }} tabular-nums" :disabled="! $canEditSalary" />
                        <x-ui.field-error :messages="$errors->get('mobile_wallet_no')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="bank_name">{{ __('Bank') }}</x-ui.field-label>
                        <x-ui.input id="bank_name" wire:model="bank_name" class="{{ $input }}" :disabled="! $canEditSalary" />
                        <x-ui.field-error :messages="$errors->get('bank_name')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="bank_account_no">{{ __('Account no.') }}</x-ui.field-label>
                        <x-ui.input id="bank_account_no" inputmode="numeric" wire:model="bank_account_no" class="{{ $input }} tabular-nums" :disabled="! $canEditSalary" />
                        <x-ui.field-error :messages="$errors->get('bank_account_no')" />
                    </x-ui.field>
                </div>
            </x-shell.form-section>
        @endif

        @if ($canViewFull)
            <x-shell.form-section :title="__('Education')">
                <x-slot:action>
                    <x-ui.button type="button" variant="outline" size="sm" class="max-md:hidden" wire:click="addEducation"><x-lucide-plus /> {{ __('Add') }}</x-ui.button>
                </x-slot:action>
                @forelse ($education as $i => $row)
                    <x-ui.item variant="outline" class="flex-col items-stretch gap-3" wire:key="education-{{ $i }}">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <x-ui.field>
                                <x-ui.field-label for="education-institution-{{ $i }}">{{ __('Institution') }} *</x-ui.field-label>
                                <x-ui.input id="education-institution-{{ $i }}" wire:model="education.{{ $i }}.institution" class="{{ $input }}" />
                                <x-ui.field-error :messages="$errors->get('education.'.$i.'.institution')" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.field-label for="education-degree-{{ $i }}">{{ __('Degree') }} *</x-ui.field-label>
                                <x-ui.input id="education-degree-{{ $i }}" wire:model="education.{{ $i }}.degree" class="{{ $input }}" />
                                <x-ui.field-error :messages="$errors->get('education.'.$i.'.degree')" />
                            </x-ui.field>
                            <div class="grid grid-cols-3 gap-3 md:col-span-2">
                                <x-ui.field>
                                    <x-ui.field-label for="education-from-{{ $i }}">{{ __('From') }}</x-ui.field-label>
                                    <x-ui.input id="education-from-{{ $i }}" type="number" inputmode="numeric" wire:model="education.{{ $i }}.from_year" class="{{ $input }}" />
                                    <x-ui.field-error :messages="$errors->get('education.'.$i.'.from_year')" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.field-label for="education-to-{{ $i }}">{{ __('To') }}</x-ui.field-label>
                                    <x-ui.input id="education-to-{{ $i }}" type="number" inputmode="numeric" wire:model="education.{{ $i }}.to_year" class="{{ $input }}" />
                                    <x-ui.field-error :messages="$errors->get('education.'.$i.'.to_year')" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.field-label for="education-result-{{ $i }}">{{ __('Result') }}</x-ui.field-label>
                                    <x-ui.input id="education-result-{{ $i }}" wire:model="education.{{ $i }}.result" class="{{ $input }}" />
                                </x-ui.field>
                            </div>
                        </div>
                        <x-ui.button type="button" variant="ghost" size="icon" class="size-11 self-end md:size-9" wire:click="removeEducation({{ $i }})" :aria-label="__('Remove education')"><x-lucide-trash-2 /></x-ui.button>
                    </x-ui.item>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('No education added.') }}</p>
                @endforelse
                <x-ui.button type="button" variant="outline" class="h-11 md:hidden" wire:click="addEducation"><x-lucide-plus /> {{ __('Add education') }}</x-ui.button>
            </x-shell.form-section>

            <x-shell.form-section :title="__('Experience')">
                <x-slot:action>
                    <x-ui.button type="button" variant="outline" size="sm" class="max-md:hidden" wire:click="addExperience"><x-lucide-plus /> {{ __('Add') }}</x-ui.button>
                </x-slot:action>
                @forelse ($experience as $i => $row)
                    <x-ui.item variant="outline" class="flex-col items-stretch gap-3" wire:key="experience-{{ $i }}">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <x-ui.field>
                                <x-ui.field-label for="experience-company-{{ $i }}">{{ __('Company') }} *</x-ui.field-label>
                                <x-ui.input id="experience-company-{{ $i }}" wire:model="experience.{{ $i }}.company" class="{{ $input }}" />
                                <x-ui.field-error :messages="$errors->get('experience.'.$i.'.company')" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.field-label for="experience-position-{{ $i }}">{{ __('Position') }} *</x-ui.field-label>
                                <x-ui.input id="experience-position-{{ $i }}" wire:model="experience.{{ $i }}.position" class="{{ $input }}" />
                                <x-ui.field-error :messages="$errors->get('experience.'.$i.'.position')" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.field-label for="experience-from-{{ $i }}">{{ __('From') }}</x-ui.field-label>
                                <x-ui.input id="experience-from-{{ $i }}" type="date" wire:model="experience.{{ $i }}.from_date" class="{{ $input }}" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.field-label for="experience-to-{{ $i }}">{{ __('To') }}</x-ui.field-label>
                                <x-ui.input id="experience-to-{{ $i }}" type="date" wire:model="experience.{{ $i }}.to_date" class="{{ $input }}" />
                            </x-ui.field>
                        </div>
                        <x-ui.button type="button" variant="ghost" size="icon" class="size-11 self-end md:size-9" wire:click="removeExperience({{ $i }})" :aria-label="__('Remove experience')"><x-lucide-trash-2 /></x-ui.button>
                    </x-ui.item>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('No experience added.') }}</p>
                @endforelse
                <x-ui.button type="button" variant="outline" class="h-11 md:hidden" wire:click="addExperience"><x-lucide-plus /> {{ __('Add experience') }}</x-ui.button>
            </x-shell.form-section>

            <x-shell.form-section :title="__('Notes')">
                <x-ui.field>
                    <x-ui.field-label for="reference">{{ __('Reference') }}</x-ui.field-label>
                    <x-ui.input id="reference" wire:model="reference" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('reference')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="notes">{{ __('Notes') }}</x-ui.field-label>
                    <x-ui.textarea id="notes" wire:model="notes" rows="3" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('notes')" />
                </x-ui.field>
            </x-shell.form-section>
        @endif

        <x-slot:aside>
            <x-shell.form-section :title="__('Job')">
                @if ($employee)
                    <x-ui.field>
                        <x-ui.field-label>{{ __('Code') }}</x-ui.field-label>
                        <p class="font-mono text-sm">{{ $employee->employee_code }}</p>
                    </x-ui.field>
                @else
                    <x-ui.field>
                        <x-ui.field-label for="employee_code">{{ __('Code') }}</x-ui.field-label>
                        <x-ui.input id="employee_code" wire:model="employee_code" autocapitalize="characters" class="{{ $input }} font-mono" />
                        <x-ui.field-description>{{ __('Leave blank to number it automatically.') }}</x-ui.field-description>
                        <x-ui.field-error :messages="$errors->get('employee_code')" />
                    </x-ui.field>
                @endif
                <x-ui.field>
                    <x-ui.field-label for="department_id">{{ __('Department') }} *</x-ui.field-label>
                    <x-lookup-select table="departments" :include="$employee?->department_id" :placeholder="__('Choose…')" id="department_id" wire:model="department_id" />
                    <x-ui.field-error :messages="$errors->get('department_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="designation_id">{{ __('Designation') }} *</x-ui.field-label>
                    <x-lookup-select table="designations" :include="$employee?->designation_id" :placeholder="__('Choose…')" id="designation_id" wire:model="designation_id" />
                    <x-ui.field-error :messages="$errors->get('designation_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="employee_type_id">{{ __('Employee type') }} *</x-ui.field-label>
                    <x-lookup-select table="employee_types" :include="$employee?->employee_type_id" :placeholder="__('Choose…')" id="employee_type_id" wire:model="employee_type_id" />
                    <x-ui.field-error :messages="$errors->get('employee_type_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="employee_status_id">{{ __('Status') }} *</x-ui.field-label>
                    <x-ui.select native id="employee_status_id" wire:model="employee_status_id" class="{{ $input }}">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('employee_status_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="branch_id">{{ __('Branch') }}</x-ui.field-label>
                    <x-lookup-select table="branches" :include="$employee?->branch_id" :placeholder="__('None')" id="branch_id" wire:model="branch_id" />
                    <x-ui.field-error :messages="$errors->get('branch_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="manager_id">{{ __('Manager') }}</x-ui.field-label>
                    <x-employee-select :include="$employee?->manager_id" :placeholder="__('None')" id="manager_id" wire:model="manager_id" />
                    <x-ui.field-error :messages="$errors->get('manager_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="joining_date">{{ __('Joining date') }} *</x-ui.field-label>
                    <x-ui.input id="joining_date" type="date" wire:model="joining_date" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('joining_date')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="confirmation_date">{{ __('Confirmation date') }}</x-ui.field-label>
                    <x-ui.input id="confirmation_date" type="date" wire:model="confirmation_date" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('confirmation_date')" />
                </x-ui.field>
            </x-shell.form-section>
        </x-slot:aside>
    </x-shell.form-screen>

    <x-shell.sheet id="employment-event" :title="__('Record the change')" :description="__('Choose the event and date for this change.')">
        <form id="employment-event-form" wire:submit="saveWithEvent" class="flex flex-col gap-4">
            <p class="text-sm text-muted-foreground">{{ __('Department, designation and salary changes are kept in the employment history.') }}</p>
            <x-ui.field>
                <x-ui.field-label for="event-type">{{ __('Event') }} *</x-ui.field-label>
                <x-ui.select native id="event-type" wire:model="event.employment_event_type_id" class="{{ $input }}">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($eventTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.field-error :messages="$errors->get('event.employment_event_type_id')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="event-date">{{ __('Effective date') }} *</x-ui.field-label>
                <x-ui.input id="event-date" type="date" wire:model="event.effective_date" class="{{ $input }}" />
                <x-ui.field-error :messages="$errors->get('event.effective_date')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="event-note">{{ __('Note') }}</x-ui.field-label>
                <x-ui.textarea id="event-note" wire:model="event.note" rows="2" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('event.note')" />
            </x-ui.field>
        </form>
        <x-slot:footer>
            <x-ui.button variant="outline" class="h-11 md:h-9" x-on:click="$dispatch('close-sheet-employment-event')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="employment-event-form" class="h-11 md:h-9">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
