@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
@endphp

<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$approval ? $approval->type->name.' · '.$approval->project->project_number : __('New approval')"
        :description="__('A permit or approval to track with the authority.')"
        :cancel-url="$approval ? route('projects.approvals.show', $approval) : route('projects.approvals.index')"
        :submit-label="$approval ? __('Save changes') : __('Create approval')">

        <x-shell.form-section :title="__('Approval')">
            @if ($approval)
                <p class="text-sm">{{ $approval->project->project_number }} · {{ $approval->project->name }}</p>
            @else
                <x-ui.field>
                    <x-ui.field-label for="project_id">{{ __('Project') }} *</x-ui.field-label>
                    <x-ui.select native id="project_id" wire:model="project_id" class="{{ $input }}">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->project_number }} · {{ $project->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('project_id')" />
                </x-ui.field>
            @endif
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="approval_authority_id">{{ __('Authority') }} *</x-ui.field-label>
                    <x-lookup-select table="approval_authorities" :include="$approval?->approval_authority_id" :placeholder="__('Choose…')" id="approval_authority_id" wire:model="approval_authority_id" />
                    <x-ui.field-error :messages="$errors->get('approval_authority_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="approval_type_id">{{ __('Type') }} *</x-ui.field-label>
                    <x-lookup-select table="approval_types" :include="$approval?->approval_type_id" :placeholder="__('Choose…')" id="approval_type_id" wire:model="approval_type_id" />
                    <x-ui.field-error :messages="$errors->get('approval_type_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="reference_no">{{ __('Authority file / memo no.') }}</x-ui.field-label>
                    <x-ui.input id="reference_no" wire:model="reference_no" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('reference_no')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="responsible_employee_id">{{ __('Responsible') }}</x-ui.field-label>
                    <x-employee-select id="responsible_employee_id" wire:model="responsible_employee_id" :include="$approval?->responsible_employee_id" :placeholder="__('None')" />
                    <x-ui.field-error :messages="$errors->get('responsible_employee_id')" />
                </x-ui.field>
            </div>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Dates and fee')">
            <div class="grid grid-cols-2 gap-4 md:gap-6">
                @foreach (['prepared_on' => __('Prepared'), 'submitted_on' => __('Submitted'), 'expected_on' => __('Expected'), 'valid_until' => __('Valid until')] as $field => $label)
                    <x-ui.field>
                        <x-ui.field-label for="{{ $field }}">{{ $label }}</x-ui.field-label>
                        <x-ui.input type="date" id="{{ $field }}" wire:model="{{ $field }}" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get($field)" />
                    </x-ui.field>
                @endforeach
            </div>
            <p class="text-sm text-muted-foreground">{{ __('Leave Expected empty to use the submitted date plus the type’s typical days.') }}</p>
            <x-ui.field>
                <x-ui.field-label for="authority_fee">{{ __('Authority fee') }}</x-ui.field-label>
                <x-ui.input id="authority_fee" wire:model="authority_fee" inputmode="decimal" class="{{ $input }} tabular-nums" />
                <x-ui.field-error :messages="$errors->get('authority_fee')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="notes">{{ __('Notes') }}</x-ui.field-label>
                <x-ui.textarea id="notes" wire:model="notes" rows="3" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('notes')" />
            </x-ui.field>
        </x-shell.form-section>
    </x-shell.form-screen>
</div>
