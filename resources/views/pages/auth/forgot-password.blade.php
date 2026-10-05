<x-layouts::auth :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <x-ui.field>
                <x-ui.field-label for="email">{{ __('Email address') }}</x-ui.field-label>
                <x-ui.input id="email" name="email" type="email" :value="old('email')" required autofocus placeholder="email@example.com" :aria-invalid="$errors->has('email') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('email')" />
            </x-ui.field>

            <x-ui.button type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('Email password reset link') }}
            </x-ui.button>
        </form>

        <div class="space-x-1 text-center text-sm text-muted-foreground rtl:space-x-reverse">
            <span>{{ __('Or, return to') }}</span>
            <x-ui.link :href="route('login')" wire:navigate>{{ __('log in') }}</x-ui.link>
        </div>
    </div>
</x-layouts::auth>
