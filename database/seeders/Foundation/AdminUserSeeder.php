<?php

namespace Database\Seeders\Foundation;

use App\Models\User;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{name: string, username: string, email: ?string, password: ?string} $admin */
        $admin = config('foundation.initial_admin');

        if (User::withTrashed()->where('username', $admin['username'])->exists()) {
            return;
        }

        if (blank($admin['password'])) {
            throw new RuntimeException('Set INITIAL_ADMIN_PASSWORD before seeding the initial super admin.');
        }

        $user = User::query()->create([
            'name' => $admin['name'],
            'username' => $admin['username'],
            'email' => $admin['email'] ?: null,
            'password' => $admin['password'],
            'is_active' => true,
            'must_change_password' => false,
            'branch_id' => Branch::query()->where('code', 'HO')->value('id'),
        ]);

        $user->syncRoles([Role::SUPER_ADMIN]);
    }
}
