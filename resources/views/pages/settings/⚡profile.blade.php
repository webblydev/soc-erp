<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('toast', type: 'success', description: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <x-ui.field>
                <x-ui.field-label for="name">{{ __('Name') }}</x-ui.field-label>
                <x-ui.input id="name" wire:model="name" type="text" required autofocus autocomplete="name" :aria-invalid="$errors->has('name') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('name')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="email">{{ __('Email') }}</x-ui.field-label>
                <x-ui.input id="email" wire:model="email" type="email" required autocomplete="email" :aria-invalid="$errors->has('email') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('email')" />

                @if ($this->hasUnverifiedEmail)
                    <x-ui.field-description>
                        {{ __('Your email address is unverified.') }}
                        <x-ui.link href="#" class="cursor-pointer" wire:click.prevent="resendVerificationNotification">
                            {{ __('Click here to re-send the verification email.') }}
                        </x-ui.link>
                    </x-ui.field-description>

                    @if (session('status') === 'verification-link-sent')
                        <x-ui.field-description class="font-medium text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </x-ui.field-description>
                    @endif
                @endif
            </x-ui.field>

            <x-ui.button type="submit" data-test="update-profile-button">
                {{ __('Save') }}
            </x-ui.button>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
