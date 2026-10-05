<?php

use App\Models\User;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\PermissionManifest;
use Database\Seeders\Foundation\FoundationSeeder;

beforeEach(fn () => config(['foundation.initial_admin.password' => 'initial-secret']));

test('seeding creates every manifest permission, the system roles and the super admin', function () {
    $this->seed(FoundationSeeder::class);

    $expected = collect(PermissionManifest::discover()->permissions())->pluck('name')->sort()->values()->all();

    expect(Permission::query()->orderBy('name')->pluck('name')->all())->toBe($expected)
        ->and(Role::query()->where('is_system', true)->count())->toBe(10)
        ->and(Branch::query()->where('code', 'HO')->exists())->toBeTrue()
        ->and(CompanyProfile::query()->count())->toBe(1);

    $admin = User::query()->where('username', 'admin')->sole();

    expect($admin->hasRole('super_admin'))->toBeTrue()
        ->and($admin->must_change_password)->toBeTrue();
});

test('wildcard grants are expanded onto roles', function () {
    $this->seed(FoundationSeeder::class);

    $management = Role::query()->where('code', 'management')->sole();
    $viewer = Role::query()->where('code', 'viewer')->sole();

    expect($management->permissions()->pluck('name'))->toContain('admin.users.view', 'admin.settings.update')
        ->and($management->permissions()->pluck('name'))->not->toContain('admin.users.impersonate')
        ->and($viewer->permissions()->pluck('name'))->toContain('admin.audit.view')
        ->and($viewer->permissions()->pluck('name'))->not->toContain('admin.users.create');
});

test('seeding twice changes nothing', function () {
    $this->seed(FoundationSeeder::class);
    $counts = [Permission::query()->count(), Role::query()->count(), User::query()->count()];

    $this->seed(FoundationSeeder::class);

    expect([Permission::query()->count(), Role::query()->count(), User::query()->count()])->toBe($counts);
});

test('permissions removed from manifests are deleted', function () {
    createPermissions('legacy.thing.view');

    $this->seed(FoundationSeeder::class);

    expect(Permission::query()->where('name', 'legacy.thing.view')->exists())->toBeFalse();
});

test('seeding without an initial admin password fails', function () {
    config(['foundation.initial_admin.password' => null]);

    $this->seed(FoundationSeeder::class);
})->throws(RuntimeException::class);
