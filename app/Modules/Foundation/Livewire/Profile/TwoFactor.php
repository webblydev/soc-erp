<?php

namespace App\Modules\Foundation\Livewire\Profile;

use App\Models\User;
use App\Modules\Foundation\Services\TwoFactorPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Component;

/**
 * Self-service 2FA: enable → scan → confirm → recovery codes; regenerate; disable.
 * Used on the profile page and, with forced=true, on the forced setup page.
 */
class TwoFactor extends Component
{
    public bool $forced = false;

    public string $code = '';

    public string $current_password = '';

    public bool $showingRecoveryCodes = false;

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $this->validate(['current_password' => ['required', 'string', 'current_password']]);
        $this->reset('current_password');

        $enable($this->user());
        $this->showingRecoveryCodes = false;
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => ['required', 'string']]);

        try {
            $confirm($this->user(), $this->code);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['code' => $exception->errors()['code'] ?? [__('The code is invalid.')]]);
        }

        $this->reset('code');
        $this->showingRecoveryCodes = true;
        $this->dispatch('toast', type: 'success', description: __('Two-factor authentication is on.'));
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $this->validate(['current_password' => ['required', 'string', 'current_password']]);
        $this->reset('current_password');

        $generate($this->user());
        $this->showingRecoveryCodes = true;
    }

    public function disable(DisableTwoFactorAuthentication $disable, TwoFactorPolicy $policy): void
    {
        $this->validate(['current_password' => ['required', 'string', 'current_password']]);
        $this->reset('current_password');

        if ($policy->requires($this->user())) {
            $this->dispatch('toast', type: 'error', description: __('Your role requires two-factor authentication.'));

            return;
        }

        $disable($this->user());
        $this->showingRecoveryCodes = false;
        $this->dispatch('toast', type: 'success', description: __('Two-factor authentication is off.'));
    }

    public function render(): View
    {
        $user = $this->user()->refresh();
        $pending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;

        return view('livewire.profile.two-factor', [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'pending' => $pending,
            'qrSvg' => $pending ? $user->twoFactorQrCodeSvg() : null,
            'setupKey' => $pending ? decrypt((string) $user->two_factor_secret) : null,
            'recoveryCodes' => $this->showingRecoveryCodes && $user->two_factor_recovery_codes !== null ? $user->recoveryCodes() : [],
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
