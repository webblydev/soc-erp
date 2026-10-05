<x-layouts::auth :title="__('Reset password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <x-ui.field>
                <x-ui.field-label for="email">{{ __('Email') }}</x-ui.field-label>
                <x-ui.input id="email" name="email" type="email" value="{{ old('email', request('email')) }}" required autocomplete="email" :aria-invalid="$errors->has('email') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('email')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="password">{{ __('Password') }}</x-ui.field-label>
                <x-ui.input id="password" name="password" type="password" required autocomplete="new-password" :placeholder="__('Password')" passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" :aria-invalid="$errors->has('password') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('password')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="password_confirmation">{{ __('Confirm password') }}</x-ui.field-label>
                <x-ui.input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" :placeholder="__('Confirm password')" passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" />
            </x-ui.field>

            <x-ui.button type="submit" class="w-full" data-test="reset-password-button">
                {{ __('Reset password') }}
            </x-ui.button>
        </form>
    </div>
</x-layouts::auth>
