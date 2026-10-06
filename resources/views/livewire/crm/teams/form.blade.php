<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$team ? $team->name : __('New sales team')"
        :description="__('A manager and the salespeople whose leads they look after.')"
        :cancel-url="route('crm.teams.index')"
        :submit-label="$team ? __('Save changes') : __('Create team')">

        <x-shell.form-section :title="__('Team')">
            <div class="grid gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="name">{{ __('Name') }} *</x-ui.field-label>
                    <x-ui.input id="name" wire:model="name" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('name') ? 'true' : null" />
                    <x-ui.field-error :messages="$errors->get('name')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="manager_user_id">{{ __('Manager') }} *</x-ui.field-label>
                    <x-ui.select native id="manager_user_id" wire:model="manager_user_id" class="h-11 text-base md:h-9 md:text-sm">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('manager_user_id')" />
                </x-ui.field>
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
                <x-ui.field>
                    <x-ui.field-label for="monthly_target_amount">{{ __('Monthly target') }}</x-ui.field-label>
                    <x-ui.input id="monthly_target_amount" wire:model="monthly_target_amount" inputmode="decimal" class="h-11 text-end text-base tabular-nums md:h-9 md:text-sm" :aria-invalid="$errors->has('monthly_target_amount') ? 'true' : null" />
                    <x-ui.field-error :messages="$errors->get('monthly_target_amount')" />
                </x-ui.field>
            </div>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Members')">
            <x-slot:action>
                <x-ui.button type="button" variant="outline" size="sm" wire:click="addMember">
                    <x-lucide-plus /> {{ __('Add member') }}
                </x-ui.button>
            </x-slot:action>

            <h2 class="text-base font-semibold md:hidden">{{ __('Members') }}</h2>

            @forelse ($members as $i => $member)
                <x-ui.item variant="outline" class="items-start gap-3 max-md:flex-col md:border-0 md:p-0" wire:key="member-{{ $i }}">
                    <div class="grid w-full gap-3 md:grid-cols-[1fr_12rem_auto] md:items-start">
                        <x-ui.field>
                            <x-ui.field-label for="member-user-{{ $i }}" class="md:sr-only">{{ __('Person') }}</x-ui.field-label>
                            <x-ui.select native id="member-user-{{ $i }}" wire:model="members.{{ $i }}.user_id" class="h-11 text-base md:h-9 md:text-sm">
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.field-error :messages="$errors->get('members.'.$i.'.user_id')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="member-joined-{{ $i }}" class="md:sr-only">{{ __('Joined') }}</x-ui.field-label>
                            <x-ui.input type="date" id="member-joined-{{ $i }}" wire:model="members.{{ $i }}.joined_on" class="h-11 text-base md:h-9 md:text-sm" />
                            <x-ui.field-error :messages="$errors->get('members.'.$i.'.joined_on')" />
                        </x-ui.field>
                        <x-ui.button type="button" variant="ghost" size="icon" class="size-11 self-end md:size-9 md:self-start" wire:click="removeMember({{ $i }})" :aria-label="__('Remove')">
                            <x-lucide-trash-2 />
                        </x-ui.button>
                    </div>
                </x-ui.item>
            @empty
                <p class="text-sm text-muted-foreground">{{ __('No members yet.') }}</p>
            @endforelse

            <x-ui.button type="button" variant="outline" class="h-11 md:hidden" wire:click="addMember">
                <x-lucide-plus /> {{ __('Add member') }}
            </x-ui.button>

            @if ($pastMembers->isNotEmpty())
                <div class="flex flex-col gap-2">
                    <h3 class="text-sm font-medium text-muted-foreground">{{ __('Past members') }}</h3>
                    @foreach ($pastMembers as $past)
                        <p class="text-sm" wire:key="past-{{ $past->id }}">
                            {{ $past->user->name }}
                            <span class="text-muted-foreground">{{ $past->joined_on->format('d-M-Y') }} – {{ $past->left_on?->format('d-M-Y') }}</span>
                        </p>
                    @endforeach
                </div>
            @endif
        </x-shell.form-section>

        <x-slot:aside>
            <x-shell.form-section :title="__('Status')">
                <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                    <x-ui.switch id="is_active" wire:model="is_active" :checked="$is_active" />
                    <x-ui.field-label for="is_active">{{ __('Active') }}</x-ui.field-label>
                </x-ui.field>
            </x-shell.form-section>
        </x-slot:aside>
    </x-shell.form-screen>

    @if ($team)
        <div class="mx-auto mt-6 w-full max-w-6xl pb-28 md:pb-0">
            <livewire:foundation.history :model="$team" :key="'history-'.$team->id" />
        </div>
    @endif
</div>
