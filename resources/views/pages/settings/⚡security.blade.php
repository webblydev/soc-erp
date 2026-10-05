<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';



    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {

    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('toast', type: 'success', description: __('Password updated.'));
    }


}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <x-ui.field>
                <x-ui.field-label for="current_password">{{ __('Current password') }}</x-ui.field-label>
                <x-ui.input id="current_password" wire:model="current_password" type="password" required autocomplete="current-password" :aria-invalid="$errors->has('current_password') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('current_password')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="password">{{ __('New password') }}</x-ui.field-label>
                <x-ui.input id="password" wire:model="password" type="password" required autocomplete="new-password" passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" :aria-invalid="$errors->has('password') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('password')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="password_confirmation">{{ __('Confirm password') }}</x-ui.field-label>
                <x-ui.input id="password_confirmation" wire:model="password_confirmation" type="password" required autocomplete="new-password" passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" />
            </x-ui.field>

            <x-ui.button type="submit" data-test="update-password-button">
                {{ __('Save') }}
            </x-ui.button>
        </form>
    </x-pages::settings.layout>
</section>
