<?php

namespace App\Modules\Foundation\Actions;

use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use App\Modules\Foundation\Models\Role;
use App\Support\AuditTrail\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartImpersonation
{
    /**
     * @throws ValidationException
     */
    public function handle(User $impersonator, User $target): void
    {
        $error = match (true) {
            ! $impersonator->hasRole(Role::SUPER_ADMIN) => __('Only super admins can sign in as another user.'),
            session()->has(HandleImpersonation::SESSION_KEY) => __('Return to your own account first.'),
            $target->is($impersonator) => __('You are already signed in as yourself.'),
            $target->hasRole(Role::SUPER_ADMIN) => __('Super admins cannot be impersonated.'),
            ! $target->is_active => __('Inactive users cannot be impersonated.'),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['user' => $error]);
        }

        DB::transaction(function () use ($impersonator, $target): void {
            AuditTrail::record($target, 'impersonation_started', null, ['impersonator_id' => $impersonator->id, 'user_id' => $target->id], $impersonator);
        });

        session()->put(HandleImpersonation::SESSION_KEY, $impersonator->id);
        Auth::guard('web')->login($target);
    }
}
