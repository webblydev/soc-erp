<?php

namespace App\Modules\Foundation;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\PermissionRegistrar;
use App\Support\Lookups\LookupRegistry;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FoundationServiceProvider extends ServiceProvider
{
    /**
     * Register foundation services.
     */
    public function register(): void
    {
        $this->app->singleton(LookupRegistry::class, fn (): LookupRegistry => new LookupRegistry(config('lookups', [])));
        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(PermissionRegistrar::class);
    }

    /**
     * Bootstrap foundation services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/foundation'));

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole(Role::SUPER_ADMIN)) {
                return true;
            }

            return str_contains($ability, '.') && $user->hasPermission($ability) ? true : null;
        });
    }
}
