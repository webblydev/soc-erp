<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public ?string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill([
            'name' => $validated['name'],
            'email' => filled($validated['email'] ?? null) ? $validated['email'] : null,
        ]);

        $user->save();

        $this->dispatch('toast', type: 'success', description: __('Profile updated.'));
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return true;
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
                <x-ui.input id="email" wire:model="email" type="email" autocomplete="email" :aria-invalid="$errors->has('email') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('email')" />
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
