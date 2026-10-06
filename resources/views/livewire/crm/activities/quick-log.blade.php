<div>
    <x-shell.sheet id="quick-log" :title="match ($mode) { 'schedule' => __('Schedule follow-up'), 'complete' => __('Mark done'), 'reschedule' => __('Reschedule'), default => __('Log activity') }">
        <form wire:submit="save" id="quick-log-form" class="flex flex-col gap-4">
            @if (in_array($mode, ['log', 'schedule'], true))
                <x-ui.segmented-control name="quick-log-done" wire:model.live="done" :value="$done ? '1' : '0'" size="lg" class="w-full"
                    :options="[['value' => '1', 'label' => __('Done now')], ['value' => '0', 'label' => __('Scheduled')]]" />

                <x-ui.field>
                    <x-ui.field-label for="ql-type">{{ __('Type') }} *</x-ui.field-label>
                    <x-lookup-select table="activity_types" id="ql-type" wire:model.live="activity_type_id" :placeholder="__('Choose…')" />
                    <x-ui.field-error :messages="$errors->get('activity_type_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="ql-title">{{ __('Title') }}</x-ui.field-label>
                    <x-ui.input id="ql-title" wire:model="title" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('title')" />
                </x-ui.field>
            @endif

            @if ($mode !== 'reschedule')
                <x-ui.field>
                    <x-ui.field-label for="ql-description">{{ __('Description') }}</x-ui.field-label>
                    <x-ui.textarea id="ql-description" wire:model="description" rows="2" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('description')" />
                </x-ui.field>
            @endif

            @if (($mode === 'log' || $mode === 'schedule') && ! $done || $mode === 'reschedule')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="ql-when">{{ __('When') }} *</x-ui.field-label>
                        <x-ui.input type="datetime-local" id="ql-when" wire:model="scheduled_at" class="h-11 text-base md:h-9 md:text-sm" />
                        <x-ui.field-error :messages="$errors->get('scheduled_at')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="ql-reminder">{{ __('Reminder') }}</x-ui.field-label>
                        <x-ui.select native id="ql-reminder" wire:model="reminder_minutes" class="h-11 text-base md:h-9 md:text-sm">
                            <option value="">{{ __('None') }}</option>
                            @foreach ($reminderOptions as $minutes => $label)
                                <option value="{{ $minutes }}">{{ __(':time before', ['time' => $label]) }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field-error :messages="$errors->get('reminder_minutes')" />
                    </x-ui.field>
                </div>
                @if ($mode !== 'reschedule')
                    <x-ui.field>
                        <x-ui.field-label for="ql-owner">{{ __('Owner') }}</x-ui.field-label>
                        <x-ui.select native id="ql-owner" wire:model="owner_user_id" class="h-11 text-base md:h-9 md:text-sm">
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field-error :messages="$errors->get('owner_user_id')" />
                    </x-ui.field>
                @endif
            @endif

            @if ($mode === 'complete' || ($mode !== 'reschedule' && $done))
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="ql-duration">{{ __('Duration (minutes)') }}{{ $type?->requires_duration ? ' *' : '' }}</x-ui.field-label>
                        <x-ui.input id="ql-duration" wire:model="duration_minutes" inputmode="numeric" class="h-11 text-base tabular-nums md:h-9 md:text-sm" />
                        <x-ui.field-error :messages="$errors->get('duration_minutes')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="ql-outcome">{{ __('Outcome') }}</x-ui.field-label>
                        <x-lookup-select table="activity_outcomes" id="ql-outcome" wire:model="outcome_id" :placeholder="__('None')" />
                        <x-ui.field-error :messages="$errors->get('outcome_id')" />
                    </x-ui.field>
                </div>
                @if ($mode !== 'complete' && in_array($type?->code, ['MEETING', 'SITE_VISIT', 'OFFICE_VISIT'], true))
                    <x-ui.field>
                        <x-ui.field-label for="ql-location">{{ __('Location') }}</x-ui.field-label>
                        <x-ui.input id="ql-location" wire:model="location_text" class="h-11 text-base md:h-9 md:text-sm" />
                    </x-ui.field>
                @endif

                <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                    <x-ui.switch id="ql-next" wire:model.live="scheduleNext" :checked="$scheduleNext" />
                    <x-ui.field-label for="ql-next">{{ __('Schedule next follow-up') }}</x-ui.field-label>
                </x-ui.field>
                @if ($scheduleNext)
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-ui.field>
                            <x-ui.field-label for="ql-next-type">{{ __('Type') }}</x-ui.field-label>
                            <x-lookup-select table="activity_types" id="ql-next-type" wire:model="next_type_id" :placeholder="__('Choose…')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="ql-next-at">{{ __('When') }}</x-ui.field-label>
                            <x-ui.input type="datetime-local" id="ql-next-at" wire:model="next_at" class="h-11 text-base md:h-9 md:text-sm" />
                        </x-ui.field>
                    </div>
                    <x-ui.field-error :messages="[...$errors->get('next_follow_up'), ...$errors->get('next_follow_up.*')]" />
                @endif
            @endif

            <x-ui.field-error :messages="$errors->get('activity')" />
        </form>

        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-quick-log')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="quick-log-form">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
