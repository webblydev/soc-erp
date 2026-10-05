<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\LoginHistory;
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

        if ($this->isLockedOut($login)) {
            throw ValidationException::withMessages([
                'login' => __('Too many failed attempts. Try again in :minutes minutes.', ['minutes' => self::LOCKOUT_MINUTES]),
            ]);
        }

        $user = User::query()
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(username) = ?', [$login])
                ->orWhereRaw('LOWER(email) = ?', [$login]))
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->record($login, $user, false, $ip, $userAgent);

            return null;
        }

        if (! $user->is_active) {
            $this->record($login, $user, false, $ip, $userAgent);

            throw ValidationException::withMessages(['login' => __('This account is inactive.')]);
        }

        $this->record($login, $user, true, $ip, $userAgent);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();

        return $user;
    }

    private function isLockedOut(string $login): bool
    {
        return LoginHistory::query()
            ->where('username_attempted', $login)
            ->where('succeeded', false)
            ->where('created_at', '>=', now()->subMinutes(self::LOCKOUT_MINUTES))
            ->count() >= self::LOCKOUT_ATTEMPTS;
    }

    private function record(string $login, ?User $user, bool $succeeded, ?string $ip, ?string $userAgent): void
    {
        LoginHistory::query()->create([
            'user_id' => $user?->id,
            'username_attempted' => Str::limit($login, 60, ''),
            'succeeded' => $succeeded,
            'ip_address' => $ip ?? '0.0.0.0',
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 252) : null,
        ]);
    }
}
