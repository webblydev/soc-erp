@php($input = 'h-11 text-base md:h-9 md:text-sm')

<div>
    <x-shell.form-screen wire:submit="{{ $step === 1 ? 'next' : 'confirm' }}"
        :heading="__('Exit :name', ['name' => $employee->full_name])"
        :description="__('Record why and when the employee leaves, then check what needs handing over.')"
        :cancel-url="route('hrm.employees.show', $employee)"
        :submit-label="$step === 1 ? __('Next') : __('Confirm exit')">

        <x-slot:alerts>
            <x-ui.stepper :value="$step" class="hidden md:flex" wire:key="stepper-{{ $step }}">
                <x-ui.stepper-nav>
                    <x-ui.stepper-item :step="1">
                        <x-ui.stepper-indicator />
                        <x-ui.stepper-title>{{ __('Details') }}</x-ui.stepper-title>
                        <x-ui.stepper-separator />
                    </x-ui.stepper-item>
                    <x-ui.stepper-item :step="2">
                        <x-ui.stepper-indicator />
                        <x-ui.stepper-title>{{ __('Checks & confirm') }}</x-ui.stepper-title>
                    </x-ui.stepper-item>
                </x-ui.stepper-nav>
            </x-ui.stepper>
            <p class="text-sm text-muted-foreground md:hidden">{{ __('Step :step of 2', ['step' => $step]) }}</p>
        </x-slot:alerts>

        @if ($step === 1)
            <x-shell.form-section :title="__('Details')">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="employee_status_id">{{ __('Exit status') }} *</x-ui.field-label>
                        <x-ui.select native id="employee_status_id" wire:model="employee_status_id" class="{{ $input }}">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($exitStatuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field-error :messages="$errors->get('employee_status_id')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="exit_date">{{ __('Exit date') }} *</x-ui.field-label>
                        <x-ui.input id="exit_date" type="date" wire:model="exit_date" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('exit_date')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="exit_reason_id">{{ __('Reason') }} *</x-ui.field-label>
                        <x-lookup-select table="exit_reasons" :placeholder="__('Choose…')" id="exit_reason_id" wire:model="exit_reason_id" />
                        <x-ui.field-error :messages="$errors->get('exit_reason_id')" />
                    </x-ui.field>
                </div>
                <x-ui.field>
                    <x-ui.field-label for="note">{{ __('Note') }}</x-ui.field-label>
                    <x-ui.textarea id="note" wire:model="note" rows="3" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('note')" />
                </x-ui.field>
            </x-shell.form-section>
        @else
            <x-shell.form-section :title="__('Checks')">
                <x-slot:action>
                    <x-ui.button type="button" variant="outline" size="sm" class="h-11 md:h-8" wire:click="back"><x-lucide-arrow-left /> {{ __('Back') }}</x-ui.button>
                </x-slot:action>
                <x-ui.item-group class="gap-2">
                    @forelse ($checks as $i => $item)
                        <x-ui.item variant="outline" class="min-h-14" wire:key="check-{{ $i }}">
                            <x-ui.item-media variant="icon">
                                @if ($item->blocking)
                                    <x-lucide-circle-alert class="text-destructive" />
                                @else
                                    <x-lucide-info />
                                @endif
                            </x-ui.item-media>
                            <x-ui.item-content class="min-w-0">
                                <x-ui.item-title class="text-sm">
                                    @if ($item->url)
                                        <a href="{{ $item->url }}" wire:navigate class="hover:underline">{{ $item->label }}</a>
                                    @else
                                        {{ $item->label }}
                                    @endif
                                </x-ui.item-title>
                            </x-ui.item-content>
                            <x-ui.badge :tone="$item->blocking ? 'danger' : 'info'" class="shrink-0 text-sm">{{ $item->blocking ? __('Must be resolved') : __('Will happen') }}</x-ui.badge>
                        </x-ui.item>
                    @empty
                        <p class="text-sm text-muted-foreground">{{ __('Nothing else to resolve.') }}</p>
                    @endforelse
                </x-ui.item-group>
                @if ($blocked)
                    <x-ui.alert variant="destructive">
                        <x-lucide-circle-alert />
                        <x-ui.alert-title>{{ __('Resolve the blocking items before this employee can leave.') }}</x-ui.alert-title>
                    </x-ui.alert>
                @endif
            </x-shell.form-section>
        @endif
    </x-shell.form-screen>
</div>
