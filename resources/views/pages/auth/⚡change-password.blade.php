<?php

use App\Modules\Foundation\Actions\ChangePassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Change password')] #[Layout('layouts::auth')] class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Save the new password and continue to the app.
     */
    public function save(ChangePassword $changePassword): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::default()],
        ]);

        $changePassword->handle(Auth::user(), $this->password);

        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Choose a new password')" :description="__('You need to set a new password before you continue.')" />

    <form wire:submit="save" class="flex flex-col gap-6">
        <x-ui.field>
            <x-ui.field-label for="current_password">{{ __('Current password') }}</x-ui.field-label>
            <x-ui.input id="current_password" wire:model="current_password" type="password" required autocomplete="current-password" :aria-invalid="$errors->has('current_password') ? 'true' : null" />
            <x-ui.field-error :messages="$errors->get('current_password')" />
        </x-ui.field>

        <x-ui.field>
            <x-ui.field-label for="password">{{ __('New password') }}</x-ui.field-label>
            <x-ui.input id="password" wire:model="password" type="password" required autocomplete="new-password" :aria-invalid="$errors->has('password') ? 'true' : null" />
            <x-ui.field-error :messages="$errors->get('password')" />
        </x-ui.field>

        <x-ui.field>
            <x-ui.field-label for="password_confirmation">{{ __('Confirm new password') }}</x-ui.field-label>
            <x-ui.input id="password_confirmation" wire:model="password_confirmation" type="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.button type="submit" class="h-11 w-full" data-test="change-password-button">
            {{ __('Save password') }}
        </x-ui.button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <x-ui.button type="submit" variant="link">{{ __('Log out') }}</x-ui.button>
    </form>
</div>
