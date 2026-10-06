<?php

namespace App\Modules\Crm;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CrmServiceProvider extends ServiceProvider
{
    /**
     * Resources whose `view` gate means "any data scope" (spec R6).
     */
    private const SCOPED_RESOURCES = ['leads', 'customers', 'activities'];

    /**
     * Bootstrap CRM services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/crm'));

        foreach (self::SCOPED_RESOURCES as $resource) {
            Gate::define("crm.{$resource}.view", fn (User $user): bool => $user->hasPermission("crm.{$resource}.view_own")
                || $user->hasPermission("crm.{$resource}.view_team")
                || $user->hasPermission("crm.{$resource}.view_all"));
        }

        Livewire::addLocation(classNamespace: 'App\\Modules\\Crm\\Livewire');
    }
}
