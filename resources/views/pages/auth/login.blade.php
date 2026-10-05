<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your username or email and password below to log in')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-ui.field>
                <x-ui.field-label for="login">{{ __('Username or email') }}</x-ui.field-label>
                <x-ui.input id="login" name="login" type="text" :value="old('login')" required autofocus autocapitalize="none" autocomplete="username" :aria-invalid="$errors->has('login') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('login')" />
            </x-ui.field>

            <x-ui.field>
                <div class="flex items-center">
                    <x-ui.field-label for="password">{{ __('Password') }}</x-ui.field-label>
                    @if (Route::has('password.request'))
                        <x-ui.link class="ms-auto text-sm" :href="route('password.request')" wire:navigate>
                            {{ __('Forgot your password?') }}
                        </x-ui.link>
                    @endif
                </div>
                <x-ui.input id="password" name="password" type="password" required autocomplete="current-password" :placeholder="__('Password')" :aria-invalid="$errors->has('password') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('password')" />
            </x-ui.field>

            <x-ui.field orientation="horizontal">
                <x-ui.checkbox id="remember" name="remember" :checked="(bool) old('remember')" />
                <x-ui.field-label for="remember" class="font-normal">{{ __('Remember me') }}</x-ui.field-label>
            </x-ui.field>

            <x-ui.button type="submit" class="w-full" data-test="login-button">
                {{ __('Log in') }}
            </x-ui.button>
        </form>

    </div>
</x-layouts::auth>
