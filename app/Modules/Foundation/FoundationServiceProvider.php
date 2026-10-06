<?php

namespace App\Modules\Foundation;

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use App\Modules\Foundation\Listeners\DeactivateLinkedUser;
use App\Modules\Foundation\Listeners\NotifyNewIpSignIn;
use App\Modules\Foundation\Listeners\RecordAuthenticationAudit;
use App\Modules\Foundation\Livewire\Notifications\Bell;
use App\Modules\Foundation\Livewire\Shared\Attachments;
use App\Modules\Foundation\Livewire\Shared\DetailModal;
use App\Modules\Foundation\Livewire\Shared\History;
use App\Modules\Foundation\Livewire\Shared\Notes;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\Navigation;
use App\Modules\Foundation\Services\PermissionRegistrar;
use App\Modules\Hrm\Events\EmployeeDeactivated;
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
        Event::listen(Login::class, NotifyNewIpSignIn::class);
        Event::listen(Logout::class, [RecordAuthenticationAudit::class, 'handleLogout']);
        Event::listen(EmployeeDeactivated::class, DeactivateLinkedUser::class);

        Livewire::addLocation(classNamespace: 'App\\Modules\\Foundation\\Livewire');

        Livewire::component('foundation.attachments', Attachments::class);
        Livewire::component('foundation.notes', Notes::class);
        Livewire::component('foundation.history', History::class);
        Livewire::component('foundation.detail-modal', DetailModal::class);
        Livewire::component('foundation.notifications.bell', Bell::class);

        Livewire::addPersistentMiddleware([EnsureUserIsActive::class, EnforceSessionTimeout::class, EnsurePasswordChanged::class, HandleImpersonation::class]);
    }
}
