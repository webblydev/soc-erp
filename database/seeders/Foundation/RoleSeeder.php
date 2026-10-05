<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * System roles from docs/01 §2.
     *
     * @var array<string, array{name: string, description: string}>
     */
    public const ROLES = [
        'super_admin' => ['name' => 'Super Admin', 'description' => 'Full access, bypasses all policies'],
        'management' => ['name' => 'Management', 'description' => 'MD / directors: read everything, approve, settings'],
        'sales_manager' => ['name' => 'Sales Manager', 'description' => 'Sales team manager'],
        'sales_executive' => ['name' => 'Sales Executive', 'description' => 'Sales / marketing officer'],
        'project_manager' => ['name' => 'Project Manager', 'description' => 'Project / operations manager'],
        'engineer' => ['name' => 'Engineer', 'description' => 'Designer, architect, site engineer, draftsman'],
        'accountant' => ['name' => 'Accountant', 'description' => 'Accounts officer'],
        'finance_manager' => ['name' => 'Finance Manager', 'description' => 'Approves and posts finance'],
        'hr_admin' => ['name' => 'HR & Admin', 'description' => 'HR & Admin'],
        'viewer' => ['name' => 'Viewer', 'description' => 'Read-only auditor'],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $code => $role) {
            Role::query()->firstOrCreate(['code' => $code], [...$role, 'is_system' => true]);
        }
    }
}
