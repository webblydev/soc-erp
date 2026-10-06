<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a super admin is signed in as someone else (FD-BR-10), user and role administration
 * is off limits so the session cannot be used to escalate the impersonated account. The
 * middleware also runs on Fortify's routes (config fortify.middleware), where it refuses the
 * 2FA management and password-confirmation endpoints so the target's secrets stay hidden.
 */
class HandleImpersonation
{
    public const SESSION_KEY = 'impersonator_id';

    /**
     * Route names refused while impersonating.
     *
     * @var list<string>
     */
    private const BLOCKED_ROUTES = [
        'admin.users.*',
        'admin.roles.*',
        'two-factor.*',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
    ];

    public static function isActive(): bool
    {
        return app()->bound('session') && session()->has(self::SESSION_KEY);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()
            && $request->session()->has(self::SESSION_KEY)
            && $request->routeIs(...self::BLOCKED_ROUTES)
            && ! $request->routeIs('two-factor.login', 'two-factor.login.store')) {
            abort(403, __('Return to your own account first.'));
        }

        return $next($request);
    }
}
