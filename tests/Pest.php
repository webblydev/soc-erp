<?php

use App\Models\User;
use App\Modules\Crm\Models\LeadPriority;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use Database\Seeders\Crm\CrmSeeder;
use Database\Seeders\Foundation\NumberSequenceFormatSeeder;
use Database\Seeders\Foundation\PermissionSeeder;
use Database\Seeders\Foundation\RolePermissionSeeder;
use Database\Seeders\Foundation\RoleSeeder;
use Database\Seeders\Foundation\SettingSeeder;
use Database\Seeders\Hrm\HrmSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

/**
 * Seed the CRM lookups and settings, the Foundation settings and the number formats.
 */
function seedCrm(): void
{
    test()->seed([SettingSeeder::class, NumberSequenceFormatSeeder::class, CrmSeeder::class]);
}

/**
 * Seed the HRM lookups, the Foundation settings and the number formats.
 */
function seedHrm(): void
{
    test()->seed([SettingSeeder::class, NumberSequenceFormatSeeder::class, HrmSeeder::class]);
}

/**
 * Valid CreateLead input for CRM tests. Needs $this->actor, $this->design and $this->survey.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function leadInput(array $overrides = []): array
{
    return [
        'lead_date' => today()->toDateString(), 'name' => 'Rahim Uddin', 'company_name' => null,
        'phone' => '+880 1711-000000', 'whatsapp' => null, 'office_phone' => null, 'email' => null, 'address' => null, 'location_id' => null,
        'lead_source_id' => LeadSource::idFor('F2F'), 'referrer_type' => null, 'referrer_id' => null, 'referrer_name' => null,
        'business_line_id' => null, 'lead_level_id' => null, 'lead_priority_id' => LeadPriority::idFor('NORMAL'),
        'expected_value' => null, 'expected_value_manual' => false, 'expected_close_date' => null,
        'site_location_text' => null, 'land_area' => '5 katha', 'floors_planned' => 6, 'notes' => null,
        'services' => [['service_id' => test()->design->id, 'estimated_value' => '150000', 'notes' => null], ['service_id' => test()->survey->id, 'estimated_value' => '20,000', 'notes' => null]],
        'assigned_to' => test()->actor->id, 'sales_team_id' => null, 'follow_up' => null, 'duplicate_reason' => null,
        ...$overrides,
    ];
}

/**
 * Assert the callback throws a ValidationException with an error on the given key.
 */
function expectValidationError(Closure $callback, string $key): void
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($key);

        return;
    }

    test()->fail("Expected a validation error on [{$key}].");
}
