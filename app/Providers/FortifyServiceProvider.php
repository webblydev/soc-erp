<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Modules\Foundation\Actions\AuthenticateUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // With 2FA on, Fortify's login pipeline calls this twice per request (credential check, then attempt).
        // Resolve it once so the attempt is recorded in the login history only once.
        Fortify::authenticateUsing(function (Request $request): ?User {
            if (! $request->attributes->has('fortify.authenticated_user')) {
                $request->attributes->set('fortify.authenticated_user', app(AuthenticateUser::class)->handle(
                    (string) $request->input('login'),
                    (string) $request->input('password'),
                    $request->ip(),
                    $request->userAgent(),
                ));
            }

            return $request->attributes->get('fortify.authenticated_user');
        });

        // Fortify's default confirmation looks the user up by the `login` form field, which is not a column.
        Fortify::confirmPasswordsUsing(fn (User $user, ?string $password): bool => Hash::check((string) $password, $user->password));
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
