<?php

namespace App\Modules\Catalog;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap catalog services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/catalog'));

        Livewire::addLocation(classNamespace: 'App\\Modules\\Catalog\\Livewire');
    }
}
