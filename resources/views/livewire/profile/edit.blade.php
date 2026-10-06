@php($tabs = ['details' => __('Details'), 'password' => __('Password'), 'two-factor' => __('Two-factor'), 'notifications' => __('Notifications')])

<div class="flex flex-col gap-4">
    <div class="flex items-center gap-3">
        <x-ui.avatar class="size-14">
            @if ($user->avatar_path)
                <x-ui.avatar-image :src="\Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path)" :alt="$user->name" />
            @endif
            <x-ui.avatar-fallback>{{ $user->initials() }}</x-ui.avatar-fallback>
        </x-ui.avatar>
        <div class="min-w-0">
            <p class="truncate text-base font-semibold">{{ $user->name }}</p>
            <p class="truncate text-sm text-muted-foreground">{{ '@'.$user->username }} @if ($user->email) · {{ $user->email }} @endif</p>
        </div>
    </div>

    <div role="tablist" class="hidden gap-1 border-b md:flex">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')"
                @class(['px-3 py-2 text-sm', 'border-b-2 border-primary font-medium' => $tab === $key, 'text-muted-foreground' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>
    <div class="overflow-x-auto md:hidden">
        <x-ui.segmented-control name="profile-tab" wire:model.live="tab" :value="$tab" :options="$tabs" class="h-11" />
    </div>

    <div class="max-w-xl">
        @if ($tab === 'details')
            <form wire:submit="saveDetails" class="flex flex-col gap-6">
                <x-ui.field>
                    <x-ui.field-label for="name">{{ __('Name') }}</x-ui.field-label>
                    <x-ui.input id="name" wire:model="name" autocomplete="name" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('name')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="phone">{{ __('Phone') }}</x-ui.field-label>
                    <x-ui.input id="phone" type="tel" inputmode="tel" wire:model="phone" placeholder="01XXXXXXXXX" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('phone')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="avatar">{{ __('Photo (PNG or JPG, up to 1 MB)') }}</x-ui.field-label>
                    <x-ui.input id="avatar" type="file" wire:model="avatar" accept="image/png,image/jpeg" class="h-11 md:h-9" />
                    <x-ui.field-error :messages="$errors->get('avatar')" />
                </x-ui.field>
                <x-ui.button type="submit" class="h-11 md:h-9 md:self-start">{{ __('Save') }}</x-ui.button>
            </form>
        @elseif ($tab === 'password')
            <form wire:submit="savePassword" class="flex flex-col gap-6">
                <x-ui.field>
                    <x-ui.field-label for="current_password">{{ __('Current password') }}</x-ui.field-label>
                    <x-ui.input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('current_password')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="password">{{ __('New password') }}</x-ui.field-label>
                    <x-ui.input id="password" type="password" wire:model="password" autocomplete="new-password" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('password')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="password_confirmation">{{ __('Confirm new password') }}</x-ui.field-label>
                    <x-ui.input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
                <x-ui.button type="submit" class="h-11 md:h-9 md:self-start">{{ __('Update password') }}</x-ui.button>
            </form>
        @elseif ($tab === 'two-factor')
            <livewire:foundation.profile.two-factor :key="'two-factor-panel'" />
        @else
            <form wire:submit="saveNotifications" class="flex flex-col gap-4">
                @foreach ($notificationKeys as $key => $definition)
                    @php($field = str_replace('.', '__', $key))
                    <div class="flex flex-col gap-1 border-b pb-3">
                        <p class="text-sm font-medium">{{ __($definition['label']) }}</p>
                        @foreach ($definition['channels'] as $channel)
                            <label class="flex min-h-11 items-center gap-3 text-sm">
                                <x-ui.switch wire:model="notifications.{{ $field }}.{{ $channel }}" :checked="(bool) ($notifications[$field][$channel] ?? true)" />
                                {{ __(match ($channel) { 'mail' => 'Email', 'sms' => 'SMS', default => 'In-app' }) }}
                            </label>
                        @endforeach
                    </div>
                @endforeach
                <x-ui.button type="submit" class="h-11 md:h-9 md:self-start">{{ __('Save preferences') }}</x-ui.button>
            </form>
        @endif
    </div>
</div>
