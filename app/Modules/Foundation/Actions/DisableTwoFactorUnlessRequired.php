<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Services\TwoFactorPolicy;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

/**
 * Also guards Fortify's own DELETE /user/two-factor-authentication route.
 */
class DisableTwoFactorUnlessRequired extends DisableTwoFactorAuthentication
{
    /**
     * @param  mixed  $user
     *
     * @throws ValidationException
     */
    public function __invoke($user)
    {
        if (app(TwoFactorPolicy::class)->requires($user)) {
            throw ValidationException::withMessages(['two_factor' => __('Your role requires two-factor authentication.')]);
        }

        parent::__invoke($user);
    }
}
