<div>
    <x-shell.sheet id="change-status" :title="match ($mode) { 'lost' => __('Mark as lost'), 'reopen' => __('Reopen lead'), default => __('Change status') }">
        <form wire:submit="save" id="change-status-form" class="flex flex-col gap-4">
            @if ($mode === 'status')
                <x-ui.field>
                    <x-ui.field-label for="cs-status">{{ __('New status') }}</x-ui.field-label>
                    <x-ui.select native id="cs-status" wire:model.live="statusId" class="h-11 text-base md:h-9 md:text-sm">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                        @if ($canConvert)
                            <option value="won">{{ __('Won → convert…') }}</option>
                        @endif
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('lead_status_id')" />
                </x-ui.field>

                @if ($this->needsFollowUp)
                    <x-ui.alert tone="info">
                        <x-lucide-calendar-clock />
                        <x-ui.alert-title>{{ __('This status needs a follow-up') }}</x-ui.alert-title>
                    </x-ui.alert>
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.field>
                            <x-ui.field-label for="cs-follow-type">{{ __('Follow-up type') }}</x-ui.field-label>
                            <x-lookup-select table="activity_types" id="cs-follow-type" wire:model="followUpTypeId" :placeholder="__('Choose…')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="cs-follow-at">{{ __('When') }}</x-ui.field-label>
                            <x-ui.input type="datetime-local" id="cs-follow-at" wire:model="followUpAt" class="h-11 text-base md:h-9 md:text-sm" />
                        </x-ui.field>
                    </div>
                    <x-ui.field-error :messages="[...$errors->get('follow_up'), ...$errors->get('follow_up.*')]" />
                @endif
            @elseif ($mode === 'lost')
                <x-ui.field>
                    <x-ui.field-label for="cs-lost-reason">{{ __('Lost reason') }}</x-ui.field-label>
                    <x-lookup-select table="lost_reasons" id="cs-lost-reason" wire:model="lostReasonId" :placeholder="__('Choose…')" />
                    <x-ui.field-error :messages="$errors->get('lost_reason_id')" />
                </x-ui.field>
            @else
                <x-ui.field>
                    <x-ui.field-label for="cs-reason">{{ __('Why reopen?') }}</x-ui.field-label>
                    <x-ui.input id="cs-reason" wire:model="reason" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('reason')" />
                </x-ui.field>
            @endif

            @if ($mode !== 'reopen')
                <x-ui.field>
                    <x-ui.field-label for="cs-note">{{ __('Note') }}</x-ui.field-label>
                    <x-ui.textarea id="cs-note" wire:model="note" rows="2" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('note')" />
                </x-ui.field>
            @endif

            <x-ui.field-error :messages="$errors->get('lead')" />
        </form>

        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-change-status')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="change-status-form" :variant="$mode === 'lost' ? 'destructive' : 'default'">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
