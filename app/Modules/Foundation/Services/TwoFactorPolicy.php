<?php

namespace App\Modules\Foundation\Services;

use App\Models\User;
use App\Support\Facades\Settings;

/**
 * Decides whether a user must use 2FA (docs/01 §5.1, setting general.require_2fa_roles).
 */
final class TwoFactorPolicy
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function requires(User $user): bool
    {
        $required = array_map(strval(...), (array) Settings::get('general.require_2fa_roles', []));

        return $required !== [] && array_intersect($required, $this->registrar->rolesFor($user)) !== [];
    }
}
