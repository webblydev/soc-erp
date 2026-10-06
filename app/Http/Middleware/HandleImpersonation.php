<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a super admin is signed in as someone else (FD-BR-10), user and role administration
 * is off limits so the session cannot be used to escalate the impersonated account.
 */
class HandleImpersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()
            && $request->session()->has(self::SESSION_KEY)
            && $request->routeIs('admin.users.*', 'admin.roles.*')) {
            abort(403, __('Return to your own account to manage users and roles.'));
        }

        return $next($request);
    }
}
