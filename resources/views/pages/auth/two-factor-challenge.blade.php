<x-layouts::auth :title="__('Two-factor code')">
    <div class="flex flex-col gap-6" x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <x-auth-header :title="__('Two-factor authentication')" :description="__('Enter the 6-digit code from your authenticator app, or one of your recovery codes.')" />

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-ui.field x-show="! recovery">
                <x-ui.field-label for="code">{{ __('Code') }}</x-ui.field-label>
                <x-ui.input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus x-bind:disabled="recovery" class="h-11 text-base tracking-widest" :aria-invalid="$errors->has('code') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('code')" />
            </x-ui.field>

            <x-ui.field x-show="recovery" x-cloak>
                <x-ui.field-label for="recovery_code">{{ __('Recovery code') }}</x-ui.field-label>
                <x-ui.input id="recovery_code" name="recovery_code" autocomplete="off" x-bind:disabled="! recovery" class="h-11 text-base" :aria-invalid="$errors->has('recovery_code') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('recovery_code')" />
            </x-ui.field>

            <x-ui.button type="submit" class="h-11 w-full">{{ __('Continue') }}</x-ui.button>
        </form>

        <x-ui.button variant="link" class="h-11" x-on:click="recovery = ! recovery">
            <span x-show="! recovery">{{ __('Use a recovery code') }}</span>
            <span x-show="recovery" x-cloak>{{ __('Use an authentication code') }}</span>
        </x-ui.button>
    </div>
</x-layouts::auth>
