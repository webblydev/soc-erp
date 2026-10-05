<div class="flex flex-col gap-4">
    @if ($enabled)
        <x-ui.alert>
            <x-lucide-shield-check />
            <x-ui.alert-title>{{ __('Two-factor authentication is on') }}</x-ui.alert-title>
            <x-ui.alert-description>{{ __('You will be asked for a code from your authenticator app when you sign in.') }}</x-ui.alert-description>
        </x-ui.alert>
    @elseif ($pending)
        <p class="text-sm">{{ __('Scan this QR code with Google Authenticator, Microsoft Authenticator or a similar app, then enter the 6-digit code it shows.') }}</p>
        <div class="flex justify-center rounded-md border bg-white p-4 [&_svg]:size-48">{!! $qrSvg !!}</div>
        <p class="text-sm text-muted-foreground">{{ __('Or enter this key:') }} <span class="font-mono break-all">{{ $setupKey }}</span></p>
        <form wire:submit="confirm" class="flex flex-col gap-3">
            <x-ui.field>
                <x-ui.field-label for="code">{{ __('Code') }}</x-ui.field-label>
                <x-ui.input id="code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" class="h-11 text-base tracking-widest" :aria-invalid="$errors->has('code') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('code')" />
            </x-ui.field>
            <x-ui.button type="submit" class="h-11 md:h-9 md:self-start">{{ __('Confirm') }}</x-ui.button>
        </form>
    @else
        <p class="text-sm text-muted-foreground">{{ __('Add a second step to sign-in: a code from an authenticator app on your phone.') }}</p>
        <x-ui.button class="h-11 md:h-9 md:self-start" wire:click="enable">{{ __('Turn on two-factor authentication') }}</x-ui.button>
    @endif

    @if ($recoveryCodes !== [])
        <div class="flex flex-col gap-2 rounded-md border p-4">
            <p class="text-sm font-medium">{{ __('Recovery codes') }}</p>
            <p class="text-sm text-muted-foreground">{{ __('Store these somewhere safe. Each one signs you in once if you lose your phone.') }}</p>
            <ul class="grid grid-cols-1 gap-1 font-mono text-sm sm:grid-cols-2">
                @foreach ($recoveryCodes as $recoveryCode)
                    <li>{{ $recoveryCode }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($enabled)
        <div class="flex flex-col gap-3 md:flex-row md:items-end">
            <x-ui.button variant="outline" class="h-11 md:h-9" wire:click="regenerateRecoveryCodes">{{ __('New recovery codes') }}</x-ui.button>
            @if ($forced)
                <x-ui.button class="h-11 md:h-9" :href="route('dashboard')">{{ __('Continue') }}</x-ui.button>
            @else
                <form wire:submit="disable" class="flex flex-col gap-2 md:flex-row md:items-end">
                    <x-ui.field>
                        <x-ui.field-label for="tf-current-password">{{ __('Current password to turn off') }}</x-ui.field-label>
                        <x-ui.input id="tf-current-password" type="password" wire:model="current_password" autocomplete="current-password" class="h-11 text-base md:h-9 md:text-sm" />
                        <x-ui.field-error :messages="$errors->get('current_password')" />
                    </x-ui.field>
                    <x-ui.button type="submit" variant="destructive" class="h-11 md:h-9">{{ __('Turn off') }}</x-ui.button>
                </form>
            @endif
        </div>
    @endif
</div>
