<?php

namespace Database\Seeders\Catalog;

use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BusinessLineSeeder::class,
            CategorySeeder::class,
            PricingBasisSeeder::class,
            UnitSeeder::class,
        ]);
    }
}
