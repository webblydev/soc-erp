<?php

use App\Models\User;
use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use Database\Seeders\Foundation\PermissionSeeder;
use Database\Seeders\Foundation\RolePermissionSeeder;
use Database\Seeders\Foundation\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Isolated');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createPermissions(string ...$names): void
{
    foreach ($names as $name) {
        $parts = explode('.', $name);

        Permission::query()->firstOrCreate(['name' => $name], [
            'module' => $parts[0],
            'resource' => count($parts) === 3 ? $parts[1] : '',
            'action' => end($parts),
        ]);
    }
}

/**
 * @param  array<string, mixed>  $attributes
 */
function ensureRole(string $code, array $attributes = []): Role
{
    return Role::query()->firstOrCreate(
        ['code' => $code],
        ['name' => Str::headline($code), ...$attributes],
    );
}

/**
 * A user holding exactly the given direct permissions (created if missing).
 */
function userWithPermissions(string ...$permissions): User
{
    createPermissions(...$permissions);
    $user = User::factory()->create();
    $user->syncDirectPermissions(array_values($permissions));

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function superAdmin(array $attributes = []): User
{
    ensureRole(Role::SUPER_ADMIN, ['is_system' => true]);
    $user = User::factory()->create($attributes);
    $user->syncRoles([Role::SUPER_ADMIN]);

    return $user;
}

/**
 * Seed every module permission, the system roles and their default grants.
 */
function seedAccessControl(): void
{
    test()->seed([PermissionSeeder::class, RoleSeeder::class, RolePermissionSeeder::class]);
}
