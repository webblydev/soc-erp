<?php

namespace App\Http\Middleware;

use App\Modules\Foundation\Services\TwoFactorPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends users whose role requires 2FA to the setup page until they have confirmed it.
 */
class EnsureTwoFactorEnabled
{
    public function __construct(private TwoFactorPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && ! $request->session()->has(HandleImpersonation::SESSION_KEY)
            && ! $request->routeIs('two-factor.setup', 'password.change', 'logout')
            && ! $user->hasEnabledTwoFactorAuthentication()
            && $this->policy->requires($user)) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
