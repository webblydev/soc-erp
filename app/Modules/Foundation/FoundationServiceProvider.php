<?php

namespace App\Modules\Foundation;

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use App\Modules\Foundation\Listeners\RecordAuthenticationAudit;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\Navigation;
use App\Modules\Foundation\Services\PermissionRegistrar;
use App\Support\Lookups\LookupRegistry;
use App\Support\Settings\SettingsRepository;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FoundationServiceProvider extends ServiceProvider
{
    /**
     * Register foundation services.
     */
    public function register(): void
    {
        $this->app->singleton(LookupRegistry::class, fn (): LookupRegistry => new LookupRegistry(config('lookups', [])));
        $this->app->singleton(SettingsRepository::class);
        $this->app->scoped(PermissionRegistrar::class);
        $this->app->singleton(Navigation::class, fn (): Navigation => new Navigation(config('navigation.groups', [])));
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

        Event::listen(Login::class, [RecordAuthenticationAudit::class, 'handleLogin']);
        Event::listen(Logout::class, [RecordAuthenticationAudit::class, 'handleLogout']);

        Livewire::addLocation(classNamespace: 'App\\Modules\\Foundation\\Livewire');

        Livewire::addPersistentMiddleware([EnsureUserIsActive::class, EnforceSessionTimeout::class]);
    }
}
