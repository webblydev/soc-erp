<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\PermissionManifest;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Adds the manifest default grants to each role. Grants an admin added in the UI are kept.
     */
    public function run(): void
    {
        $names = array_values(array_map(strval(...), Permission::query()->pluck('name')->all()));

        foreach (PermissionManifest::discover()->expandGrants($names) as $code => $granted) {
            Role::query()->where('code', $code)->firstOrFail()->grantPermissions($granted);
        }
    }
}
