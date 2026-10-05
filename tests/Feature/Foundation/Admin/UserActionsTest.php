<?php

use App\Models\User;
use App\Modules\Foundation\Actions\CreateUser;
use App\Modules\Foundation\Actions\SetUserActive;
use App\Modules\Foundation\Actions\UpdateUser;
use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\Role;

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
