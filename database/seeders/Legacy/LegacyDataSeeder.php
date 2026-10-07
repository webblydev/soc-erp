<?php

namespace Database\Seeders\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Copies the v1 records into the dev database (legacy seed spec). Reads the `legacy` connection
 * at run time, so no personal data lives in the repository. Not part of DatabaseSeeder:
 * php artisan db:seed --class="Database\Seeders\Legacy\LegacyDataSeeder"
 */
class LegacyDataSeeder extends Seeder
{
    /**
     * Importers in dependency order.
     *
     * @var list<class-string>
     */
    public const IMPORTERS = [
        ImportEmployees::class,
        ImportUsers::class,
        ImportSalesTeams::class,
        ImportClients::class,
        ImportClientActivities::class,
        ImportProjects::class,
        ImportTasks::class,
        ImportWorkEstimates::class,
        ImportMaterialEstimates::class,
        ImportProjectVisits::class,
    ];

    public function run(): void
    {
        if (! self::legacyAvailable()) {
            $this->command?->warn('Legacy data skipped: set LEGACY_DB_DATABASE to a loaded copy of docs/legacy_database.sql.');

            return;
        }

        $context = new LegacyContext;

        DB::transaction(fn () => Model::withoutEvents(function () use ($context): void {
            foreach (self::IMPORTERS as $importer) {
                $created = app($importer)->run($context);
                $this->command?->info(class_basename($importer).": {$created} created");
            }
        }));
    }

    public static function legacyAvailable(): bool
    {
        if (blank(config('database.connections.legacy.database'))) {
            return false;
        }

        try {
            DB::connection('legacy')->select('select 1');
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
