<?php

namespace Database\Seeders\Crm;

use Illuminate\Database\Seeder;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CrmLookupSeeder::class, CrmSettingSeeder::class]);
    }
}
