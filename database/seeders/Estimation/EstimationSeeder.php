<?php

namespace Database\Seeders\Estimation;

use Illuminate\Database\Seeder;

class EstimationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([EstimationLookupSeeder::class, EstimationSettingSeeder::class]);
    }
}
