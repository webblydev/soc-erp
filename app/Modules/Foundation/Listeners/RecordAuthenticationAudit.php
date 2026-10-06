<?php

namespace App\Modules\Foundation\Listeners;

use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use App\Support\AuditTrail\AuditTrail;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordAuthenticationAudit
{
    public function handleLogin(Login $event): void
    {
        if (app()->bound('session') && session()->has(HandleImpersonation::SESSION_KEY)) {
            return;
        }

        if ($event->user instanceof User) {
            AuditTrail::record($event->user, 'login', actor: $event->user);
        }
    }

    /**
     * Logging out while impersonating ends the impersonation: it is recorded as such on the
     * target, with the impersonator as the actor, instead of as the target's logout.
     */
    public function handleLogout(Logout $event): void
    {
        $impersonatorId = app()->bound('session') ? session(HandleImpersonation::SESSION_KEY) : null;

        if ($event->user instanceof User && $impersonatorId !== null) {
            $impersonator = User::query()->whereKey($impersonatorId)->first();
            AuditTrail::record($event->user, 'impersonation_ended', null, ['impersonator_id' => (int) $impersonatorId, 'user_id' => $event->user->id], $impersonator);

            return;
        }

        if ($event->user instanceof User) {
            AuditTrail::record($event->user, 'logout', actor: $event->user);
        }
    }
}
