@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
@endphp

<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$template ? $template->name : __('New template')"
        :description="__('Tasks to create for a kind of job, with dates counted from the project start.')"
        :cancel-url="route('projects.templates.index')"
        :submit-label="__('Save template')">

        <x-shell.form-section :title="__('Template')">
            <x-ui.field>
                <x-ui.field-label for="name">{{ __('Name') }} *</x-ui.field-label>
                <x-ui.input id="name" wire:model="name" class="{{ $input }}" />
                <x-ui.field-error :messages="$errors->get('name')" />
            </x-ui.field>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="service_id">{{ __('Default for service') }}</x-ui.field-label>
                    <x-ui.select native id="service_id" wire:model="service_id" class="{{ $input }}">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('service_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="project_type_id">{{ __('Default for project type') }}</x-ui.field-label>
                    <x-lookup-select table="project_types" :include="$template?->project_type_id" :placeholder="__('None')" id="project_type_id" wire:model="project_type_id" />
                    <x-ui.field-error :messages="$errors->get('project_type_id')" />
                </x-ui.field>
            </div>
            <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                <x-ui.switch id="is_active" wire:model="is_active" :checked="$is_active" />
                <x-ui.field-label for="is_active">{{ __('Active') }}</x-ui.field-label>
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Tasks')">
            <div class="flex flex-wrap items-end gap-3">
                <x-ui.field>
                    <x-ui.field-label for="preview-start">{{ __('Preview from') }}</x-ui.field-label>
                    <x-ui.input type="date" id="preview-start" wire:model.live="previewStart" class="{{ $input }}" />
                </x-ui.field>
                <p class="text-sm text-muted-foreground">{{ __('Dates below show when each task would start and be due.') }}</p>
            </div>

            @foreach ($items as $i => $item)
                <x-ui.item variant="outline" class="flex-col items-stretch gap-3" wire:key="template-item-{{ $i }}">
                    <div class="flex items-center gap-2">
                        <span class="w-6 text-sm tabular-nums text-muted-foreground">{{ $i + 1 }}.</span>
                        <x-ui.input wire:model="items.{{ $i }}.title" :placeholder="__('Task title')" class="{{ $input }} flex-1" :aria-label="__('Task title')" />
                        <x-ui.button type="button" variant="ghost" size="icon" class="size-11 md:size-9" wire:click="removeItem({{ $i }})" :aria-label="__('Remove task')"><x-lucide-trash-2 /></x-ui.button>
                    </div>
                    <x-ui.field-error :messages="[...$errors->get('items.'.$i.'.title'), ...$errors->get('items.'.$i.'.id')]" />
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <x-lookup-select table="task_types" wire:model="items.{{ $i }}.task_type_id" :aria-label="__('Type')" />
                        <x-lookup-select table="project_phases" :placeholder="__('Any phase')" wire:model="items.{{ $i }}.project_phase_id" :aria-label="__('Phase')" />
                        <x-lookup-select table="project_roles" :placeholder="__('PM gets it')" wire:model="items.{{ $i }}.project_role_id" :aria-label="__('Role')" />
                    </div>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <x-ui.field>
                            <x-ui.field-label for="offset-{{ $i }}" class="text-sm">{{ __('Start + days') }}</x-ui.field-label>
                            <x-ui.input id="offset-{{ $i }}" wire:model.live.debounce.500ms="items.{{ $i }}.offset_days_start" inputmode="numeric" class="{{ $input }} tabular-nums" />
                            <x-ui.field-error :messages="$errors->get('items.'.$i.'.offset_days_start')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="duration-{{ $i }}" class="text-sm">{{ __('Duration (days)') }}</x-ui.field-label>
                            <x-ui.input id="duration-{{ $i }}" wire:model.live.debounce.500ms="items.{{ $i }}.duration_days" inputmode="numeric" class="{{ $input }} tabular-nums" />
                            <x-ui.field-error :messages="$errors->get('items.'.$i.'.duration_days')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="hours-{{ $i }}" class="text-sm">{{ __('Hours') }}</x-ui.field-label>
                            <x-ui.input id="hours-{{ $i }}" wire:model="items.{{ $i }}.estimated_hours" inputmode="decimal" class="{{ $input }} tabular-nums" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="depends-{{ $i }}" class="text-sm">{{ __('After task') }}</x-ui.field-label>
                            <x-ui.select native id="depends-{{ $i }}" wire:model="items.{{ $i }}.depends_on" class="{{ $input }}">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($items as $j => $other)
                                    @if ($j < $i)
                                        <option value="{{ $j }}">{{ $j + 1 }}. {{ $other['title'] }}</option>
                                    @endif
                                @endforeach
                            </x-ui.select>
                            <x-ui.field-error :messages="$errors->get('items.'.$i.'.depends_on')" />
                        </x-ui.field>
                    </div>
                    <x-ui.textarea wire:model="items.{{ $i }}.checklist" rows="2" class="text-base md:text-sm" :placeholder="__('Checklist, one item per line')" :aria-label="__('Checklist')" />
                    @if ($preview[$i] ?? null)
                        <span class="text-sm tabular-nums text-muted-foreground">{{ $preview[$i]['start'] }} → {{ $preview[$i]['due'] }}</span>
                    @endif
                </x-ui.item>
            @endforeach
            <x-ui.button type="button" variant="outline" class="h-11 self-start md:h-9" wire:click="addItem"><x-lucide-plus /> {{ __('Add task') }}</x-ui.button>
            <x-ui.field-error :messages="$errors->get('items')" />
        </x-shell.form-section>
    </x-shell.form-screen>
</div>
