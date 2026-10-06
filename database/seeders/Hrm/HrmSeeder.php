<?php

namespace Database\Seeders\Hrm;

use Illuminate\Database\Seeder;

class HrmSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([HrmLookupSeeder::class]);
    }
}
