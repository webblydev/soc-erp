<?php

namespace App\Modules\Foundation;

use Illuminate\Support\ServiceProvider;

class FoundationServiceProvider extends ServiceProvider
{
    /**
     * Register foundation services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap foundation services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/foundation'));
    }
}
