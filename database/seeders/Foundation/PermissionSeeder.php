<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Services\PermissionManifest;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = PermissionManifest::discover()->permissions();

        foreach ($rows as $row) {
            Permission::query()->updateOrCreate(['name' => $row['name']], $row);
        }

        Permission::query()->whereNotIn('name', array_column($rows, 'name'))->delete();
    }
}
