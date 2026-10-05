<?php

namespace App\Http\Middleware;

use App\Support\Facades\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * FD-BR-12: log out after general.session_timeout_minutes of inactivity.
 */
class EnforceSessionTimeout
{
    public const SESSION_KEY = 'last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return $next($request);
        }

        $timeoutSeconds = (int) Settings::get('general.session_timeout_minutes', 120) * 60;
        $lastActivity = $request->session()->get(self::SESSION_KEY);

        if (is_int($lastActivity) && now()->getTimestamp() - $lastActivity > $timeoutSeconds) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', __('Your session expired. Please log in again.'));
        }

        $request->session()->put(self::SESSION_KEY, now()->getTimestamp());

        return $next($request);
    }
}
