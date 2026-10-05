<?php

namespace App\Modules\Foundation;

use App\Support\Lookups\LookupRegistry;
use App\Support\Settings\SettingsRepository;
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
    }

    /**
     * Bootstrap foundation services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/foundation'));
    }
}
