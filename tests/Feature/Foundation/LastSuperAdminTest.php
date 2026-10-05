<?php

use App\Models\User;
use App\Modules\Foundation\Actions\EnsureNotLastSuperAdmin;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => ensureRole('super_admin'));

function superAdmin(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole('super_admin');

    return $user;
}

test('the last active super admin is protected', function () {
    app(EnsureNotLastSuperAdmin::class)->handle(superAdmin());
})->throws(ValidationException::class);

test('a super admin can be changed while another active one exists', function () {
    $first = superAdmin();
    superAdmin();

    app(EnsureNotLastSuperAdmin::class)->handle($first);

    expect(true)->toBeTrue();
});

test('an inactive second super admin does not count', function () {
    $first = superAdmin();
    superAdmin(['is_active' => false]);

    app(EnsureNotLastSuperAdmin::class)->handle($first);
})->throws(ValidationException::class);

test('users who are not super admins are never blocked', function () {
    superAdmin();

    app(EnsureNotLastSuperAdmin::class)->handle(User::factory()->create());

    expect(true)->toBeTrue();
});
