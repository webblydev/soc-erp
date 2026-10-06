<?php

namespace App\Modules\Foundation\Listeners;

use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use App\Modules\Foundation\Models\LoginHistory;
use App\Modules\Foundation\Notifications\NewIpSignIn;
use App\Support\Facades\Settings;
use Illuminate\Auth\Events\Login;

/**
 * security.login_new_ip (docs/01 §9): alerts users in notifications.new_ip_roles when a password
 * sign-in comes from an IP none of their earlier successful sign-ins used. A first sign-in, a
 * remember-me login (no fresh login history row) and impersonation are not alerted.
 */
class NotifyNewIpSignIn
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || (app()->bound('session') && session()->has(HandleImpersonation::SESSION_KEY))) {
            return;
        }

        /** @var list<string> $roles */
        $roles = config('notifications.new_ip_roles', []);

        if (! collect($roles)->contains(fn (string $role): bool => $user->hasRole($role))) {
            return;
        }

        $successes = LoginHistory::query()->where('user_id', $user->id)->where('succeeded', true);
        $current = (clone $successes)->latest('id')->first();

        if ($current === null || $current->ip_address !== request()->ip() || $current->created_at->lt(now()->subMinute())) {
            return;
        }

        $earlier = (clone $successes)->whereKeyNot($current->id);

        if (! $earlier->exists() || (clone $earlier)->where('ip_address', $current->ip_address)->exists()) {
            return;
        }

        $user->notify(new NewIpSignIn(
            $current->ip_address,
            $current->created_at->timezone((string) Settings::get('general.timezone', config('app.timezone')))->format(Settings::get('general.date_format', 'd-M-Y').' H:i'),
        ));
    }
}
