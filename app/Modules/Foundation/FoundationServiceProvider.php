<?php

namespace App\Modules\Foundation;

use App\Support\Lookups\LookupRegistry;
use Illuminate\Support\ServiceProvider;

class FoundationServiceProvider extends ServiceProvider
{
    /**
     * Register foundation services.
     */
    public function register(): void
    {
        $this->app->singleton(LookupRegistry::class, fn (): LookupRegistry => new LookupRegistry(config('lookups', [])));
    }

    /**
     * Bootstrap foundation services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/foundation'));
    }
}
