<?php

use App\Models\User;
use App\Modules\Foundation\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => createPermissions('admin.users.view', 'admin.users.create', 'admin.roles.view'));

test('users receive the permissions of their roles', function () {
    ensureRole('hr_admin')->syncPermissions(['admin.users.view']);
    $user = User::factory()->create();

    $user->assignRole('hr_admin');

    expect($user->hasRole('hr_admin'))->toBeTrue()
        ->and($user->hasPermission('admin.users.view'))->toBeTrue()
        ->and($user->hasPermission('admin.users.create'))->toBeFalse();
});

test('direct permissions are added on top of role grants', function () {
    $user = User::factory()->create();

    $user->syncDirectPermissions(['admin.roles.view']);

    expect($user->hasPermission('admin.roles.view'))->toBeTrue();
});

test('inactive roles grant nothing', function () {
    ensureRole('hr_admin', ['is_active' => false])->syncPermissions(['admin.users.view']);
    $user = User::factory()->create();
    $user->assignRole('hr_admin');

    expect($user->hasRole('hr_admin'))->toBeFalse()
        ->and($user->hasPermission('admin.users.view'))->toBeFalse();
});

test('super admin passes every gate', function () {
    ensureRole('super_admin');
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    expect(Gate::forUser($user)->allows('anything.at.all'))->toBeTrue();
});

test('dotted abilities resolve through permissions', function () {
    ensureRole('hr_admin')->syncPermissions(['admin.users.view']);
    $user = User::factory()->create();
    $user->assignRole('hr_admin');

    expect(Gate::forUser($user)->allows('admin.users.view'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('admin.users.create'))->toBeFalse();
});

test('changing role permissions applies on the next check', function () {
    $role = ensureRole('hr_admin');
    $user = User::factory()->create();
    $user->assignRole('hr_admin');

    expect($user->hasPermission('admin.users.create'))->toBeFalse();

    $role->syncPermissions(['admin.users.create']);

    expect($user->hasPermission('admin.users.create'))->toBeTrue();
});

test('deactivating a role revokes its permissions on the next check', function () {
    $role = ensureRole('hr_admin');
    $role->syncPermissions(['admin.users.view']);
    $user = User::factory()->create();
    $user->assignRole('hr_admin');

    expect($user->hasPermission('admin.users.view'))->toBeTrue();

    $role->update(['is_active' => false]);

    expect($user->hasPermission('admin.users.view'))->toBeFalse();
});

test('role and permission changes are audited', function () {
    ensureRole('hr_admin');
    $user = User::factory()->create();

    $user->syncRoles(['hr_admin']);

    $entry = AuditLog::query()->where('auditable_type', 'user')->where('event', 'updated')->latest('id')->first();

    expect($entry->old_values)->toBe(['roles' => []])
        ->and($entry->new_values)->toBe(['roles' => ['hr_admin']]);
});

test('unknown role codes are rejected', function () {
    User::factory()->create()->syncRoles(['hr_admn']);
})->throws(InvalidArgumentException::class);

test('unknown permission names are rejected', function () {
    ensureRole('hr_admin')->syncPermissions(['admin.user.view']);
})->throws(InvalidArgumentException::class);

test('can middleware uses permissions', function () {
    Route::middleware(['web', 'auth', 'can:admin.users.view'])->get('test/can', fn () => 'ok');

    ensureRole('hr_admin')->syncPermissions(['admin.users.view']);
    $allowed = User::factory()->create();
    $allowed->assignRole('hr_admin');

    $this->actingAs(User::factory()->create())->get('test/can')->assertForbidden();
    $this->actingAs($allowed)->get('test/can')->assertOk();
});
