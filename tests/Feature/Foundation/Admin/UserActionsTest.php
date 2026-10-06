<?php

use App\Models\User;
use App\Modules\Foundation\Actions\CreateUser;
use App\Modules\Foundation\Actions\SetUserActive;
use App\Modules\Foundation\Actions\UpdateUser;
use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\PermissionRegistrar;

beforeEach(function () {
    ensureRole('accountant');
    ensureRole(Role::SUPER_ADMIN, ['is_system' => true]);
    $this->actor = userWithPermissions('admin.users.create', 'admin.users.update', 'admin.users.deactivate');
    $this->actingAs($this->actor);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function userInput(array $overrides = []): array
{
    return [
        'name' => 'Rahim Uddin', 'username' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => '+880 1712-345678',
        'branch_id' => null, 'roles' => ['accountant'], 'permissions' => [],
        'password' => 'secret-pass', 'password_confirmation' => 'secret-pass', 'is_active' => true,
        ...$overrides,
    ];
}

test('creating a user normalises fields, forces a password change and assigns roles', function () {
    $user = app(CreateUser::class)->handle(userInput(), $this->actor);

    expect($user->username)->toBe('rahim')
        ->and($user->phone)->toBe('01712345678')
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->hasRole('accountant'))->toBeTrue();
});

test('usernames and emails must be unique ignoring case', function () {
    User::factory()->create(['username' => 'rahim', 'email' => 'rahim@example.com']);

    expectValidationError(fn () => app(CreateUser::class)->handle(userInput(['username' => 'RAHIM', 'email' => 'x@example.com']), $this->actor), 'username');
    expectValidationError(fn () => app(CreateUser::class)->handle(userInput(['username' => 'other', 'email' => 'Rahim@Example.com']), $this->actor), 'email');
});

test('phone must be a bangladeshi mobile number and at least one role is required', function () {
    expectValidationError(fn () => app(CreateUser::class)->handle(userInput(['phone' => '12345']), $this->actor), 'phone');
    expectValidationError(fn () => app(CreateUser::class)->handle(userInput(['roles' => []]), $this->actor), 'roles');
});

test('only a super admin can grant the super admin role', function () {
    expectValidationError(fn () => app(CreateUser::class)->handle(userInput(['roles' => [Role::SUPER_ADMIN]]), $this->actor), 'roles');

    $created = app(CreateUser::class)->handle(userInput(['roles' => [Role::SUPER_ADMIN]]), superAdmin());

    expect($created->hasRole(Role::SUPER_ADMIN))->toBeTrue();
});

test('the last super admin cannot lose the role (FD-BR-02)', function () {
    $onlySuperAdmin = superAdmin();

    expectValidationError(
        fn () => app(UpdateUser::class)->handle($onlySuperAdmin, userInput(['username' => $onlySuperAdmin->username, 'email' => null, 'password' => '', 'password_confirmation' => '', 'roles' => ['accountant']]), $onlySuperAdmin),
        'user',
    );
});

test('the last super admin cannot be deactivated (FD-AC-03)', function () {
    $onlySuperAdmin = superAdmin();

    expectValidationError(fn () => app(SetUserActive::class)->handle($onlySuperAdmin, false, $this->actor), 'user');
    expect($onlySuperAdmin->fresh()->is_active)->toBeTrue();
});

test('users cannot deactivate their own account', function () {
    expectValidationError(fn () => app(SetUserActive::class)->handle($this->actor, false, $this->actor), 'user');
});

test('an admin-set password forces a change at next login', function () {
    $user = User::factory()->create();

    app(UpdateUser::class)->handle($user, userInput(['username' => $user->username, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1']), $this->actor);

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('leaving the password blank on edit keeps the current one', function () {
    $user = User::factory()->create();
    $hash = $user->password;

    app(UpdateUser::class)->handle($user, userInput(['username' => $user->username, 'password' => '', 'password_confirmation' => '']), $this->actor);

    expect($user->fresh()->password)->toBe($hash)->and($user->fresh()->must_change_password)->toBeFalse();
});

test('changing a username after first login is audited (FD-BR-01)', function () {
    $user = User::factory()->create(['username' => 'oldname', 'last_login_at' => now()]);

    app(UpdateUser::class)->handle($user, userInput(['username' => 'newname', 'password' => '', 'password_confirmation' => '']), $this->actor);

    $entry = AuditLog::query()->where('auditable_type', 'user')->where('auditable_id', $user->id)->where('event', 'updated')->latest('id')->get()->first(fn (AuditLog $log) => isset($log->new_values['username']));
    expect($entry->old_values['username'])->toBe('oldname')->and($entry->new_values['username'])->toBe('newname');
});

test('only a super admin can update or deactivate a super admin account', function () {
    $target = superAdmin();
    superAdmin();

    expectValidationError(fn () => app(UpdateUser::class)->handle($target, userInput(['username' => $target->username, 'email' => null, 'name' => 'Changed', 'password' => '', 'password_confirmation' => '', 'roles' => [Role::SUPER_ADMIN]]), $this->actor), 'user');
    expectValidationError(fn () => app(SetUserActive::class)->handle($target, false, $this->actor), 'user');
    expect($target->fresh()->is_active)->toBeTrue();
});

test('a non super admin cannot remove the super admin role from another user', function () {
    $target = superAdmin();
    superAdmin();

    expectValidationError(fn () => app(UpdateUser::class)->handle($target, userInput(['username' => $target->username, 'email' => null, 'password' => '', 'password_confirmation' => '', 'roles' => ['accountant']]), $this->actor), 'user');
});

test('a non super admin can only grant direct permissions they hold', function () {
    createPermissions('admin.roles.delete');

    $created = app(CreateUser::class)->handle(userInput(['username' => 'granted', 'email' => 'g@example.com', 'permissions' => ['admin.users.create']]), $this->actor);
    expect($created->hasPermission('admin.users.create'))->toBeTrue();

    expectValidationError(fn () => app(CreateUser::class)->handle(userInput(['username' => 'denied', 'email' => 'd@example.com', 'permissions' => ['admin.roles.delete']]), $this->actor), 'permissions');

    $byAdmin = app(CreateUser::class)->handle(userInput(['username' => 'byadmin', 'email' => 'b@example.com', 'permissions' => ['admin.roles.delete']]), superAdmin());
    expect($byAdmin->hasPermission('admin.roles.delete'))->toBeTrue();
});

test('users can be deactivated through update and reactivated', function () {
    $user = User::factory()->create();

    app(UpdateUser::class)->handle($user, userInput(['username' => $user->username, 'password' => '', 'password_confirmation' => '', 'is_active' => false]), $this->actor);
    expect($user->fresh()->is_active)->toBeFalse();

    app(SetUserActive::class)->handle($user->fresh(), true, $this->actor);
    expect($user->fresh()->is_active)->toBeTrue();
});

test('a non super admin cannot change their own access', function () {
    $this->actor->syncRoles(['accountant']);

    expectValidationError(fn () => app(UpdateUser::class)->handle($this->actor, userInput([
        'username' => $this->actor->username, 'email' => null, 'password' => null, 'roles' => ['accountant'], 'permissions' => ['admin.users.create'],
    ]), $this->actor), 'roles');

    expect($this->actor->fresh()->directPermissions()->pluck('name')->all())->not->toBe(['admin.users.create']);
});

test('a non super admin can only assign roles whose permissions they hold', function () {
    createPermissions('sales.quotes.approve');
    ensureRole('management')->syncPermissions(['sales.quotes.approve']);

    expectValidationError(fn () => app(CreateUser::class)->handle(userInput(['roles' => ['management']]), $this->actor), 'roles');

    $plain = User::factory()->create();
    $plain->syncRoles(['accountant']);

    expectValidationError(fn () => app(UpdateUser::class)->handle($plain, userInput([
        'username' => $plain->username, 'email' => null, 'password' => null, 'roles' => ['accountant', 'management'],
    ]), $this->actor), 'roles');

    expect($plain->fresh()->hasRole('management'))->toBeFalse();
});

test('a non super admin cannot change an account with more access than they have', function () {
    createPermissions('sales.quotes.approve');
    $stronger = userWithPermissions('sales.quotes.approve');
    $stronger->syncRoles(['accountant']);

    expectValidationError(fn () => app(UpdateUser::class)->handle($stronger, userInput([
        'username' => $stronger->username, 'email' => null, 'password' => null, 'name' => 'Changed', 'permissions' => ['sales.quotes.approve'],
    ]), $this->actor), 'user');

    expect($stronger->fresh()->name)->not->toBe('Changed');
});

test('an admin can create a user in a role whose permissions they hold', function () {
    createPermissions('accounting.vouchers.view');
    ensureRole('accountant')->syncPermissions(['accounting.vouchers.view']);
    $this->actor->syncDirectPermissions(['admin.users.create', 'admin.users.update', 'admin.users.deactivate', 'accounting.vouchers.view']);
    app(PermissionRegistrar::class)->forget($this->actor);

    $user = app(CreateUser::class)->handle(userInput(), $this->actor);

    expect($user->hasRole('accountant'))->toBeTrue();
});

test('super admins are not limited by the delegation rules', function () {
    createPermissions('sales.quotes.approve');
    ensureRole('management')->syncPermissions(['sales.quotes.approve']);
    $super = superAdmin();
    $stronger = userWithPermissions('sales.quotes.approve');

    app(UpdateUser::class)->handle($stronger, userInput([
        'username' => $stronger->username, 'email' => null, 'password' => null, 'roles' => ['management'], 'permissions' => ['sales.quotes.approve'],
    ]), $super);

    app(UpdateUser::class)->handle($super, userInput([
        'username' => $super->username, 'email' => null, 'password' => null, 'roles' => [Role::SUPER_ADMIN, 'management'],
    ]), $super);

    expect($stronger->fresh()->hasRole('management'))->toBeTrue()
        ->and($super->fresh()->hasRole('management'))->toBeTrue();
});
