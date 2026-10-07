@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
@endphp

<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$task ? $task->task_number.' · '.$task->title : __('New task')"
        :description="__('What needs doing, who does it and by when.')"
        :cancel-url="$task ? route('projects.tasks.show', $task) : route('projects.tasks.index')"
        :submit-label="$task ? __('Save changes') : __('Create task')">

        <x-shell.form-section :title="__('Task')">
            <x-ui.field>
                <x-ui.field-label for="title">{{ __('Title') }} *</x-ui.field-label>
                <x-ui.input id="title" wire:model="title" class="{{ $input }}" :aria-invalid="$errors->has('title') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('title')" />
            </x-ui.field>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="project_id">{{ __('Project') }}</x-ui.field-label>
                    <x-ui.select native id="project_id" wire:model.live="project_id" class="{{ $input }}">
                        <option value="">{{ __('General task (no project)') }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->project_number }} · {{ $project->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('project_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="parent_id">{{ __('Main task') }}</x-ui.field-label>
                    <x-ui.select native id="parent_id" wire:model="parent_id" class="{{ $input }}" :disabled="$parents->isEmpty()">
                        <option value="">{{ __('None (this is a main task)') }}</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->task_number }} · {{ $parent->title }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('parent_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="task_type_id">{{ __('Type') }} *</x-ui.field-label>
                    <x-lookup-select table="task_types" :include="$task?->task_type_id" :placeholder="__('Choose…')" id="task_type_id" wire:model="task_type_id" />
                    <x-ui.field-error :messages="$errors->get('task_type_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="project_phase_id">{{ __('Phase') }}</x-ui.field-label>
                    <x-lookup-select table="project_phases" :include="$task?->project_phase_id" :placeholder="__('None')" id="project_phase_id" wire:model="project_phase_id" />
                    <x-ui.field-error :messages="$errors->get('project_phase_id')" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="description">{{ __('Description') }}</x-ui.field-label>
                <x-ui.textarea id="description" wire:model="description" rows="4" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('description')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="file_number">{{ __('File no.') }}</x-ui.field-label>
                <x-ui.input id="file_number" wire:model="file_number" class="{{ $input }}" />
                <x-ui.field-error :messages="$errors->get('file_number')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Checklist')">
            @foreach ($checklist as $i => $item)
                <div class="flex gap-2" wire:key="checklist-{{ $i }}">
                    <x-ui.input wire:model="checklist.{{ $i }}.title" class="{{ $input }} flex-1" :placeholder="__('Checklist item')" :aria-label="__('Checklist item')" />
                    <x-ui.button type="button" variant="ghost" size="icon" class="size-11 md:size-9" wire:click="removeChecklistItem({{ $i }})" :aria-label="__('Remove item')"><x-lucide-x /></x-ui.button>
                </div>
            @endforeach
            <x-ui.button type="button" variant="outline" class="h-11 self-start md:h-9" wire:click="addChecklistItem"><x-lucide-plus /> {{ __('Add item') }}</x-ui.button>
            <x-ui.field-error :messages="$errors->get('checklist')" />
        </x-shell.form-section>

        <x-slot:aside>
            <x-shell.form-section :title="__('People')">
                <x-ui.field>
                    <x-ui.field-label for="assignee_employee_id">{{ __('Assignee') }}</x-ui.field-label>
                    <x-employee-select id="assignee_employee_id" wire:model="assignee_employee_id" :include="$task?->assignee_employee_id" :placeholder="__('Unassigned')" />
                    @unless ($canAssign)
                        <x-ui.field-description>{{ __('You can assign tasks to yourself.') }}</x-ui.field-description>
                    @endunless
                    <x-ui.field-error :messages="$errors->get('assignee_employee_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="support_officer_id">{{ __('Support officer') }}</x-ui.field-label>
                    <x-employee-select id="support_officer_id" wire:model="support_officer_id" :include="$task?->support_officer_id" :placeholder="__('None')" />
                    <x-ui.field-error :messages="$errors->get('support_officer_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="reviewer_employee_id">{{ __('Reviewer') }}</x-ui.field-label>
                    <x-employee-select id="reviewer_employee_id" wire:model="reviewer_employee_id" :include="$task?->reviewer_employee_id" :placeholder="__('None (the PM reviews)')" />
                    <x-ui.field-error :messages="$errors->get('reviewer_employee_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="watchers">{{ __('Watchers') }}</x-ui.field-label>
                    <x-ui.select native multiple id="watchers" wire:model="watcher_ids" class="min-h-24 text-base md:text-sm">
                        @foreach ($users as $watcher)
                            <option value="{{ $watcher->id }}">{{ $watcher->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="[...$errors->get('watcher_ids'), ...$errors->get('watcher_ids.*')]" />
                </x-ui.field>
            </x-shell.form-section>

            <x-shell.form-section :title="__('Schedule')">
                <x-ui.field>
                    <x-ui.field-label for="task_priority_id">{{ __('Priority') }} *</x-ui.field-label>
                    <x-lookup-select table="task_priorities" :include="$task?->task_priority_id" id="task_priority_id" wire:model="task_priority_id" />
                    <x-ui.field-error :messages="$errors->get('task_priority_id')" />
                </x-ui.field>
                <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                    <x-ui.checkbox native id="is_important" wire:model="is_important" value="1" />
                    <x-ui.field-label for="is_important" class="font-normal">{{ __('Important') }}</x-ui.field-label>
                </x-ui.field>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.field>
                        <x-ui.field-label for="start_date">{{ __('Start') }}</x-ui.field-label>
                        <x-ui.input type="date" id="start_date" wire:model="start_date" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('start_date')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="due_date">{{ __('Due') }}</x-ui.field-label>
                        <x-ui.input type="date" id="due_date" wire:model="due_date" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('due_date')" />
                    </x-ui.field>
                </div>
                <x-ui.field>
                    <x-ui.field-label for="estimated_hours">{{ __('Estimated hours') }}</x-ui.field-label>
                    <x-ui.input id="estimated_hours" wire:model="estimated_hours" inputmode="decimal" class="{{ $input }} tabular-nums" :placeholder="__('From the type')" />
                    <x-ui.field-error :messages="$errors->get('estimated_hours')" />
                </x-ui.field>
            </x-shell.form-section>
        </x-slot:aside>
    </x-shell.form-screen>
</div>
