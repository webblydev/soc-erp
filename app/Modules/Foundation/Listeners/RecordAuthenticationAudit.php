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

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            AuditTrail::record($event->user, 'logout', actor: $event->user);
        }
    }
}
