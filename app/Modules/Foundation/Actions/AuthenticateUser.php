<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\LoginHistory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Username-or-email login (docs/01 §5.1) with login history and FD-BR-04 lockout.
 */
class AuthenticateUser
{
    public const LOCKOUT_ATTEMPTS = 10;

    public const LOCKOUT_MINUTES = 15;

    /**
     * @throws ValidationException
     */
    public function handle(string $login, string $password, ?string $ip, ?string $userAgent): ?User
    {
        $login = Str::lower(trim($login));
        $attempted = mb_substr($login, 0, 60);

        $lockedUntil = $this->lockedUntil($attempted);

        if ($lockedUntil !== null) {
            throw ValidationException::withMessages([
                'login' => __('Too many failed attempts. Try again in :minutes minutes.', ['minutes' => max(1, (int) ceil(now()->diffInSeconds($lockedUntil) / 60))]),
            ]);
        }

        $user = User::query()
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(username) = ?', [$login])
                ->orWhereRaw('LOWER(email) = ?', [$login]))
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->record($attempted, $user, false, $ip, $userAgent);

            return null;
        }

        if (! $user->is_active) {
            $this->record($attempted, $user, false, $ip, $userAgent);

            throw ValidationException::withMessages(['login' => __('This account is inactive.')]);
        }

        $this->record($attempted, $user, true, $ip, $userAgent);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();

        return $user;
    }

    /**
     * FD-BR-04: once the latest failure completes 10 failures within 15 minutes, the login is
     * locked for 15 minutes from that failure. No failures are recorded while locked.
     */
    private function lockedUntil(string $attempted): ?CarbonInterface
    {
        $latestFailure = LoginHistory::query()
            ->where('username_attempted', $attempted)
            ->where('succeeded', false)
            ->where('created_at', '>=', now()->subMinutes(self::LOCKOUT_MINUTES))
            ->latest('created_at')
            ->value('created_at');

        if ($latestFailure === null) {
            return null;
        }

        $latestFailure = CarbonImmutable::parse($latestFailure);

        $failuresInWindow = LoginHistory::query()
            ->where('username_attempted', $attempted)
            ->where('succeeded', false)
            ->whereBetween('created_at', [$latestFailure->subMinutes(self::LOCKOUT_MINUTES), $latestFailure])
            ->count();

        return $failuresInWindow >= self::LOCKOUT_ATTEMPTS
            ? $latestFailure->addMinutes(self::LOCKOUT_MINUTES)
            : null;
    }

    private function record(string $attempted, ?User $user, bool $succeeded, ?string $ip, ?string $userAgent): void
    {
        LoginHistory::query()->create([
            'user_id' => $user?->id,
            'username_attempted' => $attempted,
            'succeeded' => $succeeded,
            'ip_address' => $ip ?? '0.0.0.0',
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 252) : null,
        ]);
    }
}
