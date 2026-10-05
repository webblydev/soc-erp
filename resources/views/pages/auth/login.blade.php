<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-ui.field>
                <x-ui.field-label for="email">{{ __('Email address') }}</x-ui.field-label>
                <x-ui.input id="email" name="email" type="email" :value="old('email')" required autofocus autocomplete="email" placeholder="email@example.com" :aria-invalid="$errors->has('email') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('email')" />
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

        @if (Route::has('register'))
            <div class="space-x-1 text-center text-sm text-muted-foreground rtl:space-x-reverse">
                <span>{{ __('Don\'t have an account?') }}</span>
                <x-ui.link :href="route('register')" wire:navigate>{{ __('Sign up') }}</x-ui.link>
            </div>
        @endif
    </div>
</x-layouts::auth>
