<?php

namespace Database\Seeders;

use Database\Seeders\Catalog\CatalogSeeder;
use Database\Seeders\Crm\CrmSeeder;
use Database\Seeders\Foundation\FoundationSeeder;
use Database\Seeders\Hrm\HrmSeeder;
use Database\Seeders\Projects\ProjectsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([FoundationSeeder::class, CatalogSeeder::class, CrmSeeder::class, HrmSeeder::class, ProjectsSeeder::class]);
    }
}
