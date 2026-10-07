@php($input = 'h-11 text-base md:h-9 md:text-sm')
<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$inspection ? $inspection->inspection_number : __('New inspection')"
        :description="__('Site visit: who was there, what was checked and what must be fixed.')"
        :cancel-url="$inspection ? route('site.inspections.show', $inspection) : route('site.inspections.index')"
        :submit-label="__('Save')">
        <x-slot:alerts>
            @foreach (['inspection', 'project'] as $key)
                @if ($errors->has($key))
                    <x-ui.alert variant="destructive"><x-lucide-circle-alert /><x-ui.alert-description>{{ $errors->first($key) }}</x-ui.alert-description></x-ui.alert>
                @endif
            @endforeach
        </x-slot:alerts>

        <x-shell.form-section :title="__('Visit')">
            <x-ui.field>
                <x-ui.field-label for="project_id">{{ __('Project') }} *</x-ui.field-label>
                @if ($inspection)
                    <p class="text-sm"><span class="font-mono">{{ $inspection->project->project_number }}</span> · {{ $inspection->project->name }}</p>
                @else
                    <x-ui.select native id="project_id" wire:model.live="project_id" class="{{ $input }}">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($projects as $option)
                            <option value="{{ $option->id }}">{{ $option->project_number }} — {{ $option->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('project_id')" />
                @endif
            </x-ui.field>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field>
                    <x-ui.field-label for="inspection_type_id">{{ __('Type') }} *</x-ui.field-label>
                    <x-lookup-select table="inspection_types" :include="$inspection?->inspection_type_id" id="inspection_type_id" wire:model="inspection_type_id" />
                    <x-ui.field-error :messages="$errors->get('inspection_type_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="inspection_date">{{ __('Date') }} *</x-ui.field-label>
                    <x-ui.input type="date" id="inspection_date" wire:model="inspection_date" class="{{ $input }}" max="{{ today()->toDateString() }}" />
                    <x-ui.field-error :messages="$errors->get('inspection_date')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="start_time">{{ __('Start') }}</x-ui.field-label>
                    <x-ui.input type="time" id="start_time" wire:model="start_time" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('start_time')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="end_time">{{ __('End') }}</x-ui.field-label>
                    <x-ui.input type="time" id="end_time" wire:model="end_time" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('end_time')" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="site_address">{{ __('Site address') }}</x-ui.field-label>
                <x-ui.input id="site_address" wire:model="site_address" class="{{ $input }}" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="project_engineer_id">{{ __('Project engineer') }}</x-ui.field-label>
                <x-employee-select id="project_engineer_id" wire:model="project_engineer_id" :include="$inspection?->project_engineer_id" :placeholder="__('None')" />
                @if ($inspection?->project_engineer_name && ! $inspection->project_engineer_id)
                    <x-ui.field-description>{{ __('v1: :name', ['name' => $inspection->project_engineer_name]) }}</x-ui.field-description>
                @endif
                <x-ui.field-error :messages="$errors->get('project_engineer_id')" />
            </x-ui.field>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="contractor_name">{{ __('Contractor') }}</x-ui.field-label>
                    <x-ui.input id="contractor_name" wire:model="contractor_name" class="{{ $input }}" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="permittee_name">{{ __('Permittee (land owner)') }}</x-ui.field-label>
                    <x-ui.input id="permittee_name" wire:model="permittee_name" class="{{ $input }}" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="field_office_phone">{{ __('Field office phone') }}</x-ui.field-label>
                    <x-ui.input type="tel" id="field_office_phone" wire:model="field_office_phone" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('field_office_phone')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="client_representative">{{ __('Client representative') }}</x-ui.field-label>
                    <x-ui.input id="client_representative" wire:model="client_representative" class="{{ $input }}" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="weather">{{ __('Weather') }}</x-ui.field-label>
                    <x-ui.input id="weather" wire:model="weather" class="{{ $input }}" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="workers_on_site">{{ __('Workers on site') }}</x-ui.field-label>
                    <x-ui.input id="workers_on_site" wire:model="workers_on_site" inputmode="numeric" class="{{ $input }} tabular-nums" />
                    <x-ui.field-error :messages="$errors->get('workers_on_site')" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="work_progress_summary">{{ __('Work progress') }}</x-ui.field-label>
                <x-ui.textarea id="work_progress_summary" wire:model="work_progress_summary" rows="3" class="text-base md:text-sm" />
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Findings')" :description="$isDraft ? __('High and critical findings need someone responsible and a due date.') : __('Add or follow up findings from the inspection page.')">
            @if ($isDraft)
                @foreach ($findings as $i => $finding)
                    <div class="flex flex-col gap-3 rounded-md border p-3" wire:key="finding-{{ $i }}">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">{{ __('Finding :n', ['n' => $i + 1]) }}</span>
                            <x-ui.button type="button" variant="ghost" size="icon" class="size-11 md:size-9" wire:click="removeFinding({{ $i }})" :aria-label="__('Remove finding')"><x-lucide-trash-2 /></x-ui.button>
                        </div>
                        @include('livewire.estimation.inspections.finding-fields', ['prefix' => "findings.{$i}", 'errorPrefix' => "findings.{$i}", 'idPrefix' => "finding-{$i}"])
                    </div>
                @endforeach
                <x-ui.button type="button" variant="outline" class="h-11 self-start md:h-9" wire:click="addFinding"><x-lucide-plus /> {{ __('Add finding') }}</x-ui.button>
            @endif
        </x-shell.form-section>

        <x-slot:aside>
            @if ($isDraft && $canSubmit)
                <x-shell.form-section :title="__('Submit')">
                    <p class="text-sm text-muted-foreground">{{ __('Submitting tells the responsible people about their findings.') }}</p>
                    <x-ui.button type="button" variant="secondary" class="h-11 md:h-9" wire:click="saveAndSubmit"><x-lucide-send /> {{ __('Save and submit') }}</x-ui.button>
                </x-shell.form-section>
            @endif
        </x-slot:aside>
    </x-shell.form-screen>
</div>
