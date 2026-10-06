<?php

namespace Database\Seeders\Foundation;

use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            BranchSeeder::class,
            CompanyProfileSeeder::class,
            SettingSeeder::class,
            NumberSequenceFormatSeeder::class,
            LocationSeeder::class,
            DocumentTypeSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
