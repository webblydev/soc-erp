# Admin Screens Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the doc 01 §5 administration screens (users, roles matrix, master data, locations, company, settings, number sequences, audit log, login history, profile with 2FA and notification preferences, impersonation) on top of the foundation core.

**Architecture:**
- Class-based Livewire components live in `app/Modules/Foundation/Livewire`. Each one authorizes, then calls an Action in `app/Modules/Foundation/Actions`, which validates its input and enforces the business rules.
- Components and Actions never hold each other's logic.
- Three shared pieces serve every list and form now and are reused by CRM later:
  - the `WithListing` trait (search, filters, sort, paging, mobile load-more);
  - the `x-shell.list`, `x-shell.form-page` and `x-shell.sheet` Blade components (desktop table / mobile list rows, sticky mobile action bar, right/bottom sheet);
  - `ListingExport` (Excel download plus audit).

**Tech Stack:** PHP 8.4, Laravel 13, Livewire 4 (class-based components, `wire:sort`), Fortify (2FA), BlatUI `x-ui.*`, Tailwind v4, Maatwebsite Excel 4, Pest 4 on in-memory SQLite.

**Spec:** `docs/superpowers/specs/2026-10-06-admin-screens-design.md` (read it with this plan). The foundation it builds on: `docs/superpowers/specs/2026-10-05-foundation-core-design.md`.

## Global Constraints

- **Branch:** work on `admin-screens`. Commit per file, with a concise subject plus a short body when needed. **Never** add a `Co-Authored-By` line or a "Generated with Claude Code" footer to commits.
- **Rules to read first:** `.ai/rules/modules.md`. Feature code goes in `app/Modules/Foundation/`, shared helpers in `app/Support/`, migrations in `database/migrations/foundation/`, seeders in `database/seeders/Foundation/`, and routes in `routes/modules/foundation.php`.
- **Where logic lives:** business logic belongs in Action classes, never in Livewire components. Every Action that writes runs inside `DB::transaction()`.
- **UI components:**
  - Build UI only with BlatUI `x-ui.*` components. Install missing ones with `php artisan blatui:add`.
  - Light mode only: no `dark:` classes and no theme toggles.
- **Mobile (below `md`), on every screen:**
  - fixed top bar and bottom nav (forms hide the bottom nav and show a sticky action bar instead);
  - `x-ui.item` rows instead of tables;
  - bottom sheets for overlays;
  - tap targets of at least 44×44 px (`h-11`/`size-11`);
  - text of at least 14 px (`text-sm` minimum; inputs `text-base`);
  - `wire:navigate` on every in-app link;
  - `env(safe-area-inset-*)` padding on fixed bars.
- **Route keys:** use the model's code instead of its numeric id: `users/{user:username}`, `roles/{role:code}`.
- **Permissions:** every admin route uses `can:admin.<resource>.<action>` middleware, and every Livewire action calls `$this->authorize(...)` again.
- **Lookup colours** are BlatUI badge tones: `neutral`, `info`, `success`, `warning`, `danger`.
- **2FA:** `general.require_2fa_roles` is seeded empty (`[]`), with type `roles`.
- **Out of scope** (spec D10): admin password reset, purging sessions on deactivation, and all emails.
- **Testing:**
  - Use Pest feature tests under `tests/Feature/Foundation/Admin/`. Create files with `php artisan make:test --pest Foundation/Admin/<Name>Test --no-interaction` when a new file is needed.
  - Run the narrowest set with `php artisan test --compact <path>`.
  - Do not delete existing tests. Rewrite them in place when the behaviour they cover changes.
  - Never run browser or headless tests.
- **Before each commit:** run `vendor/bin/pint --dirty --format agent` after PHP edits.

## Review Focus

Inputs the spec implies but no screen test would naturally hit. Each line has a test in the named task.

1. **SQL wildcards in search.** Searching for `50%` or `a_b` must match those characters literally, not act as wildcards (Task 6 `WithListing` test).
2. **Tampered URL state.** A hand-edited `?sort=password&direction=sideways&perPage=9999` must be ignored (fall back to the default order and 25 per page), never cause an SQL error or a huge page (Task 6).
3. **Livewire actions called directly without permission.** A user who can view users but lacks `admin.users.deactivate` calls `toggleActive` from the console and gets 403 (Task 8). The same applies to role deletion (Task 9).
4. **Deactivating your own account.** An admin deactivating themselves would lock themselves out mid-session; it must be refused with a clear message (Task 7).
5. **Reorder with a foreign or stale id.** A `wire:sort` call carrying an id from another lookup table, or a position past the end, must not corrupt `sort_order` (Task 10).

---

## File Structure

**Create**

| Path | Responsibility |
|---|---|
| `app/Support/Listing/WithListing.php` | Search/filter/sort/paging state and query building for list components |
| `app/Support/Exports/QueryExport.php` | Generic Maatwebsite export from a query and a column map |
| `app/Support/Exports/ListingExport.php` | Download a `QueryExport` and record an `exported` audit row |
| `resources/views/components/shell/list.blade.php` | List chrome: toolbar, filter popover/drawer, mobile rows, infinite scroll, FAB |
| `resources/views/components/shell/sort-header.blade.php` | Sortable table header button |
| `resources/views/components/shell/form-page.blade.php` | Form card (desktop) / full-screen form with sticky action bar (mobile) |
| `resources/views/components/shell/sheet.blade.php` | Right sheet on desktop, bottom sheet on mobile |
| `resources/views/components/shell/impersonation-banner.blade.php` | "Signed in as" banner |
| `resources/views/components/print/letterhead.blade.php` | Company letterhead for prints |
| `routes/modules/foundation.php` | Admin, profile and 2FA setup routes |
| `config/notifications.php` | Notification key registry |
| `app/Modules/Foundation/Concerns/ValidatesUserInput.php` | Shared user validation rules |
| `app/Modules/Foundation/Actions/{CreateUser,UpdateUser,SetUserActive,SyncUserAccess,SaveRole,DeleteRole,SaveLookup,DeleteLookup,ReorderLookup,SaveLocation,SetLocationActive,UpdateCompanyProfile,UpdateSettings,UpdateSequenceFormat,IncreaseSequenceNumber,SaveNotificationPreferences,StartImpersonation,StopImpersonation,UpdateProfile}.php` | Business actions |
| `app/Modules/Foundation/Models/{Location,LocationLevel,NotificationPreference}.php` | New models |
| `app/Modules/Foundation/Services/TwoFactorPolicy.php` | Whether a user must use 2FA |
| `app/Modules/Foundation/Livewire/Admin/Users/{Index,Form}.php`, `Roles/{Index,Form}.php`, `MasterData.php`, `Locations.php`, `Company.php`, `Settings.php`, `Sequences.php`, `AuditLog.php`, `LoginHistory.php` | Admin screens |
| `app/Modules/Foundation/Livewire/Profile/{Edit,TwoFactor,TwoFactorSetup}.php` | Profile page, 2FA panel, forced setup page |
| `app/Http/Middleware/{EnsureTwoFactorEnabled,HandleImpersonation}.php` | 2FA enforcement, impersonation guard and banner data |
| `database/migrations/foundation/2026_10_06_*_create_location_tables.php`, `..._create_notification_preferences_table.php` | New tables |
| `database/seeders/Foundation/{LocationLevelSeeder,LocationSeeder}.php`, `database/seeders/Foundation/data/locations.php` | Location seed |
| `resources/views/livewire/admin/**`, `resources/views/livewire/profile/**` | Screen views |
| `resources/views/pages/auth/two-factor-challenge.blade.php` | Fortify 2FA challenge view |
| `tests/Fixtures/ListingFixture.php` | Livewire fixture for `WithListing` tests |

**Modify**

| Path | Change |
|---|---|
| `app/Models/User.php` | Normalising mutators, `branch()`, `TwoFactorAuthenticatable`, QR label |
| `app/Concerns/PasswordValidationRules.php`, `app/Providers/AppServiceProvider.php` | Password minimum from settings; morph aliases |
| `app/Support/Lookups/LookupRegistry.php`, `app/Support/Facades/Lookup.php`, `config/lookups.php` | Typed registry, permissions per table |
| `app/Support/Settings/SettingsRepository.php`, `database/seeders/Foundation/SettingSeeder.php` | `roles` type |
| `app/Modules/Foundation/permissions.php` | `admin.branches.deactivate` |
| `app/Modules/Foundation/Listeners/RecordAuthenticationAudit.php`, `app/Modules/Foundation/Actions/AuthenticateUser.php` | Skip while impersonating |
| `app/Http/Middleware/EnsurePasswordChanged.php`, `bootstrap/app.php` | Middleware wiring |
| `app/Providers/FortifyServiceProvider.php`, `config/fortify.php` | 2FA feature and challenge view |
| `config/navigation.php` | Admin items |
| `resources/views/layouts/app.blade.php`, `layouts/app/sidebar.blade.php`, `components/shell/mobile-bottom-nav.blade.php`, `components/desktop-user-menu.blade.php` | `bottomNav` flag, banner, profile links |
| `routes/web.php`, `routes/settings.php` | Require module routes; settings redirect to profile |
| `database/factories/UserFactory.php` | Working `withTwoFactor()` state |
| `tests/Pest.php` | Access-control helpers |
| `tests/Feature/Settings/*`, `tests/Feature/Foundation/SettingsTest.php`, `tests/Feature/Foundation/LookupTest.php` | Updated expectations |

---

### Task 1: Password minimum from settings and normalised user fields (spec D9)

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (`configureDefaults`)
- Modify: `app/Concerns/PasswordValidationRules.php`
- Modify: `resources/views/pages/auth/⚡change-password.blade.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Foundation/Admin/UserNormalisationTest.php`, `tests/Feature/Auth/ChangePasswordTest.php`

**Interfaces:**
- Produces:
  - `Password::default()` enforces `general.password_min_length` in every environment.
  - `User` setters lowercase and trim `username`/`email`, store an empty email as `null`, and normalise `phone` to `01XXXXXXXXX`.
  - `User::normalisePhone(?string $phone): ?string` (public static).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/UserNormalisationTest.php`:

```php
<?php

use App\Models\User;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

test('usernames and emails are stored trimmed and lowercase', function () {
    $user = User::factory()->create(['username' => '  Rahim.BD ', 'email' => ' Rahim@Example.COM ']);

    expect($user->fresh()->username)->toBe('rahim.bd')
        ->and($user->fresh()->email)->toBe('rahim@example.com');
});

test('a blank email is stored as null', function () {
    expect(User::factory()->create(['email' => '  '])->fresh()->email)->toBeNull();
});

test('bangladeshi mobile numbers are normalised to the local 01 form', function (string $input) {
    expect(User::factory()->create(['phone' => $input])->fresh()->phone)->toBe('01712345678');
})->with(['+8801712345678', '8801712345678', '01712-345678', '0171 234 5678']);

test('the default password rule uses the minimum length setting', function () {
    $this->seed(SettingSeeder::class);
    Settings::set('general.password_min_length', 12);

    expect(Validator::make(['password' => 'short-pass1'], ['password' => Password::default()])->fails())->toBeTrue()
        ->and(Validator::make(['password' => 'long-enough-pass'], ['password' => Password::default()])->fails())->toBeFalse();
});
```

Add this case to `tests/Feature/Auth/ChangePasswordTest.php` (keep the file's existing imports; add `use App\Support\Facades\Settings;`, `use Database\Seeders\Foundation\SettingSeeder;` and `use Livewire\Livewire;` if missing):

```php
test('the forced change form enforces the minimum length setting', function () {
    $this->seed(SettingSeeder::class);
    Settings::set('general.password_min_length', 12);
    $user = User::factory()->mustChangePassword()->create();

    Livewire::actingAs($user)
        ->test('pages::auth.change-password')
        ->set('current_password', 'password')
        ->set('password', 'elevenchars')
        ->set('password_confirmation', 'elevenchars')
        ->call('save')
        ->assertHasErrors(['password']);
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/UserNormalisationTest.php tests/Feature/Auth/ChangePasswordTest.php`
Expected: FAIL. The username is stored with capitals, the phone is unchanged, and `Password::default()` is `null` outside production.

- [ ] **Step 3: Make `Password::default()` read the setting**

In `app/Providers/AppServiceProvider.php`, add `use App\Support\Facades\Settings;` and replace the `Password::defaults(...)` call in `configureDefaults()` with:

```php
        Password::defaults(function (): Password {
            $rule = Password::min((int) Settings::get('general.password_min_length', 8));

            return app()->isProduction()
                ? $rule->letters()->mixedCase()->numbers()->uncompromised()
                : $rule;
        });
```

In `app/Concerns/PasswordValidationRules.php`, keep `passwordRules()` as it is (it already uses `Password::default()`).

In `resources/views/pages/auth/⚡change-password.blade.php`:
- replace `Password::min((int) Settings::get('general.password_min_length', 8))` with `Password::default()`;
- remove the now-unused `use App\Support\Facades\Settings;` import.

- [ ] **Step 4: Add the `User` mutators**

In `app/Models/User.php`, add the imports `use Illuminate\Database\Eloquent\Casts\Attribute;`. Then add:

```php
    /**
     * Usernames are unique case-insensitively (FD-BR-01), so they are stored lowercase.
     *
     * @return Attribute<string, string>
     */
    protected function username(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => Str::lower(trim($value)));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => filled($value) ? Str::lower(trim($value)) : null);
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => self::normalisePhone($value));
    }

    /**
     * Strip spaces and dashes and turn +880 / 880 prefixes into the local 0 form.
     */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/[\s\-()]/', '', (string) $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        return (string) preg_replace('/^\+?880(?=1)/', '0', $digits);
    }
```

- [ ] **Step 5: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/UserNormalisationTest.php tests/Feature/Auth tests/Feature/Settings`
Expected: PASS.

- [ ] **Step 6: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Providers/AppServiceProvider.php && git commit -m "Read the password minimum from settings in every environment"
git add "resources/views/pages/auth/⚡change-password.blade.php" && git commit -m "Use the default password rule on the forced change page"
git add app/Models/User.php && git commit -m "Normalise username, email and phone when saving users"
git add tests/Feature/Foundation/Admin/UserNormalisationTest.php && git commit -m "Test user field normalisation and password minimum"
git add tests/Feature/Auth/ChangePasswordTest.php && git commit -m "Test the forced change form against the minimum length setting"
```

---

### Task 2: Typed lookup registry and branch deactivate permission

**Files:**
- Modify: `config/lookups.php`
- Modify: `app/Support/Lookups/LookupRegistry.php`
- Modify: `app/Support/Facades/Lookup.php`
- Modify: `app/Modules/Foundation/permissions.php`
- Test: `tests/Feature/Foundation/LookupTest.php`

**Interfaces:**
- Produces:
  - A registry entry has the shape `array{label: string, module: string, model: class-string<Model>, permission: string, extra_fields: array<string, array{type: 'text'|'textarea'|'number'|'bool', label: string}>, single_flags?: list<string>}`.
  - `LookupRegistry::visibleTo(User $user): array<string, Entry>` returns the tables whose `{permission}.view` the user holds, in config order.
  - `LookupRegistry::allows(User $user, string $table, string $action): bool` checks `{permission}.{action}`, where action is one of view|create|update|deactivate.
  - `LookupRegistry::modelFor(string $table): Model` returns a fresh instance of the entry's model.
  - `LookupRegistry::get()` keeps throwing `InvalidArgumentException` for an unknown table.
  - The `admin.branches.deactivate` permission exists.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Foundation/LookupTest.php` (add `use App\Models\User;`, `use App\Modules\Foundation\Models\Branch;` and `use App\Support\Lookups\LookupRegistry;` if missing):

```php
test('the registry lists only tables the user may view', function () {
    $user = userWithPermissions('admin.branches.view');

    expect(array_keys(app(LookupRegistry::class)->visibleTo($user)))->toBe(['branches']);
});

test('table actions are checked against the table permission prefix', function () {
    $user = userWithPermissions('admin.master_data.view', 'admin.master_data.update');
    $registry = app(LookupRegistry::class);

    expect($registry->allows($user, 'currencies', 'update'))->toBeTrue()
        ->and($registry->allows($user, 'currencies', 'deactivate'))->toBeFalse()
        ->and($registry->allows($user, 'branches', 'view'))->toBeFalse();
});

test('every registered table names its model and typed extra fields', function () {
    $registry = app(LookupRegistry::class);

    foreach ($registry->all() as $table => $entry) {
        expect($registry->modelFor($table)->getTable())->toBe($table);

        foreach ($entry['extra_fields'] as $field) {
            expect($field['type'])->toBeIn(['text', 'textarea', 'number', 'bool']);
        }
    }

    expect($registry->modelFor('branches'))->toBeInstanceOf(Branch::class);
});
```

Add these helpers to `tests/Pest.php` (with `use App\Models\User;`):

```php
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

function superAdmin(): User
{
    ensureRole(Role::SUPER_ADMIN, ['is_system' => true]);
    $user = User::factory()->create();
    $user->syncRoles([Role::SUPER_ADMIN]);

    return $user;
}
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/LookupTest.php`
Expected: FAIL with "Call to undefined method ...visibleTo()".

- [ ] **Step 3: Type the registry config**

Replace `config/lookups.php`:

```php
<?php

use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Currency;

/*
| Registry of lookup tables edited through the generic Master Data screen (docs/01 §5.8).
| Each module appends its own tables. permission is a prefix: {prefix}.view|create|update|deactivate.
| extra_fields: column => [type (text|textarea|number|bool), label]. single_flags: bool columns only one row may hold.
*/

return [
    'branches' => [
        'label' => 'Branches',
        'module' => 'admin',
        'model' => Branch::class,
        'permission' => 'admin.branches',
        'extra_fields' => [
            'address' => ['type' => 'textarea', 'label' => 'Address'],
            'phone' => ['type' => 'text', 'label' => 'Phone'],
            'is_head_office' => ['type' => 'bool', 'label' => 'Head office'],
        ],
        'single_flags' => ['is_head_office'],
    ],
    'currencies' => [
        'label' => 'Currencies',
        'module' => 'admin',
        'model' => Currency::class,
        'permission' => 'admin.master_data',
        'extra_fields' => [
            'symbol' => ['type' => 'text', 'label' => 'Symbol'],
            'decimal_places' => ['type' => 'number', 'label' => 'Decimal places'],
            'is_base' => ['type' => 'bool', 'label' => 'Base currency'],
        ],
        'single_flags' => ['is_base'],
    ],
];
```

- [ ] **Step 4: Extend the registry**

In `app/Support/Lookups/LookupRegistry.php`:
- add the imports `use App\Models\User;` and `use Illuminate\Database\Eloquent\Model;`;
- add a `@phpstan-type` on the class docblock and use it everywhere the old array shape appears:

```php
/**
 * @phpstan-type LookupEntry array{label: string, module: string, model: class-string<Model>, permission: string, extra_fields: array<string, array{type: string, label: string}>, single_flags?: list<string>}
 */
final class LookupRegistry
{
    /**
     * @param  array<string, LookupEntry>  $tables
     */
    public function __construct(private array $tables) {}
```

Change the `all()` and `get()` return types to `array<string, LookupEntry>` and `LookupEntry`. Then add:

```php
    /**
     * Tables the user may open in Master Data, in registry order.
     *
     * @return array<string, LookupEntry>
     */
    public function visibleTo(User $user): array
    {
        return array_filter($this->tables, fn (array $entry): bool => $user->can($entry['permission'].'.view'));
    }

    public function allows(User $user, string $table, string $action): bool
    {
        return $user->can($this->get($table)['permission'].'.'.$action);
    }

    public function modelFor(string $table): Model
    {
        $class = $this->get($table)['model'];

        return new $class;
    }
```

In `app/Support/Facades/Lookup.php`, update the `@method` lines to the new shape and add:

```php
 * @method static array<string, array<string, mixed>> visibleTo(\App\Models\User $user)
 * @method static bool allows(\App\Models\User $user, string $table, string $action)
 * @method static \Illuminate\Database\Eloquent\Model modelFor(string $table)
```

- [ ] **Step 5: Add the permission**

In `app/Modules/Foundation/permissions.php`, change `'branches' => ['view', 'create', 'update'],` to `'branches' => ['view', 'create', 'update', 'deactivate'],`.

- [ ] **Step 6: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/LookupTest.php tests/Feature/Foundation/FoundationSeederTest.php tests/Feature/Foundation/PermissionManifestTest.php`
Expected: PASS. If a seeder test counts permissions, update the expected count by +1.

- [ ] **Step 7: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add config/lookups.php && git commit -m "Name models and type extra fields in the lookup registry"
git add app/Support/Lookups/LookupRegistry.php && git commit -m "Filter lookup tables by permission and resolve their models"
git add app/Support/Facades/Lookup.php && git commit -m "Document new lookup registry methods on the facade"
git add app/Modules/Foundation/permissions.php && git commit -m "Add branch deactivate permission"
git add tests/Pest.php && git commit -m "Add permission and super admin test helpers"
git add tests/Feature/Foundation/LookupTest.php && git commit -m "Test lookup registry permissions and models"
```

(Also commit any seeder-test count change on its own.)

---

### Task 3: Locations data model, seed and `SaveLocation`

**Files:**
- Create: `database/migrations/foundation/2026_10_06_100000_create_location_tables.php`
- Create: `app/Modules/Foundation/Models/LocationLevel.php`, `app/Modules/Foundation/Models/Location.php`
- Create: `app/Modules/Foundation/Actions/SaveLocation.php`, `app/Modules/Foundation/Actions/SetLocationActive.php`
- Create: `database/seeders/Foundation/LocationLevelSeeder.php`, `database/seeders/Foundation/LocationSeeder.php`, `database/seeders/Foundation/data/locations.php`
- Modify: `database/seeders/Foundation/FoundationSeeder.php`, `config/lookups.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Foundation/Admin/LocationActionsTest.php`

**Interfaces:**
- Produces:
  - `LocationLevel` constants `DIVISION = 'division'`, `DISTRICT = 'district'`, `THANA = 'thana'`, `AREA = 'area'`, and `LocationLevel::ORDER = [division, district, thana, area]`.
  - `Location` relations: `parent()`, `children()`, `level()`. `Location::PATH_SEPARATOR = ' › '`.
  - `SaveLocation::handle(array $input, ?Location $location = null): Location`. Input keys: `name`, `name_bn`, `parent_id` (the parent is only read on create, or when the key is present on update).
  - `SetLocationActive::handle(Location $location, bool $active): void`.
  - Morph aliases `location` and `location_level`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/LocationActionsTest.php`:

```php
<?php

use App\Modules\Foundation\Actions\SaveLocation;
use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use Database\Seeders\Foundation\LocationLevelSeeder;
use Database\Seeders\Foundation\LocationSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->seed(LocationLevelSeeder::class));

function addLocation(string $name, ?Location $parent = null): Location
{
    return app(SaveLocation::class)->handle(['name' => $name, 'parent_id' => $parent?->id]);
}

test('a child gets the next level and a full path', function () {
    $dhaka = addLocation('Dhaka');
    $district = addLocation('Dhaka', $dhaka);
    $uttara = addLocation('Uttara', $district);
    $area = addLocation('Uttar Khan', $uttara);

    expect($dhaka->level->code)->toBe(LocationLevel::DIVISION)
        ->and($district->level->code)->toBe(LocationLevel::DISTRICT)
        ->and($area->level->code)->toBe(LocationLevel::AREA)
        ->and($area->full_path)->toBe('Dhaka › Dhaka › Uttara › Uttar Khan');
});

test('areas cannot have children', function () {
    $area = addLocation('Uttar Khan', addLocation('Uttara', addLocation('Dhaka', addLocation('Dhaka'))));

    expect(fn () => addLocation('Sector 1', $area))->toThrow(ValidationException::class);
});

test('renaming a location rebuilds the full path of every descendant (FD-AC-09)', function () {
    $district = addLocation('Dhaka', addLocation('Dhaka'));
    $uttara = addLocation('Uttara', $district);
    $first = addLocation('Uttar Khan', $uttara);
    $second = addLocation('Dakshin Khan', $uttara);

    app(SaveLocation::class)->handle(['name' => 'Uttara Model Town'], $uttara);

    expect($first->fresh()->full_path)->toBe('Dhaka › Dhaka › Uttara Model Town › Uttar Khan')
        ->and($second->fresh()->full_path)->toBe('Dhaka › Dhaka › Uttara Model Town › Dakshin Khan');
});

test('names are unique under the same parent, ignoring case', function () {
    $division = addLocation('Dhaka');
    addLocation('Gazipur', $division);

    expect(fn () => addLocation('gazipur', $division))->toThrow(ValidationException::class);
    expect(addLocation('Gazipur')->exists)->toBeTrue();
});

test('moving a location under its own descendant is refused', function () {
    $division = addLocation('Dhaka');
    $district = addLocation('Dhaka', $division);

    expect(fn () => app(SaveLocation::class)->handle(['name' => 'Dhaka', 'parent_id' => $district->id], $division))
        ->toThrow(ValidationException::class);
});

test('the location seed loads all districts and is idempotent', function () {
    $this->seed(LocationSeeder::class);
    $this->seed(LocationSeeder::class);

    $districts = Location::query()->whereHas('level', fn ($q) => $q->where('code', LocationLevel::DISTRICT))->count();

    expect(Location::query()->whereNull('parent_id')->count())->toBe(8)
        ->and($districts)->toBe(64)
        ->and(Location::query()->where('full_path', 'Dhaka › Gazipur › Kaliakair')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/LocationActionsTest.php`
Expected: FAIL with "Class ...LocationLevelSeeder not found".

- [ ] **Step 3: Write the migration**

`php artisan make:migration create_location_tables --path=database/migrations/foundation --no-interaction`, then rename the file to `2026_10_06_100000_create_location_tables.php` and write:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_levels', function (Blueprint $table) {
            $table->lookupColumns();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('location_level_id')->constrained('location_levels');
            $table->string('name', 120);
            $table->string('name_bn', 120)->nullable();
            $table->string('full_path', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['parent_id', 'name']);
            $table->index('parent_id');
        });

        // Prefix index (docs/01 §3.7); the prefix syntax is MySQL-only.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX locations_full_path_index ON locations (full_path(191))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('location_levels');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Modules/Foundation/Models/LocationLevel.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system'])]
class LocationLevel extends Model
{
    use Auditable, IsLookup;

    public const DIVISION = 'division';

    public const DISTRICT = 'district';

    public const THANA = 'thana';

    public const AREA = 'area';

    /** Top-down order of the location hierarchy (docs/01 §3.7). */
    public const ORDER = [self::DIVISION, self::DISTRICT, self::THANA, self::AREA];
}
```

`app/Modules/Foundation/Models/Location.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property int $location_level_id
 * @property string $name
 * @property string|null $name_bn
 * @property string $full_path
 * @property bool $is_active
 * @property-read LocationLevel $level
 * @property-read Location|null $parent
 */
#[Fillable(['parent_id', 'location_level_id', 'name', 'name_bn', 'full_path', 'is_active'])]
class Location extends Model
{
    use Auditable;

    public const PATH_SEPARATOR = ' › ';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id')->orderBy('name');
    }

    /**
     * @return BelongsTo<LocationLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(LocationLevel::class, 'location_level_id');
    }
}
```

In `app/Providers/AppServiceProvider.php` `configureMorphMap()`, add `'location' => Location::class,` and `'location_level' => LocationLevel::class,`, with their imports.

In `config/lookups.php`, add this entry (with `use App\Modules\Foundation\Models\LocationLevel;`):

```php
    'location_levels' => [
        'label' => 'Location levels',
        'module' => 'admin',
        'model' => LocationLevel::class,
        'permission' => 'admin.locations',
        'extra_fields' => [],
    ],
```

`admin.locations` has no `deactivate` action. Add it in `app/Modules/Foundation/permissions.php`: `'locations' => ['view', 'create', 'update', 'deactivate'],`.

- [ ] **Step 5: Write the actions**

`app/Modules/Foundation/Actions/SaveLocation.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a location. The level follows from the parent, and full_path is rebuilt
 * for the location and all its descendants whenever its name or parent changes (FD-BR-11).
 */
class SaveLocation
{
    /**
     * @param  array{name?: mixed, name_bn?: mixed, parent_id?: mixed}  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?Location $location = null): Location
    {
        /** @var array{name: string, name_bn?: string|null, parent_id?: int|null} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:locations,id'],
        ])->validate();

        $location ??= new Location;
        $parentId = array_key_exists('parent_id', $input) || ! $location->exists
            ? ($data['parent_id'] ?? null)
            : $location->parent_id;
        $parent = $parentId !== null ? Location::query()->with('level')->findOrFail($parentId) : null;

        $level = $this->levelBelow($parent);
        $this->ensureValidParent($location, $parent, $level);
        $this->ensureUniqueName($location, $parentId, $data['name']);

        return DB::transaction(function () use ($location, $data, $parentId, $parent, $level): Location {
            $pathChanged = ! $location->exists || $location->name !== $data['name'] || $location->parent_id !== $parentId;

            $location->fill([
                'name' => $data['name'],
                'name_bn' => $data['name_bn'] ?? $location->name_bn,
                'parent_id' => $parentId,
                'location_level_id' => $level->id,
                'full_path' => $this->pathFor($parent, $data['name']),
            ])->save();

            if ($pathChanged) {
                $this->rebuildDescendants($location);
            }

            return $location->load('level');
        });
    }

    private function levelBelow(?Location $parent): LocationLevel
    {
        $index = $parent === null ? 0 : array_search($parent->level->code, LocationLevel::ORDER, true) + 1;
        $code = LocationLevel::ORDER[$index] ?? null;

        if ($code === null) {
            throw ValidationException::withMessages(['parent_id' => __('Areas cannot have child locations.')]);
        }

        return LocationLevel::query()->where('code', $code)->firstOrFail();
    }

    private function ensureValidParent(Location $location, ?Location $parent, LocationLevel $level): void
    {
        if (! $location->exists || $parent === null) {
            return;
        }

        $ancestor = $parent;

        while ($ancestor !== null) {
            if ($ancestor->is($location)) {
                throw ValidationException::withMessages(['parent_id' => __('A location cannot be moved under itself.')]);
            }

            $ancestor = $ancestor->parent;
        }

        if ($location->location_level_id !== $level->id) {
            throw ValidationException::withMessages(['parent_id' => __('The new parent must be on the same level as the current one.')]);
        }
    }

    private function ensureUniqueName(Location $location, ?int $parentId, string $name): void
    {
        $taken = Location::query()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->when($location->exists, fn ($query) => $query->whereKeyNot($location->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('A location with this name already exists here.')]);
        }
    }

    private function pathFor(?Location $parent, string $name): string
    {
        return $parent === null ? $name : $parent->full_path.Location::PATH_SEPARATOR.$name;
    }

    private function rebuildDescendants(Location $location): void
    {
        foreach (Location::query()->where('parent_id', $location->id)->get() as $child) {
            $child->update(['full_path' => $this->pathFor($location, $child->name)]);
            $this->rebuildDescendants($child);
        }
    }
}
```

`where('parent_id', null)` compiles to `IS NULL` in Laravel, so top-level uniqueness works.

`app/Modules/Foundation/Actions/SetLocationActive.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Location;

class SetLocationActive
{
    public function handle(Location $location, bool $active): void
    {
        $location->update(['is_active' => $active]);
    }
}
```

- [ ] **Step 6: Write the seeders and data**

`database/seeders/Foundation/LocationLevelSeeder.php`:

```php
<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\LocationLevel;
use Illuminate\Database\Seeder;

class LocationLevelSeeder extends Seeder
{
    private const NAMES = [
        LocationLevel::DIVISION => 'Division',
        LocationLevel::DISTRICT => 'District',
        LocationLevel::THANA => 'Thana / Upazila',
        LocationLevel::AREA => 'Area / Mouza / Sector',
    ];

    public function run(): void
    {
        foreach (LocationLevel::ORDER as $index => $code) {
            LocationLevel::query()->firstOrCreate(['code' => $code], [
                'name' => self::NAMES[$code], 'sort_order' => $index + 1, 'is_system' => true,
            ]);
        }
    }
}
```

`database/seeders/Foundation/data/locations.php` lists division ⇒ district ⇒ thanas/upazilas. Only Dhaka and Gazipur districts have thanas. **The user must review this list before go-live.**

```php
<?php

/*
| Bangladesh divisions → districts → thanas/upazilas (docs/01 §4). Thanas are seeded for
| Dhaka and Gazipur only. REVIEW against the official BBS list before go-live.
*/

return [
    'Barishal' => ['Barguna' => [], 'Barishal' => [], 'Bhola' => [], 'Jhalokati' => [], 'Patuakhali' => [], 'Pirojpur' => []],
    'Chattogram' => [
        'Bandarban' => [], 'Brahmanbaria' => [], 'Chandpur' => [], 'Chattogram' => [], "Cox's Bazar" => [], 'Cumilla' => [],
        'Feni' => [], 'Khagrachhari' => [], 'Lakshmipur' => [], 'Noakhali' => [], 'Rangamati' => [],
    ],
    'Dhaka' => [
        'Dhaka' => [
            'Adabor', 'Badda', 'Banani', 'Bangshal', 'Bhashantek', 'Bhatara', 'Biman Bandar', 'Cantonment', 'Chawkbazar',
            'Dakshinkhan', 'Darus Salam', 'Demra', 'Dhamrai', 'Dhanmondi', 'Dohar', 'Gendaria', 'Gulshan', 'Hatirjheel',
            'Hazaribagh', 'Jatrabari', 'Kadamtali', 'Kafrul', 'Kalabagan', 'Kamrangirchar', 'Keraniganj', 'Khilgaon',
            'Khilkhet', 'Kotwali', 'Lalbagh', 'Mirpur', 'Mohammadpur', 'Motijheel', 'Mugda', 'Nawabganj', 'New Market',
            'Pallabi', 'Paltan', 'Ramna', 'Rampura', 'Rupnagar', 'Sabujbagh', 'Savar', 'Shah Ali', 'Shahbagh',
            'Shahjahanpur', 'Sher-e-Bangla Nagar', 'Shyampur', 'Sutrapur', 'Tejgaon', 'Tejgaon Industrial Area', 'Turag',
            'Uttara East', 'Uttara West', 'Uttar Khan', 'Vatara', 'Wari',
        ],
        'Faridpur' => [], 'Gazipur' => [
            'Gazipur Sadar', 'Kaliakair', 'Kaliganj', 'Kapasia', 'Sreepur', 'Basan', 'Gacha', 'Joydebpur', 'Kashimpur',
            'Konabari', 'Pubail', 'Tongi East', 'Tongi West',
        ],
        'Gopalganj' => [], 'Kishoreganj' => [], 'Madaripur' => [], 'Manikganj' => [], 'Munshiganj' => [],
        'Narayanganj' => [], 'Narsingdi' => [], 'Rajbari' => [], 'Shariatpur' => [], 'Tangail' => [],
    ],
    'Khulna' => [
        'Bagerhat' => [], 'Chuadanga' => [], 'Jashore' => [], 'Jhenaidah' => [], 'Khulna' => [], 'Kushtia' => [],
        'Magura' => [], 'Meherpur' => [], 'Narail' => [], 'Satkhira' => [],
    ],
    'Mymensingh' => ['Jamalpur' => [], 'Mymensingh' => [], 'Netrokona' => [], 'Sherpur' => []],
    'Rajshahi' => [
        'Bogura' => [], 'Chapainawabganj' => [], 'Joypurhat' => [], 'Naogaon' => [], 'Natore' => [], 'Pabna' => [],
        'Rajshahi' => [], 'Sirajganj' => [],
    ],
    'Rangpur' => [
        'Dinajpur' => [], 'Gaibandha' => [], 'Kurigram' => [], 'Lalmonirhat' => [], 'Nilphamari' => [], 'Panchagarh' => [],
        'Rangpur' => [], 'Thakurgaon' => [],
    ],
    'Sylhet' => ['Habiganj' => [], 'Moulvibazar' => [], 'Sunamganj' => [], 'Sylhet' => []],
];
```

`database/seeders/Foundation/LocationSeeder.php` inserts directly, without the Action, so there are no per-row audit entries:

```php
<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LocationLevelSeeder::class);

        $levels = LocationLevel::query()->pluck('id', 'code');

        /** @var array<string, array<string, list<string>>> $divisions */
        $divisions = require database_path('seeders/Foundation/data/locations.php');

        foreach ($divisions as $divisionName => $districts) {
            $division = $this->place(null, $divisionName, (int) $levels[LocationLevel::DIVISION]);

            foreach ($districts as $districtName => $thanas) {
                $district = $this->place($division, $districtName, (int) $levels[LocationLevel::DISTRICT]);

                foreach ($thanas as $thanaName) {
                    $this->place($district, $thanaName, (int) $levels[LocationLevel::THANA]);
                }
            }
        }
    }

    private function place(?Location $parent, string $name, int $levelId): Location
    {
        return Location::withoutEvents(fn (): Location => Location::query()->firstOrCreate(
            ['parent_id' => $parent?->id, 'name' => $name],
            [
                'location_level_id' => $levelId,
                'full_path' => $parent === null ? $name : $parent->full_path.Location::PATH_SEPARATOR.$name,
            ],
        ));
    }
}
```

In `database/seeders/Foundation/FoundationSeeder.php`, add `LocationSeeder::class` after `NumberSequenceFormatSeeder::class`.

- [ ] **Step 7: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/LocationActionsTest.php tests/Feature/Foundation/FoundationSeederTest.php`
Expected: PASS.

- [ ] **Step 8: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/foundation/2026_10_06_100000_create_location_tables.php && git commit -m "Add location levels and locations tables"
git add app/Modules/Foundation/Models/LocationLevel.php && git commit -m "Add location level lookup model"
git add app/Modules/Foundation/Models/Location.php && git commit -m "Add location model"
git add app/Providers/AppServiceProvider.php && git commit -m "Register location morph aliases"
git add config/lookups.php && git commit -m "Register location levels in master data"
git add app/Modules/Foundation/permissions.php && git commit -m "Add location deactivate permission"
git add app/Modules/Foundation/Actions/SaveLocation.php && git commit -m "Save locations and rebuild descendant paths" -m "Covers FD-BR-11: the level follows the parent and full_path cascades on rename or move."
git add app/Modules/Foundation/Actions/SetLocationActive.php && git commit -m "Add action to activate or deactivate a location"
git add database/seeders/Foundation/LocationLevelSeeder.php && git commit -m "Seed location levels"
git add database/seeders/Foundation/data/locations.php && git commit -m "Add Bangladesh division and district seed data"
git add database/seeders/Foundation/LocationSeeder.php && git commit -m "Seed locations from the data file"
git add database/seeders/Foundation/FoundationSeeder.php && git commit -m "Run the location seeder with the foundation seed"
git add tests/Feature/Foundation/Admin/LocationActionsTest.php && git commit -m "Test location saving, paths and seed"
```

---

### Task 4: Notification preferences storage

**Files:**
- Create: `config/notifications.php`
- Create: `database/migrations/foundation/2026_10_06_100100_create_notification_preferences_table.php`
- Create: `app/Modules/Foundation/Models/NotificationPreference.php`
- Create: `app/Modules/Foundation/Actions/SaveNotificationPreferences.php`
- Test: `tests/Feature/Foundation/Admin/NotificationPreferencesTest.php`

**Interfaces:**
- Produces:
  - `config('notifications.keys')`, shaped `array<string, array{label: string, channels: list<string>}>`.
  - `NotificationPreference::matrixFor(User $user): array<string, array<string, bool>>` maps key → channel → enabled, defaulting to `true`.
  - `SaveNotificationPreferences::handle(User $user, array<string, array<string, bool>> $matrix): void` ignores unknown keys and channels.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Foundation/Admin/NotificationPreferencesTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Actions\SaveNotificationPreferences;
use App\Modules\Foundation\Models\NotificationPreference;

test('preferences default to enabled for every registered key and channel', function () {
    $matrix = NotificationPreference::matrixFor(User::factory()->create());

    expect($matrix)->toHaveKeys(['user.created', 'user.password_reset', 'security.login_new_ip'])
        ->and($matrix['security.login_new_ip'])->toBe(['mail' => true]);
});

test('saving stores choices and ignores unknown keys and channels', function () {
    $user = User::factory()->create();

    app(SaveNotificationPreferences::class)->handle($user, [
        'security.login_new_ip' => ['mail' => false, 'sms' => true],
        'made.up' => ['mail' => false],
    ]);

    expect(NotificationPreference::matrixFor($user)['security.login_new_ip'])->toBe(['mail' => false])
        ->and(NotificationPreference::query()->count())->toBe(1);
});
```

- [ ] **Step 2: Run the test to see it fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/NotificationPreferencesTest.php`
Expected: FAIL with "Class ...NotificationPreference not found".

- [ ] **Step 3: Write the config, migration and model**

`config/notifications.php`:

```php
<?php

/*
| Notification keys users can opt out of (docs/01 §9). Delivery is built in sub-project 3;
| each module adds its own keys. channels: database, mail, sms.
*/

return [
    'keys' => [
        'user.created' => ['label' => 'Your account was created', 'channels' => ['mail']],
        'user.password_reset' => ['label' => 'An admin reset your password', 'channels' => ['mail']],
        'security.login_new_ip' => ['label' => 'Sign-in from a new IP address', 'channels' => ['mail']],
    ],
];
```

Migration `2026_10_06_100100_create_notification_preferences_table.php` (create it with `php artisan make:migration create_notification_preferences_table --path=database/migrations/foundation --no-interaction` and rename it):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_key', 80);
            $table->string('channel', 20);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'notification_key', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
```

`app/Modules/Foundation/Models/NotificationPreference.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $notification_key
 * @property string $channel
 * @property bool $is_enabled
 */
#[Fillable(['user_id', 'notification_key', 'channel', 'is_enabled'])]
class NotificationPreference extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    /**
     * Every registered key and channel with the user's choice; missing rows mean enabled.
     *
     * @return array<string, array<string, bool>>
     */
    public static function matrixFor(User $user): array
    {
        $saved = static::query()->where('user_id', $user->id)->get()
            ->mapWithKeys(fn (self $row): array => [$row->notification_key.'|'.$row->channel => $row->is_enabled]);

        $matrix = [];

        /** @var array<string, array{label: string, channels: list<string>}> $keys */
        $keys = config('notifications.keys', []);

        foreach ($keys as $key => $definition) {
            foreach ($definition['channels'] as $channel) {
                $matrix[$key][$channel] = (bool) ($saved[$key.'|'.$channel] ?? true);
            }
        }

        return $matrix;
    }
}
```

- [ ] **Step 4: Write the action**

`app/Modules/Foundation/Actions/SaveNotificationPreferences.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\NotificationPreference;
use Illuminate\Support\Facades\DB;

class SaveNotificationPreferences
{
    /**
     * @param  array<string, array<string, bool>>  $matrix
     */
    public function handle(User $user, array $matrix): void
    {
        /** @var array<string, array{label: string, channels: list<string>}> $keys */
        $keys = config('notifications.keys', []);

        DB::transaction(function () use ($user, $matrix, $keys): void {
            foreach ($matrix as $key => $channels) {
                foreach ($channels as $channel => $enabled) {
                    if (! in_array($channel, $keys[$key]['channels'] ?? [], true)) {
                        continue;
                    }

                    NotificationPreference::query()->updateOrCreate(
                        ['user_id' => $user->id, 'notification_key' => $key, 'channel' => $channel],
                        ['is_enabled' => (bool) $enabled],
                    );
                }
            }
        });
    }
}
```

- [ ] **Step 5: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/NotificationPreferencesTest.php`
Expected: PASS.

- [ ] **Step 6: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add config/notifications.php && git commit -m "Add notification key registry"
git add database/migrations/foundation/2026_10_06_100100_create_notification_preferences_table.php && git commit -m "Add notification preferences table"
git add app/Modules/Foundation/Models/NotificationPreference.php && git commit -m "Add notification preference model with default matrix"
git add app/Modules/Foundation/Actions/SaveNotificationPreferences.php && git commit -m "Add action to save notification preferences"
git add tests/Feature/Foundation/Admin/NotificationPreferencesTest.php && git commit -m "Test notification preference defaults and saving"
```

---

### Task 5: `roles` setting type and empty 2FA role list (spec D7)

**Files:**
- Modify: `app/Support/Settings/SettingsRepository.php` (`cast`)
- Modify: `database/seeders/Foundation/SettingSeeder.php`
- Test: `tests/Feature/Foundation/SettingsTest.php`

**Interfaces:**
- Produces:
  - Settings of type `roles` read back as `list<string>`.
  - `general.require_2fa_roles` is seeded as `[]` with type `roles`.
  - Re-seeding updates `type`/`label` on existing rows but keeps the stored value.

- [ ] **Step 1: Update the tests**

In `tests/Feature/Foundation/SettingsTest.php`, change the first test's last expectation to `->and(Settings::get('general.require_2fa_roles'))->toBe([]);`. Then append:

```php
test('roles settings are stored and read as a list of role codes', function () {
    Settings::set('general.require_2fa_roles', ['finance_manager', 'super_admin']);

    expect(Settings::get('general.require_2fa_roles'))->toBe(['finance_manager', 'super_admin'])
        ->and(Setting::query()->where('key', 'require_2fa_roles')->value('type'))->toBe('roles');
});

test('re-seeding corrects the type of an existing row but keeps its value', function () {
    Setting::query()->where('key', 'require_2fa_roles')->update(['type' => 'json', 'value' => json_encode(['viewer'])]);

    $this->seed(SettingSeeder::class);

    $row = Setting::query()->where('key', 'require_2fa_roles')->first();
    expect($row->type)->toBe('roles')->and($row->value)->toBe(['viewer']);
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/SettingsTest.php`
Expected: FAIL, because the default is still `['finance_manager', 'super_admin']`.

- [ ] **Step 3: Implement**

In `SettingsRepository::cast()`, add the arm `$type === 'roles' => array_values(array_map(strval(...), (array) $value)),` above the `default` arm.

In `SettingSeeder`:
- change the `require_2fa_roles` row to `'type' => 'roles', 'value' => []`;
- replace the loop body with:

```php
            $row = Setting::query()->firstOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                ['type' => $setting['type'], 'value' => $setting['value'], 'label' => $setting['label']],
            );

            if ($row->type !== $setting['type'] || $row->label !== $setting['label']) {
                $row->update(['type' => $setting['type'], 'label' => $setting['label']]);
            }
```

- [ ] **Step 4: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/SettingsTest.php tests/Feature/Foundation/FoundationSeederTest.php`
Expected: PASS.

- [ ] **Step 5: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Settings/SettingsRepository.php && git commit -m "Add roles setting type"
git add database/seeders/Foundation/SettingSeeder.php && git commit -m "Seed an empty 2FA role list and correct setting types on re-seed"
git add tests/Feature/Foundation/SettingsTest.php && git commit -m "Test roles settings and type correction on re-seed"
```

---
### Task 6: Shared list, form and sheet scaffolding, exports, routes and navigation

**Files:**
- Create: `app/Support/Listing/WithListing.php`
- Create: `app/Support/Exports/QueryExport.php`, `app/Support/Exports/ListingExport.php`
- Create: `resources/views/components/shell/list.blade.php`, `sort-header.blade.php`, `form-page.blade.php`, `sheet.blade.php`
- Create: `routes/modules/foundation.php`
- Create: `tests/Fixtures/ListingFixture.php`
- Modify: `routes/web.php`, `config/navigation.php`
- Modify: `resources/views/layouts/app.blade.php`, `resources/views/layouts/app/sidebar.blade.php`
- Test: `tests/Feature/Foundation/Admin/ListingTest.php`, `tests/Feature/Foundation/Admin/ListingExportTest.php`

**Interfaces:**
- Produces:
  - **The `WithListing` trait** (`App\Support\Listing\WithListing`, uses Livewire `WithPagination`).
    - Public properties: `string $search`, `array<string, string> $filters`, `string $sort`, `string $direction`, `int $perPage`, `int $limit`, `bool $hasMoreRows`.
    - Actions: `sortBy(string $key)`, `loadMore()`, `clearFilters()`.
    - Helpers: `paginatedRows(): LengthAwarePaginator`, `mobileRows(): Collection`. `mobileRows()` sets `$hasMoreRows`, so views must read `$this->hasMoreRows` after calling it (not the extracted `$hasMoreRows` variable).
    - The using component must implement `listingQuery(): Builder`, `searchColumns(): list<string>` and `sortColumns(): array<string, string>` (sort key → column). It may override `applyFilters(Builder $query): void`.
    - `WithListing::PER_PAGE_OPTIONS = [25, 50, 100]`.
  - **Exports:** `ListingExport::download(string $name, Builder $query, array<string, string|Closure> $columns): BinaryFileResponse`. It records `AuditTrail::record($actor, 'exported', null, ['export' => $name, 'rows' => n])` on the acting user and returns `{name}-YYYYmmdd-His.xlsx`. (This replaces the spec §4.3 wording "on the exported resource type", because `audit_logs.auditable_id` is NOT NULL.)
  - **Blade components:**
    - `<x-shell.list>` with props `search-placeholder`, `create-url`, `create-label`, `exportable`, `has-more`, `active-filters`, and slots `filters`, `desktop`, `mobile`.
    - `<x-shell.sort-header key label :sort :direction />`
    - `<x-shell.form-page cancel-url submit-label>` (a `<form>`; pass `wire:submit`).
    - `<x-shell.sheet id title description>` with a `footer` slot. Open and close it from Livewire with `$this->dispatch('open-sheet-{id}')` and `$this->dispatch('close-sheet-{id}')`.
  - **Layout data:** `bottomNav` (bool, default true) hides the mobile bottom nav. Pass it from a Livewire component with `->layoutData(['bottomNav' => false])`.
  - **Routes:** `routes/modules/foundation.php` defines the `admin` group (`Route::middleware('app')->prefix('admin')->name('admin.')`). Later tasks add routes inside the marked group.
  - **Test helper:** `seedAccessControl()` in `tests/Pest.php`.

- [ ] **Step 1: Write the failing tests**

`tests/Fixtures/ListingFixture.php`:

```php
<?php

namespace Tests\Fixtures;

use App\Models\User;
use App\Support\Listing\WithListing;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ListingFixture extends Component
{
    use WithListing;

    /**
     * @return Builder<User>
     */
    protected function listingQuery(): Builder
    {
        return User::query()->orderBy('id');
    }

    protected function searchColumns(): array
    {
        return ['name', 'username'];
    }

    protected function sortColumns(): array
    {
        return ['name' => 'name'];
    }

    protected function applyFilters(Builder $query): void
    {
        if (($this->filters['active'] ?? '') !== '') {
            $query->where('is_active', $this->filters['active'] === '1');
        }
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <p>desktop:@foreach ($this->paginatedRows() as $row){{ $row->username }},@endforeach</p>
                <p>mobile:@foreach ($this->mobileRows() as $row){{ $row->username }},@endforeach</p>
                <p>more:{{ $this->hasMoreRows ? 'yes' : 'no' }}</p>
            </div>
            BLADE;
    }
}
```

`tests/Feature/Foundation/Admin/ListingTest.php`:

```php
<?php

use App\Models\User;
use Livewire\Livewire;
use Tests\Fixtures\ListingFixture;

test('search matches name or username case-insensitively', function () {
    User::factory()->create(['username' => 'karim', 'name' => 'Abdul Karim']);
    User::factory()->create(['username' => 'rahim', 'name' => 'Rahim Uddin']);

    Livewire::test(ListingFixture::class)
        ->set('search', 'KARIM')
        ->assertSee('desktop:karim,')
        ->assertDontSee('rahim,');
});

test('wildcard characters in the search are matched literally', function () {
    User::factory()->create(['username' => 'a_b', 'name' => 'Underscore']);
    User::factory()->create(['username' => 'axb', 'name' => 'Other']);
    User::factory()->create(['username' => 'pct', 'name' => '50% off']);

    Livewire::test(ListingFixture::class)
        ->set('search', 'a_b')->assertSee('desktop:a_b,')->assertDontSee('axb,')
        ->set('search', '%')->assertSee('desktop:pct,')->assertDontSee('a_b,');
});

test('unknown sort keys, bad directions and page sizes fall back to defaults', function () {
    User::factory()->create(['username' => 'zed', 'name' => 'Zed']);
    User::factory()->create(['username' => 'amy', 'name' => 'Amy']);

    Livewire::withQueryParams(['sort' => 'password', 'direction' => 'sideways', 'perPage' => 9999])
        ->test(ListingFixture::class)
        ->assertSee('desktop:zed,amy,')
        ->assertSet('perPage', 25);
});

test('sorting by a whitelisted key toggles direction', function () {
    User::factory()->create(['username' => 'zed', 'name' => 'Zed']);
    User::factory()->create(['username' => 'amy', 'name' => 'Amy']);

    Livewire::test(ListingFixture::class)
        ->call('sortBy', 'name')->assertSee('desktop:amy,zed,')
        ->call('sortBy', 'name')->assertSee('desktop:zed,amy,');
});

test('filters narrow the rows and clearFilters resets them', function () {
    User::factory()->create(['username' => 'live']);
    User::factory()->inactive()->create(['username' => 'gone']);

    Livewire::test(ListingFixture::class)
        ->set('filters.active', '0')->assertSee('desktop:gone,')->assertDontSee('live,')
        ->call('clearFilters')->assertSee('live,')->assertSee('gone,');
});

test('mobile rows load 25 at a time', function () {
    User::factory()->count(30)->create();

    Livewire::test(ListingFixture::class)
        ->assertSee('more:yes')
        ->call('loadMore')
        ->assertSet('limit', 50)
        ->assertSee('more:no');
});
```

`tests/Feature/Foundation/Admin/ListingExportTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Models\AuditLog;
use App\Support\Exports\ListingExport;
use App\Support\Exports\QueryExport;
use Maatwebsite\Excel\Facades\Excel;

test('a listing export downloads the query rows and records an audit row', function () {
    Excel::fake();
    $this->travelTo(now()->setDate(2026, 10, 6)->setTime(9, 30, 0));
    $actor = User::factory()->create(['username' => 'exporter']);
    $this->actingAs($actor);

    ListingExport::download('users', User::query(), ['Username' => 'username', 'Name' => fn (User $user): string => strtoupper($user->name)]);

    Excel::assertDownloaded('users-20261006-093000.xlsx', function (QueryExport $export) {
        return $export->headings() === ['Username', 'Name']
            && $export->map(User::query()->where('username', 'exporter')->first())[0] === 'exporter';
    });

    expect(AuditLog::query()->where('event', 'exported')->where('auditable_id', $actor->id)->first()?->new_values)
        ->toBe(['export' => 'users', 'rows' => 1]);
});
```

Add to `tests/Pest.php` (with `use Database\Seeders\Foundation\PermissionSeeder;`, `RoleSeeder`, `RolePermissionSeeder`):

```php
/**
 * Seed every module permission, the system roles and their default grants.
 */
function seedAccessControl(): void
{
    test()->seed([PermissionSeeder::class, RoleSeeder::class, RolePermissionSeeder::class]);
}
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/ListingTest.php tests/Feature/Foundation/Admin/ListingExportTest.php`
Expected: FAIL with "Trait ...WithListing not found".

- [ ] **Step 3: Write `WithListing`**

`app/Support/Listing/WithListing.php`:

```php
<?php

namespace App\Support\Listing;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Search, filters, sort and paging for list screens (docs/00 §7.2, §7.6).
 * Desktop renders paginatedRows(); mobile renders mobileRows() with infinite scroll.
 * Sort keys are whitelisted through sortColumns(), so URL tampering cannot reach other columns.
 */
trait WithListing
{
    use WithPagination;

    public const PER_PAGE_OPTIONS = [25, 50, 100];

    #[Url(except: '')]
    public string $search = '';

    /** @var array<string, string> */
    #[Url(except: [])]
    public array $filters = [];

    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    #[Url(except: 25)]
    public int $perPage = 25;

    public int $limit = 25;

    public bool $hasMoreRows = false;

    /**
     * The base query, including eager loads and the default order.
     *
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    abstract protected function listingQuery(): Builder;

    /**
     * Columns matched by the search box.
     *
     * @return list<string>
     */
    abstract protected function searchColumns(): array;

    /**
     * Sortable keys mapped to their columns.
     *
     * @return array<string, string>
     */
    abstract protected function sortColumns(): array;

    /**
     * Apply $this->filters to the query. Override in the component.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function applyFilters(Builder $query): void {}

    public function updatedSearch(): void
    {
        $this->resetListing();
    }

    public function updatedFilters(): void
    {
        $this->resetListing();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $key): void
    {
        if (! array_key_exists($key, $this->sortColumns())) {
            return;
        }

        $this->direction = $this->sort === $key && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $key;
        $this->resetListing();
    }

    public function loadMore(): void
    {
        $this->limit += 25;
    }

    public function clearFilters(): void
    {
        $this->reset('filters', 'search');
        $this->resetListing();
    }

    public function paginatedRows(): LengthAwarePaginator
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 25;
        }

        return $this->filteredQuery()->paginate($this->perPage);
    }

    /**
     * The first $limit rows for the mobile list; sets $hasMoreRows.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function mobileRows(): Collection
    {
        $rows = $this->filteredQuery()->limit($this->limit + 1)->get();
        $this->hasMoreRows = $rows->count() > $this->limit;

        return $rows->take($this->limit);
    }

    /**
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function filteredQuery(): Builder
    {
        $query = $this->listingQuery();
        $this->applyFilters($query);

        $term = trim($this->search);

        if ($term !== '' && $this->searchColumns() !== []) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($term)).'%';

            $query->where(function (Builder $query) use ($like): void {
                foreach ($this->searchColumns() as $column) {
                    $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$like]);
                }
            });
        }

        $column = $this->sortColumns()[$this->sort] ?? null;

        if ($column !== null) {
            $query->reorder($column, $this->direction === 'desc' ? 'desc' : 'asc');
        }

        return $query;
    }

    private function resetListing(): void
    {
        $this->limit = 25;
        $this->resetPage();
    }
}
```

`searchColumns()` values are written by developers, never taken from user input, which is why the raw interpolation is safe.

- [ ] **Step 4: Write the exports**

`app/Support/Exports/QueryExport.php`:

```php
<?php

namespace App\Support\Exports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Excel export of a query. Columns map a heading to an attribute path or a closure.
 */
final class QueryExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<string, string|Closure(Model): mixed>  $columns
     */
    public function __construct(private Builder $query, private array $columns) {}

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys($this->columns);
    }

    /**
     * @param  Model  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return array_map(
            fn (string|Closure $column): mixed => $column instanceof Closure ? $column($row) : data_get($row, $column),
            array_values($this->columns),
        );
    }
}
```

`app/Support/Exports/ListingExport.php`:

```php
<?php

namespace App\Support\Exports;

use App\Models\User;
use App\Support\AuditTrail\AuditTrail;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ListingExport
{
    /**
     * Download the rows as Excel and record who exported what (docs/01 §10).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, string|Closure(Model): mixed>  $columns
     */
    public static function download(string $name, Builder $query, array $columns): BinaryFileResponse
    {
        /** @var User $actor */
        $actor = Auth::user();

        AuditTrail::record($actor, 'exported', null, ['export' => $name, 'rows' => (clone $query)->toBase()->getCountForPagination()]);

        return Excel::download(new QueryExport($query, $columns), $name.'-'.now()->format('Ymd-His').'.xlsx');
    }
}
```

- [ ] **Step 5: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/ListingTest.php tests/Feature/Foundation/Admin/ListingExportTest.php`
Expected: PASS.

- [ ] **Step 6: Build the Blade scaffolding**

`resources/views/components/shell/sort-header.blade.php`:

```blade
@props(['key', 'label', 'sort' => '', 'direction' => 'asc'])

<button type="button" wire:click="sortBy('{{ $key }}')" class="inline-flex items-center gap-1 font-medium hover:text-foreground">
    {{ $label }}
    @if ($sort === $key)
        <x-dynamic-component :component="$direction === 'desc' ? 'lucide-arrow-down' : 'lucide-arrow-up'" class="size-3.5" />
    @else
        <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
    @endif
</button>
```

`resources/views/components/shell/list.blade.php`:

```blade
@props([
    'searchPlaceholder' => __('Search'),
    'createUrl' => null,
    'createLabel' => __('New'),
    'exportable' => false,
    'hasMore' => false,
    'activeFilters' => 0,
])

<div class="flex flex-col gap-4">
    {{-- Desktop toolbar and table --}}
    <div class="hidden items-center gap-2 md:flex">
        <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="$searchPlaceholder" class="max-w-xs" />

        @isset($filters)
            <x-ui.popover>
                <x-ui.popover-trigger>
                    <x-ui.button variant="outline">
                        <x-lucide-list-filter />
                        {{ __('Filters') }}
                        @if ($activeFilters > 0)
                            <x-ui.badge variant="secondary">{{ $activeFilters }}</x-ui.badge>
                        @endif
                    </x-ui.button>
                </x-ui.popover-trigger>
                <x-ui.popover-content class="w-80">
                    <div class="flex flex-col gap-4">
                        {{ $filters }}
                        <x-ui.button variant="ghost" size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>
                    </div>
                </x-ui.popover-content>
            </x-ui.popover>
        @endisset

        <div class="ms-auto flex items-center gap-2">
            @if ($exportable)
                <x-ui.button variant="outline" wire:click="export">
                    <x-lucide-download />
                    {{ __('Export') }}
                </x-ui.button>
            @endif
            @if ($createUrl)
                <x-ui.button :href="$createUrl" wire:navigate>
                    <x-lucide-plus />
                    {{ $createLabel }}
                </x-ui.button>
            @endif
        </div>
    </div>

    <div class="hidden md:block">{{ $desktop }}</div>

    {{-- Mobile search, filter sheet and rows --}}
    <div class="flex flex-col gap-3 md:hidden">
        <div class="flex items-center gap-2">
            <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="$searchPlaceholder" class="h-11 flex-1 text-base" />

            @isset($filters)
                <x-ui.drawer>
                    <x-ui.drawer-trigger>
                        <x-ui.button variant="outline" size="icon" class="relative size-11" :aria-label="__('Filters')">
                            <x-lucide-list-filter class="size-5" />
                            @if ($activeFilters > 0)
                                <span class="absolute -top-1 -end-1 flex size-5 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground">{{ $activeFilters }}</span>
                            @endif
                        </x-ui.button>
                    </x-ui.drawer-trigger>
                    <x-ui.drawer-content>
                        <x-ui.drawer-header>
                            <x-ui.drawer-title>{{ __('Filters') }}</x-ui.drawer-title>
                        </x-ui.drawer-header>
                        <div class="flex flex-col gap-4 overflow-y-auto px-4">{{ $filters }}</div>
                        <x-ui.drawer-footer class="flex-row gap-2 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                            <x-ui.button variant="outline" class="h-11 flex-1" wire:click="clearFilters">{{ __('Clear') }}</x-ui.button>
                            <x-ui.drawer-close class="flex-1">
                                <x-ui.button class="h-11 w-full">{{ __('Done') }}</x-ui.button>
                            </x-ui.drawer-close>
                        </x-ui.drawer-footer>
                    </x-ui.drawer-content>
                </x-ui.drawer>
            @endisset

            @if ($exportable)
                <x-ui.button variant="outline" size="icon" class="size-11" wire:click="export" :aria-label="__('Export')">
                    <x-lucide-download class="size-5" />
                </x-ui.button>
            @endif
        </div>

        <x-ui.item-group class="gap-2">{{ $mobile }}</x-ui.item-group>

        @if ($hasMore)
            <x-ui.infinite-scroll x-on:load-more.prevent="$wire.loadMore().then(() => $el.dispatchEvent(new CustomEvent('load-more-done', { detail: { done: false } })))" />
        @endif
    </div>

    @if ($createUrl)
        <a href="{{ $createUrl }}" wire:navigate aria-label="{{ $createLabel }}"
           class="fixed end-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg active:scale-95 md:hidden">
            <x-lucide-plus class="size-6" />
        </a>
    @endif
</div>
```

`resources/views/components/shell/form-page.blade.php`:

```blade
@props(['cancelUrl' => null, 'submitLabel' => __('Save')])

<form {{ $attributes->merge(['class' => 'flex flex-col gap-6 pb-28 md:pb-0']) }}>
    <x-ui.card class="max-md:border-0 max-md:bg-transparent max-md:py-0 max-md:shadow-none">
        <x-ui.card-content class="flex max-w-2xl flex-col gap-6 max-md:px-0">
            {{ $slot }}
        </x-ui.card-content>
        <x-ui.card-footer class="hidden justify-end gap-2 md:flex">
            @if ($cancelUrl)
                <x-ui.button variant="outline" :href="$cancelUrl" wire:navigate>{{ __('Cancel') }}</x-ui.button>
            @endif
            <x-ui.button type="submit">{{ $submitLabel }}</x-ui.button>
        </x-ui.card-footer>
    </x-ui.card>

    <div data-test="mobile-action-bar" class="fixed inset-x-0 bottom-0 z-40 flex gap-2 border-t bg-background px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] md:hidden">
        @if ($cancelUrl)
            <x-ui.button variant="outline" class="h-11 flex-1" :href="$cancelUrl" wire:navigate>{{ __('Cancel') }}</x-ui.button>
        @endif
        <x-ui.button type="submit" class="h-11 flex-1">{{ $submitLabel }}</x-ui.button>
    </div>
</form>
```

`resources/views/components/shell/sheet.blade.php`. The sheet slides in from the right on desktop and is restyled into a bottom sheet below `md`:

```blade
@props(['id', 'title' => null, 'description' => null])

<x-ui.sheet :id="$id" {{ $attributes }}>
    <x-ui.sheet-content side="right"
        class="sm:max-w-md max-md:inset-x-0 max-md:top-auto max-md:bottom-0 max-md:h-auto max-md:max-h-[90dvh] max-md:w-full max-md:max-w-none max-md:rounded-t-2xl max-md:border-l-0 max-md:border-t">
        <x-ui.sheet-header>
            @if ($title)
                <x-ui.sheet-title>{{ $title }}</x-ui.sheet-title>
            @endif
            @if ($description)
                <x-ui.sheet-description>{{ $description }}</x-ui.sheet-description>
            @endif
        </x-ui.sheet-header>

        <div class="flex flex-1 flex-col gap-4 overflow-y-auto px-4">{{ $slot }}</div>

        @isset($footer)
            <x-ui.sheet-footer class="flex-row gap-2 pb-[calc(1rem+env(safe-area-inset-bottom))] [&>*]:h-11 [&>*]:flex-1 md:[&>*]:h-9 md:[&>*]:flex-none">
                {{ $footer }}
            </x-ui.sheet-footer>
        @endisset
    </x-ui.sheet-content>
</x-ui.sheet>
```

If `x-ui.sheet-header` / `sheet-footer` / `sheet-title` / `sheet-description` / `popover-*` / `drawer-footer` / `drawer-close` / `card-footer` are missing, run `php artisan blatui:add sheet popover drawer card --no-interaction`.

- [ ] **Step 7: Add the `bottomNav` layout flag**

`resources/views/layouts/app.blade.php`: change the first line to:

```blade
<x-layouts::app.sidebar :title="$title ?? null" :back="$back ?? null" :bottom-nav="$bottomNav ?? true">
```

`resources/views/layouts/app/sidebar.blade.php`:
- change `@props` to `@props(['title' => null, 'back' => null, 'bottomNav' => true])`;
- on `x-ui.sidebar-inset`, make the bottom padding conditional: `class="pt-[calc(3.5rem+env(safe-area-inset-top))] {{ $bottomNav ? 'pb-[calc(4rem+env(safe-area-inset-bottom))]' : '' }} md:pt-0 md:pb-0"`;
- wrap `<x-shell.mobile-bottom-nav />` in `@if ($bottomNav) … @endif`.

- [ ] **Step 8: Create the routes file and navigation entries**

`routes/modules/foundation.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

/*
| Foundation & Administration routes (docs/01 §5). Each admin route repeats its permission as
| `can:` middleware; Livewire actions authorize again.
*/

Route::middleware('app')->prefix('admin')->name('admin.')->group(function () {
    // Admin screens are added here by the tasks that build them.
});
```

In `routes/web.php`, add `require __DIR__.'/modules/foundation.php';` before `require __DIR__.'/settings.php';`.

In `config/navigation.php`, set the `admin` group's items:

```php
        ['key' => 'admin', 'label' => 'Admin', 'icon' => 'shield', 'items' => [
            ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'users', 'permission' => 'admin.users.view'],
            ['label' => 'Roles', 'route' => 'admin.roles.index', 'icon' => 'shield-check', 'permission' => 'admin.roles.view'],
            ['label' => 'Master data', 'route' => 'admin.master-data.index', 'icon' => 'list', 'permission' => 'admin.master_data.view'],
            ['label' => 'Branches', 'route' => 'admin.branches.index', 'icon' => 'building-2', 'permission' => 'admin.branches.view'],
            ['label' => 'Locations', 'route' => 'admin.locations.index', 'icon' => 'map-pin', 'permission' => 'admin.locations.view'],
            ['label' => 'Company', 'route' => 'admin.company.edit', 'icon' => 'building', 'permission' => 'admin.company.view'],
            ['label' => 'Settings', 'route' => 'admin.settings.edit', 'icon' => 'settings', 'permission' => 'admin.settings.view'],
            ['label' => 'Number sequences', 'route' => 'admin.sequences.index', 'icon' => 'hash', 'permission' => 'admin.sequences.view'],
            ['label' => 'Audit log', 'route' => 'admin.audit.index', 'icon' => 'history', 'permission' => 'admin.audit.view'],
            ['label' => 'Login history', 'route' => 'admin.login-history.index', 'icon' => 'log-in', 'permission' => 'admin.login_history.view'],
        ]],
```

Items stay hidden until their route exists, because `Navigation` skips routes that are missing.

- [ ] **Step 9: Run the related suites**

Run: `php artisan test --compact tests/Feature/Foundation tests/Feature/DashboardTest.php`
Expected: PASS.

- [ ] **Step 10: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Listing/WithListing.php && git commit -m "Add shared listing trait for search, filters, sort and paging" -m "Sort keys are whitelisted and search wildcards are escaped."
git add app/Support/Exports/QueryExport.php && git commit -m "Add generic query export"
git add app/Support/Exports/ListingExport.php && git commit -m "Download listing exports and audit them"
git add resources/views/components/shell/sort-header.blade.php && git commit -m "Add sortable table header component"
git add resources/views/components/shell/list.blade.php && git commit -m "Add list shell with desktop table and mobile rows"
git add resources/views/components/shell/form-page.blade.php && git commit -m "Add form page shell with sticky mobile action bar"
git add resources/views/components/shell/sheet.blade.php && git commit -m "Add sheet shell that becomes a bottom sheet on mobile"
git add resources/views/layouts/app.blade.php && git commit -m "Forward bottom nav flag through the app layout"
git add resources/views/layouts/app/sidebar.blade.php && git commit -m "Allow pages to hide the mobile bottom nav"
git add routes/modules/foundation.php && git commit -m "Add foundation module routes file"
git add routes/web.php && git commit -m "Load foundation module routes"
git add config/navigation.php && git commit -m "Add admin navigation items"
git add tests/Fixtures/ListingFixture.php && git commit -m "Add listing fixture component for tests"
git add tests/Pest.php && git commit -m "Add access control seeding helper"
git add tests/Feature/Foundation/Admin/ListingTest.php && git commit -m "Test listing search, sort, filters and paging"
git add tests/Feature/Foundation/Admin/ListingExportTest.php && git commit -m "Test listing export download and audit"
```

---

### Task 7: User actions (create, update, activate, access)

**Files:**
- Create: `app/Modules/Foundation/Concerns/ValidatesUserInput.php`
- Create: `app/Modules/Foundation/Actions/SyncUserAccess.php`, `CreateUser.php`, `UpdateUser.php`, `SetUserActive.php`
- Modify: `app/Models/User.php` (add `branch()`)
- Test: `tests/Feature/Foundation/Admin/UserActionsTest.php`

**Interfaces:**
- Consumes: `EnsureNotLastSuperAdmin::handle(User)`, `User::syncRoles()`/`syncDirectPermissions()`, `Password::default()` (Task 1).
- Produces:
  - `CreateUser::handle(array $input, User $actor): User`
  - `UpdateUser::handle(User $user, array $input, User $actor): User`
  - `SetUserActive::handle(User $user, bool $active, User $actor): void`
  - `SetUserActive::ensureCanDeactivate(User $user, User $actor): void`
  - `SyncUserAccess::handle(User $user, list<string> $roles, list<string> $permissions, User $actor): void`
  - `User::branch(): BelongsTo`
- **Input keys:** `name`, `username`, `email`, `phone`, `branch_id`, `roles` (role codes), `permissions` (permission names), `password`, `password_confirmation`, `is_active`.
- **Errors:** rule failures throw `ValidationException`. Field errors use the input key. Account-level errors use the key `user`, and role errors use the key `roles`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/UserActionsTest.php`:

```php
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

    $entry = AuditLog::query()->where('auditable_type', 'user')->where('auditable_id', $user->id)->where('event', 'updated')->latest('id')->first();
    expect($entry->old_values['username'])->toBe('oldname')->and($entry->new_values['username'])->toBe('newname');
});
```

Add this shared helper to `tests/Pest.php` (with `use Illuminate\Validation\ValidationException;`). Later tasks use it too:

```php
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
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/UserActionsTest.php`
Expected: FAIL with "Class ...CreateUser not found".

- [ ] **Step 3: Write the validation concern**

`app/Modules/Foundation/Concerns/ValidatesUserInput.php`:

```php
<?php

namespace App\Modules\Foundation\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validation for admin-maintained user accounts (docs/01 §5.3).
 */
trait ValidatesUserInput
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function userRules(?User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', $this->uniqueIgnoringCase('username', $user)],
            'email' => ['nullable', 'string', 'email', 'max:150', $this->uniqueIgnoringCase('email', $user)],
            'phone' => ['nullable', 'string', 'regex:/^(?:\+?880|0)1[3-9]\d{8}$/'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'code')->where('is_active', true)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'password' => [$user === null ? 'required' : 'nullable', 'string', Password::default(), 'confirmed'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Trim and blank-to-null the free-text fields and strip phone separators before validation.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function prepareUserInput(array $input): array
    {
        $phone = (string) preg_replace('/[\s\-()]/', '', (string) ($input['phone'] ?? ''));

        return [
            ...$input,
            'username' => trim((string) ($input['username'] ?? '')),
            'email' => filled($input['email'] ?? null) ? trim((string) $input['email']) : null,
            'phone' => $phone === '' ? null : $phone,
            'branch_id' => filled($input['branch_id'] ?? null) ? (int) $input['branch_id'] : null,
            'password' => filled($input['password'] ?? null) ? $input['password'] : null,
        ];
    }

    private function uniqueIgnoringCase(string $column, ?User $user): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $user): void {
            $taken = User::withTrashed()
                ->whereRaw("LOWER({$column}) = ?", [Str::lower(trim((string) $value))])
                ->when($user !== null, fn ($query) => $query->whereKeyNot($user->id))
                ->exists();

            if ($taken) {
                $fail(__('This :attribute is already taken.'));
            }
        };
    }
}
```

- [ ] **Step 4: Write the actions**

`app/Modules/Foundation/Actions/SyncUserAccess.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use Illuminate\Validation\ValidationException;

/**
 * Applies a user's roles and direct permissions. Only a super admin may grant or remove
 * super_admin, and the last super admin keeps it (FD-BR-02).
 */
class SyncUserAccess
{
    public function __construct(private EnsureNotLastSuperAdmin $ensureNotLastSuperAdmin) {}

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $roles, array $permissions, User $actor): void
    {
        $hadSuperAdmin = $user->exists && $user->roles()->where('code', Role::SUPER_ADMIN)->exists();
        $wantsSuperAdmin = in_array(Role::SUPER_ADMIN, $roles, true);

        if ($hadSuperAdmin !== $wantsSuperAdmin && ! $actor->hasRole(Role::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['roles' => __('Only a super admin can grant or remove the super admin role.')]);
        }

        if ($hadSuperAdmin && ! $wantsSuperAdmin) {
            $this->ensureNotLastSuperAdmin->handle($user);
        }

        $user->syncRoles(array_values($roles));
        $user->syncDirectPermissions(array_values($permissions));
    }
}
```

`app/Modules/Foundation/Actions/SetUserActive.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Activates or deactivates an account. A deactivated user is logged out on their next
 * request by EnsureUserIsActive (spec D10).
 */
class SetUserActive
{
    public function __construct(private EnsureNotLastSuperAdmin $ensureNotLastSuperAdmin) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, bool $active, User $actor): void
    {
        if (! $active) {
            $this->ensureCanDeactivate($user, $actor);
        }

        $user->update(['is_active' => $active]);
    }

    /**
     * @throws ValidationException
     */
    public function ensureCanDeactivate(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => __('You cannot deactivate your own account.')]);
        }

        $this->ensureNotLastSuperAdmin->handle($user);
    }
}
```

`app/Modules/Foundation/Actions/CreateUser.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Concerns\ValidatesUserInput;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Admin-created account (docs/01 §6.1): starts with must_change_password.
 */
class CreateUser
{
    use ValidatesUserInput;

    public function __construct(private SyncUserAccess $syncUserAccess) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, User $actor): User
    {
        /** @var array{name: string, username: string, email: ?string, phone: ?string, branch_id: ?int, roles: list<string>, permissions?: list<string>, password: string, is_active?: bool} $data */
        $data = Validator::make($this->prepareUserInput($input), $this->userRules(null))->validate();

        return DB::transaction(function () use ($data, $actor): User {
            $user = new User(Arr::only($data, ['name', 'username', 'email', 'phone', 'branch_id', 'password']));
            $user->is_active = (bool) ($data['is_active'] ?? true);
            $user->must_change_password = true;
            $user->save();

            $this->syncUserAccess->handle($user, $data['roles'], $data['permissions'] ?? [], $actor);

            return $user;
        });
    }
}
```

`app/Modules/Foundation/Actions/UpdateUser.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Concerns\ValidatesUserInput;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Admin edit of an account. A username change after first login is allowed for admins and
 * audited by the Auditable observer (FD-BR-01). Setting a password forces a change at next login.
 */
class UpdateUser
{
    use ValidatesUserInput;

    public function __construct(
        private SyncUserAccess $syncUserAccess,
        private SetUserActive $setUserActive,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input, User $actor): User
    {
        /** @var array{name: string, username: string, email: ?string, phone: ?string, branch_id: ?int, roles: list<string>, permissions?: list<string>, password: ?string, is_active?: bool} $data */
        $data = Validator::make($this->prepareUserInput($input), $this->userRules($user))->validate();
        $active = (bool) ($data['is_active'] ?? $user->is_active);

        if ($user->is_active && ! $active) {
            $this->setUserActive->ensureCanDeactivate($user, $actor);
        }

        return DB::transaction(function () use ($user, $data, $active, $actor): User {
            $user->fill(Arr::only($data, ['name', 'username', 'email', 'phone', 'branch_id']));
            $user->is_active = $active;

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
                $user->must_change_password = true;
            }

            $user->save();

            $this->syncUserAccess->handle($user, $data['roles'], $data['permissions'] ?? [], $actor);

            return $user;
        });
    }
}
```

In `app/Models/User.php`, add `use App\Modules\Foundation\Models\Branch;` and `use Illuminate\Database\Eloquent\Relations\BelongsTo;`. Then add:

```php
    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
```

- [ ] **Step 5: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/UserActionsTest.php tests/Feature/Foundation/LastSuperAdminTest.php`
Expected: PASS.

- [ ] **Step 6: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Foundation/Concerns/ValidatesUserInput.php && git commit -m "Add shared validation rules for user accounts"
git add app/Modules/Foundation/Actions/SyncUserAccess.php && git commit -m "Sync user roles and grants with super admin guards"
git add app/Modules/Foundation/Actions/SetUserActive.php && git commit -m "Add action to activate or deactivate users" -m "Refuses self-deactivation and deactivating the last super admin."
git add app/Modules/Foundation/Actions/CreateUser.php && git commit -m "Add create user action"
git add app/Modules/Foundation/Actions/UpdateUser.php && git commit -m "Add update user action"
git add app/Models/User.php && git commit -m "Add branch relation to users"
git add tests/Pest.php && git commit -m "Add validation error assertion helper"
git add tests/Feature/Foundation/Admin/UserActionsTest.php && git commit -m "Test user create, update and activation rules"
```

---

### Task 8: Users screens (list, form, export, FD-AC-01)

**Files:**
- Create: `app/Modules/Foundation/Livewire/Admin/Users/Index.php`, `app/Modules/Foundation/Livewire/Admin/Users/Form.php`
- Create: `resources/views/livewire/admin/users/index.blade.php`, `resources/views/livewire/admin/users/form.blade.php`
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/UsersScreenTest.php`

**Interfaces:**
- Consumes: `WithListing`, `ListingExport`, `x-shell.list`/`form-page`/`sheet`/`sort-header` (Task 6); `CreateUser`, `UpdateUser`, `SetUserActive` (Task 7).
- Produces:
  - Routes `admin.users.index`, `admin.users.create`, `admin.users.edit` (`{user:username}`).
  - `Users\Index` public methods: `openActions(int $userId)`, `toggleActive(int $userId)`, `export()`.
  - `Users\Index` public property `?int $actionUserId`. Task 19 adds `impersonate(int $userId)`.
  - **Conventions** used by every later screen:
    - in-place success or failure: `$this->dispatch('toast', type: 'success'|'error', description: ...)`;
    - success followed by a redirect: `session()->flash('success', ...)` and then `$this->redirectRoute(..., navigate: true)`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/UsersScreenTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\Users\Form;
use App\Modules\Foundation\Livewire\Admin\Users\Index;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(fn () => ensureRole('accountant'));

test('the users list needs admin.users.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();

    $this->actingAs(userWithPermissions('admin.users.view'))
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee(route('admin.users.index'));
});

test('the list searches and filters by role', function () {
    $accountant = User::factory()->create(['name' => 'Accounts Person', 'username' => 'acc1']);
    $accountant->syncRoles(['accountant']);
    User::factory()->create(['name' => 'Someone Else', 'username' => 'other1']);

    Livewire::actingAs(userWithPermissions('admin.users.view'))
        ->test(Index::class)
        ->set('filters.role', 'accountant')
        ->assertSee('acc1')
        ->assertDontSee('other1')
        ->set('filters.role', '')
        ->set('search', 'someone')
        ->assertSee('other1')
        ->assertDontSee('acc1');
});

test('toggling active needs admin.users.deactivate even when called directly', function () {
    $target = User::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.users.view'))
        ->test(Index::class)
        ->call('toggleActive', $target->id)
        ->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});

test('deactivating the last super admin shows an error toast (FD-AC-03)', function () {
    $onlySuperAdmin = superAdmin();

    Livewire::actingAs(userWithPermissions('admin.users.view', 'admin.users.deactivate'))
        ->test(Index::class)
        ->call('toggleActive', $onlySuperAdmin->id)
        ->assertDispatched('toast', type: 'error');

    expect($onlySuperAdmin->fresh()->is_active)->toBeTrue();
});

test('the users list exports to excel', function () {
    Excel::fake();
    Excel::matchByRegex();

    Livewire::actingAs(userWithPermissions('admin.users.view'))->test(Index::class)->call('export');

    Excel::assertDownloaded('/^users-\d{8}-\d{6}\.xlsx$/');
});

test('an admin creates a user from the form', function () {
    Livewire::actingAs(userWithPermissions('admin.users.view', 'admin.users.create'))
        ->test(Form::class)
        ->set('name', 'Karim Mia')
        ->set('username', 'Karim')
        ->set('phone', '01812345678')
        ->set('roles', ['accountant'])
        ->set('password', 'secret-pass')
        ->set('password_confirmation', 'secret-pass')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.users.index'));

    expect(User::query()->where('username', 'karim')->first()?->hasRole('accountant'))->toBeTrue();
});

test('form errors show on the fields', function () {
    User::factory()->create(['username' => 'taken']);

    Livewire::actingAs(userWithPermissions('admin.users.create'))
        ->test(Form::class)
        ->set('name', 'X')
        ->set('username', 'TAKEN')
        ->call('save')
        ->assertHasErrors(['username', 'roles', 'password']);
});

test('the edit form is reached by username and prefilled', function () {
    $user = User::factory()->create(['username' => 'karim', 'name' => 'Karim Mia']);
    $user->syncRoles(['accountant']);

    $this->actingAs(userWithPermissions('admin.users.update'))
        ->get('/admin/users/karim/edit')
        ->assertOk()
        ->assertSee('Karim Mia')
        ->assertSee('data-test="mobile-action-bar"', false)
        ->assertDontSee('data-test="mobile-bottom-nav"', false);
});

test('an accountant created by an admin must change password and sees no admin menus (FD-AC-01)', function () {
    seedAccessControl();

    Livewire::actingAs(userWithPermissions('admin.users.view', 'admin.users.create'))
        ->test(Form::class)
        ->set('name', 'Accounts One')
        ->set('username', 'accounts1')
        ->set('roles', ['accountant'])
        ->set('password', 'secret-pass')
        ->set('password_confirmation', 'secret-pass')
        ->call('save')
        ->assertHasNoErrors();

    $accountant = User::query()->where('username', 'accounts1')->firstOrFail();

    $this->actingAs($accountant)->get(route('dashboard'))->assertRedirect(route('password.change'));

    $accountant->forceFill(['must_change_password' => false])->save();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.users.index'));
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/UsersScreenTest.php`
Expected: FAIL with "Route [admin.users.index] not defined".

- [ ] **Step 3: Add the routes**

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Livewire\Admin\Users;`. Inside the group, add:

```php
    Route::livewire('users', Users\Index::class)->middleware('can:admin.users.view')->name('users.index');
    Route::livewire('users/create', Users\Form::class)->middleware('can:admin.users.create')->name('users.create');
    Route::livewire('users/{user:username}/edit', Users\Form::class)->middleware('can:admin.users.update')->name('users.edit');
```

- [ ] **Step 4: Write the list component**

`app/Modules/Foundation/Livewire/Admin/Users/Index.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin\Users;

use App\Models\User;
use App\Modules\Foundation\Actions\SetUserActive;
use App\Modules\Foundation\Models\Role;
use App\Support\Exports\ListingExport;
use App\Support\Facades\Lookup;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Users')]
class Index extends Component
{
    use WithListing;

    public ?int $actionUserId = null;

    public function openActions(int $userId): void
    {
        $this->actionUserId = $userId;
        $this->dispatch('open-sheet-user-actions');
    }

    public function toggleActive(int $userId, SetUserActive $setUserActive): void
    {
        $this->authorize('admin.users.deactivate');

        $user = User::query()->findOrFail($userId);

        try {
            $setUserActive->handle($user, ! $user->is_active, $this->actor());
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->dispatch('close-sheet-user-actions');
        $this->dispatch('toast', type: 'success', description: $user->is_active ? __('User activated.') : __('User deactivated.'));
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('admin.users.view');

        return ListingExport::download('users', $this->filteredQuery(), [
            'Name' => 'name',
            'Username' => 'username',
            'Email' => 'email',
            'Phone' => 'phone',
            'Roles' => fn (User $user): string => $user->roles->pluck('name')->implode(', '),
            'Branch' => 'branch.name',
            'Active' => fn (User $user): string => $user->is_active ? 'Yes' : 'No',
            'Last login' => fn (User $user): ?string => $user->last_login_at?->format('d-M-Y H:i'),
        ]);
    }

    /**
     * @return Builder<User>
     */
    protected function listingQuery(): Builder
    {
        return User::query()->with(['roles:id,code,name', 'branch:id,name'])->orderBy('name');
    }

    protected function searchColumns(): array
    {
        return ['users.name', 'users.username', 'users.email', 'users.phone'];
    }

    protected function sortColumns(): array
    {
        return ['name' => 'users.name', 'username' => 'users.username', 'last_login_at' => 'users.last_login_at'];
    }

    protected function applyFilters(Builder $query): void
    {
        if (filled($this->filters['role'] ?? null)) {
            $query->whereHas('roles', fn (Builder $roles) => $roles->where('code', $this->filters['role']));
        }

        if (filled($this->filters['branch'] ?? null)) {
            $query->where('branch_id', (int) $this->filters['branch']);
        }

        if (($this->filters['active'] ?? '') !== '') {
            $query->where('is_active', $this->filters['active'] === '1');
        }
    }

    public function render(): View
    {
        return view('livewire.admin.users.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'roles' => Role::query()->orderBy('name')->get(['code', 'name']),
            'branches' => Lookup::options('branches'),
            'actionUser' => $this->actionUserId !== null ? User::query()->find($this->actionUserId) : null,
        ]);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
```

- [ ] **Step 5: Write the list view**

`resources/views/livewire/admin/users/index.blade.php`:

```blade
<div>
    <x-shell.list
        :search-placeholder="__('Search name, username, email or phone')"
        :create-url="auth()->user()->can('admin.users.create') ? route('admin.users.create') : null"
        :create-label="__('New user')"
        exportable
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-role">{{ __('Role') }}</x-ui.field-label>
                <x-ui.select native id="filter-role" wire:model.live="filters.role" class="h-11 md:h-9">
                    <option value="">{{ __('All roles') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->code }}">{{ $role->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-branch">{{ __('Branch') }}</x-ui.field-label>
                <x-ui.select native id="filter-branch" wire:model.live="filters.branch" class="h-11 md:h-9">
                    <option value="">{{ __('All branches') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-active">{{ __('Status') }}</x-ui.field-label>
                <x-ui.select native id="filter-active" wire:model.live="filters.active" class="h-11 md:h-9">
                    <option value="">{{ __('All') }}</option>
                    <option value="1">{{ __('Active') }}</option>
                    <option value="0">{{ __('Inactive') }}</option>
                </x-ui.select>
            </x-ui.field>
        </x-slot:filters>

        <x-slot:desktop>
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="username" :label="__('Username')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Email') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Phone') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Roles') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Branch') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Active') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="last_login_at" :label="__('Last login')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head class="w-10"><span class="sr-only">{{ __('Actions') }}</span></x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $user)
                        <x-ui.table-row wire:key="user-{{ $user->id }}">
                            <x-ui.table-cell class="font-medium">
                                <a href="{{ route('admin.users.edit', $user) }}" wire:navigate class="hover:underline">{{ $user->name }}</a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->username }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->email }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->phone }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($user->roles as $role)
                                        <x-ui.badge variant="secondary">{{ $role->name }}</x-ui.badge>
                                    @endforeach
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->branch?->name }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge :tone="$user->is_active ? 'success' : 'neutral'">{{ $user->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->last_login_at?->format('d-M-Y H:i') ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.dropdown-menu>
                                    <x-ui.dropdown-menu-trigger>
                                        <x-ui.button variant="ghost" size="icon" :aria-label="__('Actions')"><x-lucide-ellipsis /></x-ui.button>
                                    </x-ui.dropdown-menu-trigger>
                                    <x-ui.dropdown-menu-content align="end">
                                        @can('admin.users.update')
                                            <x-ui.dropdown-menu-item :href="route('admin.users.edit', $user)" wire:navigate>{{ __('Edit') }}</x-ui.dropdown-menu-item>
                                        @endcan
                                        @can('admin.users.deactivate')
                                            <x-ui.dropdown-menu-item wire:click="toggleActive({{ $user->id }})">
                                                {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                            </x-ui.dropdown-menu-item>
                                        @endcan
                                    </x-ui.dropdown-menu-content>
                                </x-ui.dropdown-menu>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="9" class="py-10 text-center text-muted-foreground">{{ __('No users found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            <div class="mt-4 flex items-center justify-between gap-4">
                <x-ui.select native wire:model.live="perPage" class="w-36" :aria-label="__('Rows per page')">
                    @foreach (\App\Support\Listing\WithListing::PER_PAGE_OPTIONS as $option)
                        <option value="{{ $option }}">{{ __(':count per page', ['count' => $option]) }}</option>
                    @endforeach
                </x-ui.select>
                {{ $rows->links() }}
            </div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $user)
                <x-ui.item variant="outline" class="min-h-16 gap-2 py-2 pe-1" wire:key="m-user-{{ $user->id }}">
                    <a href="{{ route('admin.users.edit', $user) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3 rounded-md active:bg-accent">
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title class="flex items-center gap-2 text-base">
                                <span class="truncate">{{ $user->name }}</span>
                                <span @class(['size-2 shrink-0 rounded-full', 'bg-success' => $user->is_active, 'bg-muted-foreground' => ! $user->is_active])></span>
                            </x-ui.item-title>
                            <x-ui.item-description class="text-sm">{{ '@'.$user->username }}</x-ui.item-description>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($user->roles as $role)
                                    <x-ui.badge variant="secondary">{{ $role->name }}</x-ui.badge>
                                @endforeach
                            </div>
                        </x-ui.item-content>
                    </a>
                    <x-ui.button variant="ghost" size="icon" class="size-11" wire:click="openActions({{ $user->id }})" :aria-label="__('Actions')">
                        <x-lucide-ellipsis-vertical class="size-5" />
                    </x-ui.button>
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No users found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>

    <x-shell.sheet id="user-actions" :title="$actionUser?->name" :description="$actionUser ? '@'.$actionUser->username : null">
        @if ($actionUser)
            <div class="flex flex-col gap-2 pb-4">
                @can('admin.users.update')
                    <x-ui.button variant="outline" class="h-11 justify-start" :href="route('admin.users.edit', $actionUser)" wire:navigate>
                        <x-lucide-pencil /> {{ __('Edit') }}
                    </x-ui.button>
                @endcan
                @can('admin.users.deactivate')
                    <x-ui.button variant="outline" class="h-11 justify-start" wire:click="toggleActive({{ $actionUser->id }})">
                        <x-lucide-power /> {{ $actionUser->is_active ? __('Deactivate') : __('Activate') }}
                    </x-ui.button>
                @endcan
            </div>
        @endif
    </x-shell.sheet>
</div>
```

If any `x-ui.table*`, `dropdown-menu*` or `item-*` component is missing, add it with `php artisan blatui:add table dropdown-menu item --no-interaction`.

- [ ] **Step 6: Write the form component**

`app/Modules/Foundation/Livewire/Admin/Users/Form.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin\Users;

use App\Models\User;
use App\Modules\Foundation\Actions\CreateUser;
use App\Modules\Foundation\Actions\UpdateUser;
use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use App\Support\Facades\Lookup;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Form extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $phone = '';

    public ?int $branch_id = null;

    /** @var list<string> */
    public array $roles = [];

    /** @var list<string> */
    public array $permissions = [];

    public string $password = '';

    public string $password_confirmation = '';

    public bool $is_active = true;

    public function mount(?User $user = null): void
    {
        if ($user === null || ! $user->exists) {
            $this->authorize('admin.users.create');

            return;
        }

        $this->authorize('admin.users.update');

        $this->user = $user;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
        $this->branch_id = $user->branch_id;
        $this->is_active = $user->is_active;
        $this->roles = array_values(array_map(strval(...), $user->roles()->pluck('code')->all()));
        $this->permissions = array_values(array_map(strval(...), $user->directPermissions()->pluck('name')->all()));
    }

    public function save(CreateUser $createUser, UpdateUser $updateUser): void
    {
        $this->authorize($this->user === null ? 'admin.users.create' : 'admin.users.update');

        $input = $this->only(['name', 'username', 'email', 'phone', 'branch_id', 'roles', 'permissions', 'password', 'password_confirmation', 'is_active']);

        $this->user === null
            ? $createUser->handle($input, $this->actor())
            : $updateUser->handle($this->user, $input, $this->actor());

        session()->flash('success', $this->user === null ? __('User created.') : __('User saved.'));

        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.users.form', [
            'branches' => Lookup::options('branches', $this->branch_id),
            'availableRoles' => Role::query()->where('is_active', true)->orderBy('name')->get(['code', 'name', 'description']),
            'canGrantSuperAdmin' => $this->actor()->hasRole(Role::SUPER_ADMIN),
            'permissionGroups' => Permission::query()->orderBy('sort_order')->get()->groupBy('module'),
            'usernameUsed' => $this->user?->last_login_at !== null,
        ])
            ->title($this->user === null ? __('New user') : __('Edit user'))
            ->layoutData(['back' => route('admin.users.index'), 'bottomNav' => false]);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
```

`ValidationException` thrown by the Action inside `save()` is turned into field errors by Livewire, because the error keys match the property names.

- [ ] **Step 7: Write the form view**

`resources/views/livewire/admin/users/form.blade.php`:

```blade
<x-shell.form-page wire:submit="save" :cancel-url="route('admin.users.index')" :submit-label="$user ? __('Save changes') : __('Create user')">
    @error('user')
        <x-ui.alert variant="destructive"><x-ui.alert-description>{{ $message }}</x-ui.alert-description></x-ui.alert>
    @enderror

    <x-ui.field>
        <x-ui.field-label for="name">{{ __('Name') }} *</x-ui.field-label>
        <x-ui.input id="name" wire:model="name" autocomplete="name" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('name') ? 'true' : null" />
        <x-ui.field-error :messages="$errors->get('name')" />
    </x-ui.field>

    <x-ui.field>
        <x-ui.field-label for="username">{{ __('Username') }} *</x-ui.field-label>
        <x-ui.input id="username" wire:model="username" autocapitalize="none" autocomplete="off" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('username') ? 'true' : null" />
        @if ($usernameUsed)
            <x-ui.field-description>{{ __('This user has already signed in. Changing the username changes how they log in, and the change is recorded in the audit log.') }}</x-ui.field-description>
        @endif
        <x-ui.field-error :messages="$errors->get('username')" />
    </x-ui.field>

    <x-ui.field>
        <x-ui.field-label for="email">{{ __('Email') }}</x-ui.field-label>
        <x-ui.input id="email" type="email" inputmode="email" wire:model="email" autocapitalize="none" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('email') ? 'true' : null" />
        <x-ui.field-error :messages="$errors->get('email')" />
    </x-ui.field>

    <x-ui.field>
        <x-ui.field-label for="phone">{{ __('Phone') }}</x-ui.field-label>
        <x-ui.input id="phone" type="tel" inputmode="tel" wire:model="phone" placeholder="01XXXXXXXXX" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('phone') ? 'true' : null" />
        <x-ui.field-error :messages="$errors->get('phone')" />
    </x-ui.field>

    <x-ui.field>
        <x-ui.field-label for="branch_id">{{ __('Branch') }}</x-ui.field-label>
        <x-ui.select native id="branch_id" wire:model="branch_id" class="h-11 text-base md:h-9 md:text-sm">
            <option value="">{{ __('No branch') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.field-error :messages="$errors->get('branch_id')" />
    </x-ui.field>

    <x-ui.field-set>
        <x-ui.field-legend>{{ __('Roles') }} *</x-ui.field-legend>
        <div class="flex flex-col gap-2">
            @foreach ($availableRoles as $role)
                @php($locked = $role->code === \App\Modules\Foundation\Models\Role::SUPER_ADMIN && ! $canGrantSuperAdmin)
                <label class="flex min-h-11 items-center gap-3 rounded-md border px-3 py-2 has-[:checked]:border-primary {{ $locked ? 'opacity-50' : '' }}">
                    <x-ui.checkbox native wire:model="roles" value="{{ $role->code }}" :disabled="$locked" />
                    <span class="flex flex-col">
                        <span class="text-sm font-medium">{{ $role->name }}</span>
                        @if ($role->description)
                            <span class="text-sm text-muted-foreground">{{ $role->description }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
        <x-ui.field-error :messages="$errors->get('roles')" />
    </x-ui.field-set>

    <x-ui.collapsible>
        <x-ui.collapsible-trigger class="flex min-h-11 w-full items-center justify-between text-sm font-medium">
            {{ __('Extra permissions (:count)', ['count' => count($permissions)]) }}
            <x-lucide-chevron-down class="size-4" />
        </x-ui.collapsible-trigger>
        <x-ui.collapsible-content>
            <div class="flex flex-col gap-4 pt-2">
                @foreach ($permissionGroups as $module => $modulePermissions)
                    <div>
                        <p class="mb-1 text-sm font-semibold uppercase text-muted-foreground">{{ $module }}</p>
                        <div class="grid gap-1 md:grid-cols-2">
                            @foreach ($modulePermissions as $permission)
                                <label class="flex min-h-11 items-center gap-3 text-sm md:min-h-8">
                                    <x-ui.checkbox native wire:model="permissions" value="{{ $permission->name }}" />
                                    {{ $permission->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.collapsible-content>
    </x-ui.collapsible>

    <x-ui.field>
        <x-ui.field-label for="password">{{ __('Password') }} {{ $user ? '' : '*' }}</x-ui.field-label>
        <x-ui.input id="password" type="password" wire:model="password" autocomplete="new-password" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('password') ? 'true' : null" />
        <x-ui.field-description>
            {{ $user ? __('Leave blank to keep the current password. A new password must be changed at next login.') : __('The user must change it at first login.') }}
        </x-ui.field-description>
        <x-ui.field-error :messages="$errors->get('password')" />
    </x-ui.field>

    <x-ui.field>
        <x-ui.field-label for="password_confirmation">{{ __('Confirm password') }}</x-ui.field-label>
        <x-ui.input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="h-11 text-base md:h-9 md:text-sm" />
    </x-ui.field>

    <x-ui.field orientation="horizontal" class="min-h-11 items-center">
        <x-ui.switch id="is_active" wire:model="is_active" :checked="$is_active" />
        <x-ui.field-label for="is_active">{{ __('Active') }}</x-ui.field-label>
    </x-ui.field>
</x-shell.form-page>
```

Add any missing BlatUI components with `php artisan blatui:add alert collapsible field --no-interaction`.

- [ ] **Step 8: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/UsersScreenTest.php`
Expected: PASS.

If `->title()` or `->layoutData()` is undefined on the returned view in this Livewire version, use `#[Title]` plus `#[Layout('layouts::app', ['back' => ..., 'bottomNav' => false])]` attributes instead. Run `search-docs` with `["page layout data", "page title"]` to confirm which form this version uses.

- [ ] **Step 9: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add routes/modules/foundation.php && git commit -m "Add user admin routes"
git add app/Modules/Foundation/Livewire/Admin/Users/Index.php && git commit -m "Add users list component with filters, actions and export"
git add resources/views/livewire/admin/users/index.blade.php && git commit -m "Add users list view for desktop and mobile"
git add app/Modules/Foundation/Livewire/Admin/Users/Form.php && git commit -m "Add user form component"
git add resources/views/livewire/admin/users/form.blade.php && git commit -m "Add user form view"
git add tests/Feature/Foundation/Admin/UsersScreenTest.php && git commit -m "Test users screens and FD-AC-01"
```

---

### Task 9: Roles and the permission matrix

**Files:**
- Create: `app/Modules/Foundation/Actions/SaveRole.php`, `app/Modules/Foundation/Actions/DeleteRole.php`
- Create: `app/Modules/Foundation/Livewire/Admin/Roles/Index.php`, `app/Modules/Foundation/Livewire/Admin/Roles/Form.php`
- Create: `resources/views/livewire/admin/roles/index.blade.php`, `resources/views/livewire/admin/roles/form.blade.php`
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/RolesTest.php`

**Interfaces:**
- Consumes: `Role::syncPermissions(list<string>)` (clears the cache and audits); `WithListing`; the shell components.
- Produces:
  - `SaveRole::handle(array $input, ?Role $role = null): Role`, with input keys `name`, `code`, `description`, `is_active`, `permissions`.
  - `DeleteRole::handle(Role $role): void`.
  - Routes `admin.roles.index`, `admin.roles.create`, `admin.roles.edit` (`{role:code}`).
  - `Roles\Form` methods `toggleResource(string $module, string $resource)`, `toggleAction(string $module, string $action)`, `save()`.
  - `Roles\Index` methods `confirmDelete(int $roleId)`, `delete()`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/RolesTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Actions\DeleteRole;
use App\Modules\Foundation\Actions\SaveRole;
use App\Modules\Foundation\Livewire\Admin\Roles\Form;
use App\Modules\Foundation\Livewire\Admin\Roles\Index;
use App\Modules\Foundation\Models\Role;
use Livewire\Livewire;

beforeEach(function () {
    createPermissions('admin.users.view', 'admin.users.create', 'admin.roles.view');
    $this->actingAs(userWithPermissions('admin.roles.view', 'admin.roles.create', 'admin.roles.update', 'admin.roles.delete'));
});

test('saving a role syncs its permissions and takes effect immediately', function () {
    $role = app(SaveRole::class)->handle(['name' => 'Auditor', 'code' => 'auditor', 'is_active' => true, 'permissions' => ['admin.users.view']]);
    $member = User::factory()->create();
    $member->syncRoles(['auditor']);

    expect($member->can('admin.users.view'))->toBeTrue();

    app(SaveRole::class)->handle(['name' => 'Auditor', 'code' => 'auditor', 'is_active' => true, 'permissions' => ['admin.roles.view']], $role);

    expect($member->fresh()->can('admin.users.view'))->toBeFalse()
        ->and($member->fresh()->can('admin.roles.view'))->toBeTrue();
});

test('codes are snake case and unique', function () {
    ensureRole('auditor');

    expectValidationError(fn () => app(SaveRole::class)->handle(['name' => 'X', 'code' => 'Bad Code']), 'code');
    expectValidationError(fn () => app(SaveRole::class)->handle(['name' => 'X', 'code' => 'auditor']), 'code');
});

test('system role codes are locked and super admin is read-only', function () {
    $system = ensureRole('viewer', ['is_system' => true]);
    $super = ensureRole(Role::SUPER_ADMIN, ['is_system' => true]);

    expectValidationError(fn () => app(SaveRole::class)->handle(['name' => 'Viewer', 'code' => 'reader'], $system), 'code');
    expectValidationError(fn () => app(SaveRole::class)->handle(['name' => 'Boss', 'code' => Role::SUPER_ADMIN], $super), 'role');
});

test('system roles and roles in use cannot be deleted', function () {
    $system = ensureRole('viewer', ['is_system' => true]);
    $held = ensureRole('auditor');
    User::factory()->create()->syncRoles(['auditor']);
    $unused = ensureRole('temp');

    expectValidationError(fn () => app(DeleteRole::class)->handle($system), 'role');
    expectValidationError(fn () => app(DeleteRole::class)->handle($held), 'role');

    app(DeleteRole::class)->handle($unused);
    expect(Role::query()->where('code', 'temp')->exists())->toBeFalse();
});

test('the roles list needs admin.roles.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.roles.view'))->get(route('admin.roles.index'))->assertOk();
});

test('deleting from the list needs admin.roles.delete even when called directly', function () {
    $role = ensureRole('temp');

    Livewire::actingAs(userWithPermissions('admin.roles.view'))
        ->test(Index::class)
        ->call('confirmDelete', $role->id)
        ->call('delete')
        ->assertForbidden();
});

test('deleting a role in use shows an error toast', function () {
    $role = ensureRole('auditor');
    User::factory()->create()->syncRoles(['auditor']);

    Livewire::test(Index::class)
        ->call('confirmDelete', $role->id)
        ->call('delete')
        ->assertDispatched('toast', type: 'error');
});

test('the matrix toggles a whole resource row and a whole action column', function () {
    Livewire::test(Form::class)
        ->call('toggleResource', 'admin', 'users')
        ->assertSet('permissions', ['admin.users.view', 'admin.users.create'])
        ->call('toggleResource', 'admin', 'users')
        ->assertSet('permissions', [])
        ->call('toggleAction', 'admin', 'view')
        ->assertSet('permissions', ['admin.users.view', 'admin.roles.view']);
});

test('a role is created from the form', function () {
    Livewire::test(Form::class)
        ->set('name', 'Auditor')
        ->set('code', 'auditor')
        ->set('permissions', ['admin.users.view'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::query()->where('code', 'auditor')->first()->permissions()->pluck('name')->all())->toBe(['admin.users.view']);
});

test('the super admin role opens read-only', function () {
    ensureRole(Role::SUPER_ADMIN, ['is_system' => true]);

    $this->get(route('admin.roles.edit', Role::SUPER_ADMIN))
        ->assertOk()
        ->assertSee(__('Super admin has every permission and cannot be edited.'));
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/RolesTest.php`
Expected: FAIL with "Class ...SaveRole not found".

- [ ] **Step 3: Write the actions**

`app/Modules/Foundation/Actions/SaveRole.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Role;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a role and its permission grants (docs/01 §5.4).
 * super_admin is never edited; system role codes never change.
 */
class SaveRole
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?Role $role = null): Role
    {
        $role ??= new Role;

        if ($role->exists && $role->code === Role::SUPER_ADMIN) {
            throw ValidationException::withMessages(['role' => __('The super admin role cannot be edited.')]);
        }

        /** @var array{name: string, code: string, description?: string|null, is_active?: bool, permissions?: list<string>} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'code')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ])->validate();

        if ($role->exists && $role->is_system && $data['code'] !== $role->code) {
            throw ValidationException::withMessages(['code' => __('System role codes cannot be changed.')]);
        }

        return DB::transaction(function () use ($role, $data): Role {
            $role->fill(Arr::only($data, ['name', 'code', 'description']));
            $role->is_active = (bool) ($data['is_active'] ?? true);
            $role->save();

            $role->syncPermissions(array_values($data['permissions'] ?? []));

            return $role;
        });
    }
}
```

`app/Modules/Foundation/Actions/DeleteRole.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRole
{
    /**
     * @throws ValidationException
     */
    public function handle(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => __('System roles cannot be deleted.')]);
        }

        $holders = $role->users()->count();

        if ($holders > 0) {
            throw ValidationException::withMessages(['role' => trans_choice('Remove this role from its :count user first.|Remove this role from its :count users first.', $holders, ['count' => $holders])]);
        }

        DB::transaction(fn () => $role->delete());
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Livewire\Admin\Roles;`. Inside the group, add:

```php
    Route::livewire('roles', Roles\Index::class)->middleware('can:admin.roles.view')->name('roles.index');
    Route::livewire('roles/create', Roles\Form::class)->middleware('can:admin.roles.create')->name('roles.create');
    Route::livewire('roles/{role:code}/edit', Roles\Form::class)->middleware('can:admin.roles.update')->name('roles.edit');
```

- [ ] **Step 5: Write the list component and view**

`app/Modules/Foundation/Livewire/Admin/Roles/Index.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin\Roles;

use App\Modules\Foundation\Actions\DeleteRole;
use App\Modules\Foundation\Models\Role;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Roles')]
class Index extends Component
{
    use WithListing;

    public ?int $deletingRoleId = null;

    public function confirmDelete(int $roleId): void
    {
        $this->deletingRoleId = $roleId;
        $this->dispatch('open-sheet-role-delete');
    }

    public function delete(DeleteRole $deleteRole): void
    {
        $this->authorize('admin.roles.delete');

        $role = Role::query()->findOrFail($this->deletingRoleId);

        try {
            $deleteRole->handle($role);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->deletingRoleId = null;
        $this->dispatch('close-sheet-role-delete');
        $this->dispatch('toast', type: 'success', description: __('Role deleted.'));
    }

    /**
     * @return Builder<Role>
     */
    protected function listingQuery(): Builder
    {
        return Role::query()->withCount('users')->orderBy('name');
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    protected function sortColumns(): array
    {
        return ['name' => 'name', 'users' => 'users_count'];
    }

    public function render(): View
    {
        return view('livewire.admin.roles.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'deletingRole' => $this->deletingRoleId !== null ? Role::query()->find($this->deletingRoleId) : null,
        ]);
    }
}
```

`resources/views/livewire/admin/roles/index.blade.php`:

```blade
<div>
    <x-shell.list
        :search-placeholder="__('Search roles')"
        :create-url="auth()->user()->can('admin.roles.create') ? route('admin.roles.create') : null"
        :create-label="__('New role')"
        :has-more="$this->hasMoreRows"
    >
        <x-slot:desktop>
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Code') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="users" :label="__('Users')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                        <x-ui.table-head class="w-10"><span class="sr-only">{{ __('Actions') }}</span></x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $role)
                        <x-ui.table-row wire:key="role-{{ $role->id }}">
                            <x-ui.table-cell class="font-medium">
                                <a href="{{ route('admin.roles.edit', $role) }}" wire:navigate class="hover:underline">{{ $role->name }}</a>
                                @if ($role->is_system)
                                    <x-ui.badge tone="neutral" class="ms-2">{{ __('System') }}</x-ui.badge>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="font-mono text-sm">{{ $role->code }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $role->users_count }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge :tone="$role->is_active ? 'success' : 'neutral'">{{ $role->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if (! $role->is_system)
                                    @can('admin.roles.delete')
                                        <x-ui.button variant="ghost" size="icon" wire:click="confirmDelete({{ $role->id }})" :aria-label="__('Delete')"><x-lucide-trash-2 /></x-ui.button>
                                    @endcan
                                @endif
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="5" class="py-10 text-center text-muted-foreground">{{ __('No roles found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $role)
                <x-ui.item variant="outline" class="min-h-16 gap-2 py-2 pe-1" wire:key="m-role-{{ $role->id }}">
                    <a href="{{ route('admin.roles.edit', $role) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3 rounded-md active:bg-accent">
                        <x-ui.item-content>
                            <x-ui.item-title class="text-base">{{ $role->name }}</x-ui.item-title>
                            <x-ui.item-description class="text-sm">{{ trans_choice(':count user|:count users', $role->users_count, ['count' => $role->users_count]) }}</x-ui.item-description>
                        </x-ui.item-content>
                        <x-ui.badge :tone="$role->is_active ? 'success' : 'neutral'">{{ $role->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                    </a>
                    @if (! $role->is_system && auth()->user()->can('admin.roles.delete'))
                        <x-ui.button variant="ghost" size="icon" class="size-11" wire:click="confirmDelete({{ $role->id }})" :aria-label="__('Delete')"><x-lucide-trash-2 class="size-5" /></x-ui.button>
                    @else
                        <x-lucide-chevron-right class="size-5 text-muted-foreground" />
                    @endif
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No roles found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>

    <x-shell.sheet id="role-delete" :title="__('Delete role?')" :description="$deletingRole ? __('“:name” will be removed permanently.', ['name' => $deletingRole->name]) : null">
        <x-slot:footer>
            <x-ui.sheet-close><x-ui.button variant="outline" class="w-full">{{ __('Cancel') }}</x-ui.button></x-ui.sheet-close>
            <x-ui.button variant="destructive" wire:click="delete">{{ __('Delete') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
```

Run `php artisan blatui:add sheet --no-interaction` if `x-ui.sheet-close` is missing.

- [ ] **Step 6: Write the form component**

`app/Modules/Foundation/Livewire/Admin/Roles/Form.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin\Roles;

use App\Modules\Foundation\Actions\SaveRole;
use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class Form extends Component
{
    public ?Role $role = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public bool $is_active = true;

    /** @var list<string> */
    public array $permissions = [];

    public bool $readOnly = false;

    public function mount(?Role $role = null): void
    {
        if ($role === null || ! $role->exists) {
            $this->authorize('admin.roles.create');

            return;
        }

        $this->authorize('admin.roles.update');

        $this->role = $role;
        $this->readOnly = $role->code === Role::SUPER_ADMIN;
        $this->name = $role->name;
        $this->code = $role->code;
        $this->description = (string) $role->description;
        $this->is_active = $role->is_active;
        $this->permissions = array_values(array_map(strval(...), $role->permissions()->pluck('name')->all()));
    }

    public function toggleResource(string $module, string $resource): void
    {
        $this->toggle(Permission::query()->where('module', $module)->where('resource', $resource)->orderBy('sort_order')->orderBy('id')->pluck('name'));
    }

    public function toggleAction(string $module, string $action): void
    {
        $this->toggle(Permission::query()->where('module', $module)->where('action', $action)->orderBy('sort_order')->orderBy('id')->pluck('name'));
    }

    public function save(SaveRole $saveRole): void
    {
        $this->authorize($this->role === null ? 'admin.roles.create' : 'admin.roles.update');

        $saveRole->handle($this->only(['name', 'code', 'description', 'is_active', 'permissions']), $this->role);

        session()->flash('success', __('Role saved.'));

        $this->redirectRoute('admin.roles.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.roles.form', ['matrix' => $this->matrix()])
            ->title($this->role === null ? __('New role') : $this->role->name)
            ->layoutData(['back' => route('admin.roles.index'), 'bottomNav' => false]);
    }

    /**
     * Modules → their action columns and resource rows (action → permission name).
     *
     * @return array<string, array{actions: list<string>, resources: array<string, array<string, string>>}>
     */
    private function matrix(): array
    {
        return Permission::query()->orderBy('sort_order')->orderBy('id')->get()
            ->groupBy('module')
            ->map(fn (Collection $permissions): array => [
                'actions' => array_values($permissions->pluck('action')->unique()->all()),
                'resources' => $permissions->groupBy('resource')
                    ->map(fn (Collection $rows): array => $rows->pluck('name', 'action')->all())
                    ->all(),
            ])
            ->all();
    }

    /**
     * Select all of the given names, or clear them when they are already all selected.
     *
     * @param  Collection<int, mixed>  $names
     */
    private function toggle(Collection $names): void
    {
        $names = $names->map(fn (mixed $name): string => (string) $name)->all();

        $this->permissions = array_diff($names, $this->permissions) === []
            ? array_values(array_diff($this->permissions, $names))
            : array_values(array_unique([...$this->permissions, ...$names]));
    }
}
```

- [ ] **Step 7: Write the form view**

`resources/views/livewire/admin/roles/form.blade.php`:

```blade
<div>
    @if ($readOnly)
        <x-ui.alert>
            <x-lucide-shield-check />
            <x-ui.alert-title>{{ $name }}</x-ui.alert-title>
            <x-ui.alert-description>{{ __('Super admin has every permission and cannot be edited.') }}</x-ui.alert-description>
        </x-ui.alert>
    @else
        <x-shell.form-page wire:submit="save" :cancel-url="route('admin.roles.index')" :submit-label="__('Save role')">
            <x-ui.field>
                <x-ui.field-label for="name">{{ __('Name') }} *</x-ui.field-label>
                <x-ui.input id="name" wire:model="name" class="h-11 text-base md:h-9 md:text-sm" />
                <x-ui.field-error :messages="$errors->get('name')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="code">{{ __('Code') }} *</x-ui.field-label>
                <x-ui.input id="code" wire:model="code" autocapitalize="none" class="h-11 font-mono text-base md:h-9 md:text-sm" :disabled="$role?->is_system" />
                <x-ui.field-description>{{ __('Lowercase letters, numbers and underscores. Used by the system; cannot change on system roles.') }}</x-ui.field-description>
                <x-ui.field-error :messages="$errors->get('code')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="description">{{ __('Description') }}</x-ui.field-label>
                <x-ui.textarea id="description" wire:model="description" rows="2" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('description')" />
            </x-ui.field>

            <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                <x-ui.switch id="is_active" wire:model="is_active" :checked="$is_active" />
                <x-ui.field-label for="is_active">{{ __('Active') }}</x-ui.field-label>
            </x-ui.field>

            <div class="flex flex-col gap-2">
                <h2 class="text-base font-semibold">{{ __('Permissions') }}</h2>
                <x-ui.field-error :messages="$errors->get('role')" />

                {{-- Desktop matrix --}}
                <div class="hidden flex-col gap-6 md:flex">
                    @foreach ($matrix as $module => $group)
                        <div class="overflow-hidden rounded-md border" wire:key="matrix-{{ $module }}">
                            <x-ui.table>
                                <x-ui.table-header>
                                    <x-ui.table-row>
                                        <x-ui.table-head class="font-semibold uppercase">{{ $module }}</x-ui.table-head>
                                        @foreach ($group['actions'] as $action)
                                            <x-ui.table-head class="text-center">
                                                <button type="button" wire:click="toggleAction('{{ $module }}', '{{ $action }}')" class="hover:underline">{{ str_replace('_', ' ', $action) }}</button>
                                            </x-ui.table-head>
                                        @endforeach
                                    </x-ui.table-row>
                                </x-ui.table-header>
                                <x-ui.table-body>
                                    @foreach ($group['resources'] as $resource => $names)
                                        <x-ui.table-row>
                                            <x-ui.table-cell>
                                                <button type="button" wire:click="toggleResource('{{ $module }}', '{{ $resource }}')" class="hover:underline">{{ $resource !== '' ? str_replace('_', ' ', $resource) : $module }}</button>
                                            </x-ui.table-cell>
                                            @foreach ($group['actions'] as $action)
                                                <x-ui.table-cell class="text-center">
                                                    @isset($names[$action])
                                                        <x-ui.checkbox native wire:model="permissions" value="{{ $names[$action] }}" :aria-label="$names[$action]" />
                                                    @endisset
                                                </x-ui.table-cell>
                                            @endforeach
                                        </x-ui.table-row>
                                    @endforeach
                                </x-ui.table-body>
                            </x-ui.table>
                        </div>
                    @endforeach
                </div>

                {{-- Mobile: accordion per module, checkbox rows per action --}}
                <x-ui.accordion type="multiple" class="md:hidden">
                    @foreach ($matrix as $module => $group)
                        <x-ui.accordion-item value="{{ $module }}">
                            <x-ui.accordion-trigger class="min-h-11 text-base uppercase">{{ $module }}</x-ui.accordion-trigger>
                            <x-ui.accordion-content>
                                @foreach ($group['resources'] as $resource => $names)
                                    <div class="mb-3">
                                        <button type="button" wire:click="toggleResource('{{ $module }}', '{{ $resource }}')" class="min-h-11 text-sm font-semibold">
                                            {{ $resource !== '' ? str_replace('_', ' ', $resource) : $module }}
                                        </button>
                                        @foreach ($names as $action => $permissionName)
                                            <label class="flex min-h-11 items-center gap-3 text-sm">
                                                <x-ui.checkbox native wire:model="permissions" value="{{ $permissionName }}" />
                                                {{ str_replace('_', ' ', $action) }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            </x-ui.accordion-content>
                        </x-ui.accordion-item>
                    @endforeach
                </x-ui.accordion>
            </div>
        </x-shell.form-page>
    @endif
</div>
```

The spec says "switches" for mobile. This plan uses native checkbox rows, because `x-ui.switch` cannot bind to an array model. Note this deviation in the task report.

- [ ] **Step 8: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/RolesTest.php`
Expected: PASS. Permissions created by `createPermissions()` all have `sort_order` 0, so the `orderBy('id')` tie-break keeps them in insertion order.

- [ ] **Step 9: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Foundation/Actions/SaveRole.php && git commit -m "Add save role action with system role guards"
git add app/Modules/Foundation/Actions/DeleteRole.php && git commit -m "Add delete role action"
git add routes/modules/foundation.php && git commit -m "Add role admin routes"
git add app/Modules/Foundation/Livewire/Admin/Roles/Index.php && git commit -m "Add roles list component"
git add resources/views/livewire/admin/roles/index.blade.php && git commit -m "Add roles list view"
git add app/Modules/Foundation/Livewire/Admin/Roles/Form.php && git commit -m "Add role form with permission matrix toggles"
git add resources/views/livewire/admin/roles/form.blade.php && git commit -m "Add role form view with desktop matrix and mobile accordion"
git add tests/Feature/Foundation/Admin/RolesTest.php && git commit -m "Test role actions and screens"
```

---

### Task 10: Master data actions (save, delete, reorder)

**Files:**
- Create: `app/Modules/Foundation/Actions/SaveLookup.php`, `DeleteLookup.php`, `ReorderLookup.php`
- Modify: `config/lookups.php`: mark `currencies.symbol` and `currencies.decimal_places` with `'required' => true` (both columns are NOT NULL)
- Test: `tests/Feature/Foundation/Admin/LookupActionsTest.php`

**Interfaces:**
- Consumes: `LookupRegistry::get()` and `modelFor()` (Task 2).
- Produces:
  - `SaveLookup::handle(string $table, array $input, ?Model $row = null): Model`
  - `SaveLookup::COLORS = ['neutral', 'info', 'success', 'warning', 'danger']`
  - `DeleteLookup::handle(string $table, Model $row): void`
  - `ReorderLookup::handle(string $table, int $id, int $position): void`
- **Errors:** field keys (`code`, `is_active`, …) or `row`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/LookupActionsTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Actions\DeleteLookup;
use App\Modules\Foundation\Actions\ReorderLookup;
use App\Modules\Foundation\Actions\SaveLookup;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Currency;

test('a new row is appended at the end of the sort order', function () {
    Branch::factory()->create(['sort_order' => 5]);

    $branch = app(SaveLookup::class)->handle('branches', ['code' => 'CTG', 'name' => 'Chattogram', 'is_active' => true, 'is_head_office' => false]);

    expect($branch->sort_order)->toBe(6)->and($branch->is_system)->toBeFalse();
});

test('system rows keep their code and stay active (FD-BR-05)', function () {
    $row = Branch::factory()->create(['code' => 'HO', 'is_system' => true]);

    expectValidationError(fn () => app(SaveLookup::class)->handle('branches', ['code' => 'HQ', 'name' => 'HQ', 'is_active' => true], $row), 'code');
    expectValidationError(fn () => app(SaveLookup::class)->handle('branches', ['code' => 'HO', 'name' => 'HQ', 'is_active' => false], $row), 'is_active');
    expectValidationError(fn () => app(DeleteLookup::class)->handle('branches', $row), 'row');
});

test('rows in use cannot be deleted (FD-BR-06)', function () {
    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    expectValidationError(fn () => app(DeleteLookup::class)->handle('branches', $branch), 'row');
    expect($branch->fresh())->not->toBeNull();
});

test('setting a single flag clears it on the other rows', function () {
    $old = Currency::factory()->create(['is_base' => true]);
    $new = Currency::factory()->create(['is_base' => false]);

    app(SaveLookup::class)->handle('currencies', ['code' => $new->code, 'name' => $new->name, 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'is_base' => true], $new);

    expect($old->fresh()->is_base)->toBeFalse()->and($new->fresh()->is_base)->toBeTrue();
});

test('reorder moves a row and ignores ids from other tables', function () {
    [$a, $b, $c] = collect(['A', 'B', 'C'])->map(fn (string $code, int $i) => Branch::factory()->create(['code' => $code, 'sort_order' => $i + 1]))->all();

    app(ReorderLookup::class)->handle('branches', $c->id, 0);
    expect(Branch::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['C', 'A', 'B']);

    app(ReorderLookup::class)->handle('branches', $a->id, 99);
    expect(Branch::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['C', 'B', 'A']);

    $currency = Currency::factory()->create();
    expect(fn () => app(ReorderLookup::class)->handle('branches', $currency->id + 1000, 0))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/LookupActionsTest.php`
Expected: FAIL with "Class ...SaveLookup not found".

- [ ] **Step 3: Write the actions**

`app/Modules/Foundation/Actions/SaveLookup.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Support\Lookups\LookupRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a row in any registered lookup table (docs/01 §5.8, FD-BR-05).
 */
class SaveLookup
{
    public const COLORS = ['neutral', 'info', 'success', 'warning', 'danger'];

    public function __construct(private LookupRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(string $table, array $input, ?Model $row = null): Model
    {
        $entry = $this->registry->get($table);
        $row ??= $this->registry->modelFor($table);

        $rules = [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique($table, 'code')->ignore($row->getKey())],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(self::COLORS)],
            'is_active' => ['boolean'],
        ];

        foreach ($entry['extra_fields'] as $field => $definition) {
            $presence = ($definition['required'] ?? false) ? 'required' : 'nullable';

            $rules[$field] = match ($definition['type']) {
                'bool' => ['boolean'],
                'number' => [$presence, 'integer', 'min:0', 'max:65535'],
                'textarea' => [$presence, 'string', 'max:2000'],
                default => [$presence, 'string', 'max:255'],
            };
        }

        /** @var array<string, mixed> $data */
        $data = Validator::make($input, $rules)->validate();

        if ($row->exists && $row->getAttribute('is_system')) {
            if ($data['code'] !== $row->getAttribute('code')) {
                throw ValidationException::withMessages(['code' => __('System rows cannot have their code changed.')]);
            }

            if (! ($data['is_active'] ?? true)) {
                throw ValidationException::withMessages(['is_active' => __('System rows cannot be deactivated.')]);
            }
        }

        return DB::transaction(function () use ($row, $data, $entry): Model {
            $row->fill(Arr::only($data, ['code', 'name', 'description', 'color', 'is_active', ...array_keys($entry['extra_fields'])]));

            if (! $row->exists) {
                $row->setAttribute('sort_order', (int) $row->newQuery()->max('sort_order') + 1);
            }

            $row->save();

            foreach ($entry['single_flags'] ?? [] as $flag) {
                if ($row->getAttribute($flag)) {
                    $row->newQuery()->whereKeyNot($row->getKey())->where($flag, true)->get()
                        ->each(fn (Model $other) => $other->update([$flag => false]));
                }
            }

            return $row;
        });
    }
}
```

`app/Modules/Foundation/Actions/DeleteLookup.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an unused, non-system lookup row; rows referenced elsewhere must be deactivated (FD-BR-06).
 */
class DeleteLookup
{
    /**
     * @throws ValidationException
     */
    public function handle(string $table, Model $row): void
    {
        if ($row->getAttribute('is_system')) {
            throw ValidationException::withMessages(['row' => __('System rows cannot be deleted.')]);
        }

        try {
            DB::transaction(fn () => $row->delete());
        } catch (QueryException $exception) {
            if (str_starts_with((string) ($exception->errorInfo[0] ?? $exception->getCode()), '23')) {
                throw ValidationException::withMessages(['row' => __('In use — deactivate instead.')]);
            }

            throw $exception;
        }
    }
}
```

`app/Modules/Foundation/Actions/ReorderLookup.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Support\Lookups\LookupRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Moves a lookup row to a zero-based position (wire:sort) and renumbers sort_order 1..n.
 */
class ReorderLookup
{
    public function __construct(private LookupRegistry $registry) {}

    public function handle(string $table, int $id, int $position): void
    {
        $model = $this->registry->modelFor($table);
        $model->newQuery()->findOrFail($id);

        DB::transaction(function () use ($model, $id, $position): void {
            $ids = $model->newQuery()->orderBy('sort_order')->orderBy('name')->pluck('id')
                ->reject(fn (int $other): bool => $other === $id)->values()->all();

            array_splice($ids, max(0, min($position, count($ids))), 0, [$id]);

            foreach ($ids as $index => $rowId) {
                $model->newQuery()->whereKey($rowId)->update(['sort_order' => $index + 1]);
            }
        });
    }
}
```

In `config/lookups.php`, change `currencies` to `'symbol' => ['type' => 'text', 'label' => 'Symbol', 'required' => true]` and `'decimal_places' => ['type' => 'number', 'label' => 'Decimal places', 'required' => true]`. Also add `required?: bool` to the `LookupEntry` extra-field shape in `LookupRegistry`.

- [ ] **Step 4: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/LookupActionsTest.php tests/Feature/Foundation/LookupTest.php`
Expected: PASS.

- [ ] **Step 5: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Foundation/Actions/SaveLookup.php && git commit -m "Add save lookup action with system row and single flag rules"
git add app/Modules/Foundation/Actions/DeleteLookup.php && git commit -m "Refuse deleting lookup rows that are in use"
git add app/Modules/Foundation/Actions/ReorderLookup.php && git commit -m "Add lookup reorder action"
git add config/lookups.php app/Support/Lookups/LookupRegistry.php && git commit -m "Mark required currency extra fields"
git add tests/Feature/Foundation/Admin/LookupActionsTest.php && git commit -m "Test lookup save, delete and reorder rules"
```

---

### Task 11: Master data screen and branches entry point

**Files:**
- Create: `app/Modules/Foundation/Livewire/Admin/MasterData.php`
- Create: `resources/views/livewire/admin/master-data.blade.php`
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/MasterDataScreenTest.php`

**Interfaces:**
- Consumes: `LookupRegistry::visibleTo/allows/modelFor/get` (Task 2); `SaveLookup`, `DeleteLookup`, `ReorderLookup` (Task 10); `x-shell.sheet`.
- Produces:
  - Routes `admin.master-data.index` (`admin/master-data`), `admin.master-data.show` (`admin/master-data/{table}`) and `admin.branches.index` (a redirect to `admin/master-data/branches`).
  - Component methods: `create()`, `edit(int $id)`, `save()`, `delete()`, `sort(int $id, int $position)`.
  - Public state: `?string $table`, `?int $editingId`, `array $form`.

These routes carry no `can:` middleware, because the permission depends on the table. `mount()` aborts with 403 or 404 instead, and every action re-checks permission through `authorizeTable()`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/MasterDataScreenTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Currency;
use Livewire\Livewire;

test('master data needs a view permission for at least one table', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.master-data.index'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.branches.view'))->get(route('admin.master-data.index'))->assertOk()->assertSee('Branches');
});

test('unknown tables are 404 and tables without permission are 403', function () {
    $this->actingAs(userWithPermissions('admin.branches.view'));

    $this->get(route('admin.master-data.show', 'nope'))->assertNotFound();
    $this->get(route('admin.master-data.show', 'currencies'))->assertForbidden();
    $this->get(route('admin.master-data.show', 'branches'))->assertOk();
});

test('the branches nav item redirects to the branches table', function () {
    $this->actingAs(userWithPermissions('admin.branches.view'))
        ->get(route('admin.branches.index'))
        ->assertRedirect(route('admin.master-data.show', 'branches'));
});

test('a row is created through the sheet form', function () {
    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.create'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('create')
        ->assertDispatched('open-sheet-lookup-row')
        ->set('form.code', 'CTG')
        ->set('form.name', 'Chattogram')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('close-sheet-lookup-row');

    expect(Branch::query()->where('code', 'CTG')->exists())->toBeTrue();
});

test('rule failures show on the sheet fields', function () {
    $system = Branch::factory()->create(['code' => 'HO', 'is_system' => true]);

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('edit', $system->id)
        ->set('form.code', 'HQ')
        ->call('save')
        ->assertHasErrors(['form.code']);
});

test('deactivating through the form needs the deactivate permission', function () {
    $branch = Branch::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('edit', $branch->id)
        ->set('form.is_active', false)
        ->call('save')
        ->assertForbidden();
});

test('deleting a row in use shows an error toast', function () {
    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update', 'admin.branches.deactivate'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('edit', $branch->id)
        ->call('delete')
        ->assertDispatched('toast', type: 'error');
});

test('sorting needs update permission and a row from the open table', function () {
    $branch = Branch::factory()->create();
    $currency = Currency::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.branches.view'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('sort', $branch->id, 0)
        ->assertForbidden();

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('sort', $currency->id + 1000, 0)
        ->assertNotFound();
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/MasterDataScreenTest.php`
Expected: FAIL with "Route [admin.master-data.index] not defined".

- [ ] **Step 3: Add the routes**

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Livewire\Admin\MasterData;`. Inside the admin group, add:

```php
    Route::livewire('master-data', MasterData::class)->name('master-data.index');
    Route::livewire('master-data/{table}', MasterData::class)->name('master-data.show');
    Route::redirect('branches', '/admin/master-data/branches')->middleware('can:admin.branches.view')->name('branches.index');
```

- [ ] **Step 4: Write the component**

`app/Modules/Foundation/Livewire/Admin/MasterData.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Models\User;
use App\Modules\Foundation\Actions\DeleteLookup;
use App\Modules\Foundation\Actions\ReorderLookup;
use App\Modules\Foundation\Actions\SaveLookup;
use App\Support\Lookups\LookupRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Generic editor for every table in config/lookups.php (docs/01 §5.8).
 */
#[Title('Master data')]
class MasterData extends Component
{
    public ?string $table = null;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(?string $table = null): void
    {
        $registry = $this->registry();

        if ($table === null) {
            abort_if($registry->visibleTo($this->actor()) === [], 403);

            return;
        }

        abort_unless(array_key_exists($table, $registry->all()), 404);
        abort_unless($registry->allows($this->actor(), $table, 'view'), 403);

        $this->table = $table;
    }

    public function create(): void
    {
        $this->authorizeTable('create');

        $this->editingId = null;
        $this->form = $this->blankForm();
        $this->resetErrorBag();
        $this->dispatch('open-sheet-lookup-row');
    }

    public function edit(int $id): void
    {
        $this->authorizeTable('view');

        $row = $this->findRow($id);
        $this->editingId = $row->getKey();
        $this->form = array_merge($this->blankForm(), $row->only(array_keys($this->blankForm())));
        $this->resetErrorBag();
        $this->dispatch('open-sheet-lookup-row');
    }

    public function save(SaveLookup $saveLookup): void
    {
        $row = $this->editingId !== null ? $this->findRow($this->editingId) : null;

        $this->authorizeTable($row === null ? 'create' : 'update');

        if ($row !== null && (bool) $row->getAttribute('is_active') !== (bool) ($this->form['is_active'] ?? true)) {
            $this->authorizeTable('deactivate');
        }

        try {
            $saveLookup->handle((string) $this->table, $this->form, $row);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ['form.'.$key => $messages])->all(),
            );
        }

        $this->dispatch('close-sheet-lookup-row');
        $this->dispatch('toast', type: 'success', description: __('Saved.'));
    }

    public function delete(DeleteLookup $deleteLookup): void
    {
        $this->authorizeTable('deactivate');

        try {
            $deleteLookup->handle((string) $this->table, $this->findRow((int) $this->editingId));
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->editingId = null;
        $this->dispatch('close-sheet-lookup-row');
        $this->dispatch('toast', type: 'success', description: __('Deleted.'));
    }

    public function sort(int $id, int $position, ReorderLookup $reorderLookup): void
    {
        $this->authorizeTable('update');
        $this->findRow($id);

        $reorderLookup->handle((string) $this->table, $id, $position);
    }

    public function render(): View
    {
        $registry = $this->registry();
        $visible = $registry->visibleTo($this->actor());

        return view('livewire.admin.master-data', [
            'groups' => collect($visible)->groupBy('module', preserveKeys: true),
            'entry' => $this->table !== null ? $registry->get($this->table) : null,
            'rows' => $this->table !== null ? $registry->modelFor($this->table)->newQuery()->orderBy('sort_order')->orderBy('name')->get() : collect(),
            'colors' => SaveLookup::COLORS,
            'can' => fn (string $action): bool => $this->table !== null && $registry->allows($this->actor(), $this->table, $action),
        ])->layoutData(['back' => $this->table !== null ? route('admin.master-data.index') : null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        $form = ['code' => '', 'name' => '', 'description' => '', 'color' => '', 'is_active' => true];

        foreach ($this->registry()->get((string) $this->table)['extra_fields'] as $field => $definition) {
            $form[$field] = $definition['type'] === 'bool' ? false : '';
        }

        return $form;
    }

    private function findRow(int $id): Model
    {
        $row = $this->registry()->modelFor((string) $this->table)->newQuery()->find($id);
        abort_if($row === null, 404);

        return $row;
    }

    private function authorizeTable(string $action): void
    {
        abort_unless($this->table !== null && $this->registry()->allows($this->actor(), $this->table, $action), 403);
    }

    private function registry(): LookupRegistry
    {
        return app(LookupRegistry::class);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
```

`SaveLookup` validates `color` against `Rule::in`, and blank strings must be stored as null. In `SaveLookup::handle()`, before validation, add:

```php
        $input = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $input);
```

Then `code` and `name` fail as "required" when blank, as they should.

- [ ] **Step 5: Write the view**

`resources/views/livewire/admin/master-data.blade.php`:

```blade
<div class="flex flex-col gap-4 md:grid md:grid-cols-[14rem_1fr] md:gap-6">
    {{-- Table list: always on desktop, only when no table is open on mobile --}}
    <nav @class(['flex flex-col gap-4', 'max-md:hidden' => $table !== null]) aria-label="{{ __('Lookup tables') }}">
        @foreach ($groups as $module => $tables)
            <x-ui.item-group class="gap-1">
                <p class="px-1 text-sm font-medium uppercase text-muted-foreground">{{ $module }}</p>
                @foreach ($tables as $key => $definition)
                    <x-ui.item size="sm" :href="route('admin.master-data.show', $key)" wire:navigate
                        @class(['min-h-11 active:bg-accent', 'bg-accent' => $key === $table])>
                        <span class="flex-1 text-sm">{{ __($definition['label']) }}</span>
                        <x-lucide-chevron-right class="size-4 text-muted-foreground md:hidden" />
                    </x-ui.item>
                @endforeach
            </x-ui.item-group>
        @endforeach
    </nav>

    <section @class(['flex flex-col gap-4', 'max-md:hidden' => $table === null])>
        @if ($entry === null)
            <x-ui.empty class="hidden md:flex">
                <x-ui.empty-header>
                    <x-ui.empty-title>{{ __('Choose a table') }}</x-ui.empty-title>
                    <x-ui.empty-description>{{ __('Pick a lookup table on the left to edit its rows.') }}</x-ui.empty-description>
                </x-ui.empty-header>
            </x-ui.empty>
        @else
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ __($entry['label']) }}</h2>
                @if ($can('create'))
                    <x-ui.button class="hidden md:inline-flex" wire:click="create"><x-lucide-plus /> {{ __('Add row') }}</x-ui.button>
                @endif
            </div>

            <div class="flex flex-col gap-2" @if ($can('update')) wire:sort="sort" @endif>
                @forelse ($rows as $row)
                    <x-ui.item variant="outline" class="min-h-14 gap-2 py-2 ps-1" wire:key="row-{{ $row->id }}" wire:sort:item="{{ $row->id }}">
                        @if ($can('update'))
                            <span wire:sort:handle class="flex size-11 cursor-grab touch-none items-center justify-center text-muted-foreground" aria-label="{{ __('Drag to reorder') }}">
                                <x-lucide-grip-vertical class="size-5" />
                            </span>
                        @endif
                        <button type="button" wire:click="edit({{ $row->id }})" class="flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-md px-2 text-start active:bg-accent">
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="flex items-center gap-2 text-base font-medium md:text-sm">
                                    <span class="truncate">{{ $row->name }}</span>
                                    @if ($row->color)
                                        <x-ui.badge :tone="$row->color">{{ $row->code }}</x-ui.badge>
                                    @else
                                        <span class="font-mono text-sm text-muted-foreground">{{ $row->code }}</span>
                                    @endif
                                    @if ($row->is_system)
                                        <x-lucide-lock class="size-3.5 text-muted-foreground" :aria-label="__('System row')" />
                                    @endif
                                </span>
                                @if ($row->description)
                                    <span class="truncate text-sm text-muted-foreground">{{ $row->description }}</span>
                                @endif
                            </span>
                            @unless ($row->is_active)
                                <x-ui.badge tone="neutral">{{ __('Inactive') }}</x-ui.badge>
                            @endunless
                            <x-lucide-chevron-right class="size-4 text-muted-foreground" />
                        </button>
                    </x-ui.item>
                @empty
                    <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No rows yet.') }}</p>
                @endforelse
            </div>

            @if ($can('create'))
                <button type="button" wire:click="create" aria-label="{{ __('Add row') }}"
                    class="fixed end-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg active:scale-95 md:hidden">
                    <x-lucide-plus class="size-6" />
                </button>
            @endif
        @endif
    </section>

    @if ($entry !== null)
        <x-shell.sheet id="lookup-row" :title="$editingId ? __('Edit row') : __('Add row')">
            <form wire:submit="save" id="lookup-row-form" class="flex flex-col gap-4 pb-2">
                @php($isSystem = $editingId && $rows->firstWhere('id', $editingId)?->is_system)

                <x-ui.field>
                    <x-ui.field-label for="form-code">{{ __('Code') }} *</x-ui.field-label>
                    <x-ui.input id="form-code" wire:model="form.code" autocapitalize="characters" class="h-11 font-mono text-base md:h-9 md:text-sm" :disabled="$isSystem" />
                    <x-ui.field-error :messages="$errors->get('form.code')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="form-name">{{ __('Name') }} *</x-ui.field-label>
                    <x-ui.input id="form-name" wire:model="form.name" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('form.name')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="form-description">{{ __('Description') }}</x-ui.field-label>
                    <x-ui.input id="form-description" wire:model="form.description" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('form.description')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="form-color">{{ __('Badge colour') }}</x-ui.field-label>
                    <x-ui.select native id="form-color" wire:model="form.color" class="h-11 text-base md:h-9 md:text-sm">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($colors as $color)
                            <option value="{{ $color }}">{{ ucfirst($color) }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('form.color')" />
                </x-ui.field>

                @foreach ($entry['extra_fields'] as $field => $definition)
                    @if ($definition['type'] === 'bool')
                        <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                            <x-ui.switch id="form-{{ $field }}" wire:model="form.{{ $field }}" :checked="(bool) ($form[$field] ?? false)" />
                            <x-ui.field-label for="form-{{ $field }}">{{ __($definition['label']) }}</x-ui.field-label>
                        </x-ui.field>
                    @else
                        <x-ui.field>
                            <x-ui.field-label for="form-{{ $field }}">{{ __($definition['label']) }}{{ ($definition['required'] ?? false) ? ' *' : '' }}</x-ui.field-label>
                            @if ($definition['type'] === 'textarea')
                                <x-ui.textarea id="form-{{ $field }}" wire:model="form.{{ $field }}" rows="3" class="text-base md:text-sm" />
                            @else
                                <x-ui.input id="form-{{ $field }}" wire:model="form.{{ $field }}" :type="$definition['type'] === 'number' ? 'number' : 'text'" :inputmode="$definition['type'] === 'number' ? 'numeric' : null" class="h-11 text-base md:h-9 md:text-sm" />
                            @endif
                            <x-ui.field-error :messages="$errors->get('form.'.$field)" />
                        </x-ui.field>
                    @endif
                @endforeach

                <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                    <x-ui.switch id="form-is_active" wire:model="form.is_active" :checked="(bool) ($form['is_active'] ?? true)" :disabled="$isSystem || ! $can('deactivate')" />
                    <x-ui.field-label for="form-is_active">{{ __('Active') }}</x-ui.field-label>
                </x-ui.field>
                <x-ui.field-error :messages="$errors->get('form.is_active')" />
            </form>

            <x-slot:footer>
                @if ($editingId && ! $isSystem && $can('deactivate'))
                    <x-ui.button variant="outline" class="text-destructive" wire:click="delete" wire:confirm="{{ __('Delete this row? Rows in use cannot be deleted.') }}">{{ __('Delete') }}</x-ui.button>
                @endif
                <x-ui.button type="submit" form="lookup-row-form">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endif
</div>
```

`wire:confirm` shows the browser's native confirm dialog. The mobile rules prefer bottom sheets for confirmations. Because the delete button already sits inside a bottom sheet, this uses `wire:confirm` as a second guard instead of stacking a second sheet. Note it in the task report.

If `x-ui.empty*` or `x-ui.textarea` is missing, run `php artisan blatui:add empty textarea --no-interaction`.

- [ ] **Step 6: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/MasterDataScreenTest.php tests/Feature/Foundation/Admin/LookupActionsTest.php`
Expected: PASS.

- [ ] **Step 7: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add routes/modules/foundation.php && git commit -m "Add master data and branches routes"
git add app/Modules/Foundation/Livewire/Admin/MasterData.php && git commit -m "Add generic master data editor component"
git add resources/views/livewire/admin/master-data.blade.php && git commit -m "Add master data view with sortable rows and edit sheet"
git add app/Modules/Foundation/Actions/SaveLookup.php && git commit -m "Treat blank lookup inputs as null"
git add tests/Feature/Foundation/Admin/MasterDataScreenTest.php && git commit -m "Test master data screen access, editing and sorting"
```

---

### Task 12: Locations tree screen

**Files:**
- Create: `app/Modules/Foundation/Livewire/Admin/Locations.php`
- Create: `resources/views/livewire/admin/locations.blade.php`, `resources/views/livewire/admin/locations/node.blade.php`
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/LocationsScreenTest.php`

**Interfaces:**
- Consumes: `SaveLocation`, `SetLocationActive` (Task 3); `x-shell.sheet`.
- Produces:
  - Route `admin.locations.index` (`admin/locations`, `can:admin.locations.view`).
  - Methods `toggle(int $id)`, `addChild(?int $parentId)`, `edit(int $id)`, `save()`, `toggleActive(int $id)`.
  - Public state `list<int> $expanded`, `string $search`, `?int $editingId`, `?int $parent_id`, `string $name`, `string $name_bn`.
- **Children load lazily:** only the children of ids in `$expanded` are queried.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/LocationsScreenTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Actions\SaveLocation;
use App\Modules\Foundation\Livewire\Admin\Locations;
use App\Modules\Foundation\Models\Location;
use Database\Seeders\Foundation\LocationLevelSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LocationLevelSeeder::class);
    $this->division = app(SaveLocation::class)->handle(['name' => 'Dhaka']);
    $this->district = app(SaveLocation::class)->handle(['name' => 'Gazipur', 'parent_id' => $this->division->id]);
});

test('locations need admin.locations.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.locations.index'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.locations.view'))->get(route('admin.locations.index'))->assertOk()->assertSee('Dhaka');
});

test('children appear only once their parent is expanded', function () {
    Livewire::actingAs(userWithPermissions('admin.locations.view'))
        ->test(Locations::class)
        ->assertDontSee('Gazipur')
        ->call('toggle', $this->division->id)
        ->assertSee('Gazipur');
});

test('search lists matches with their full path', function () {
    Livewire::actingAs(userWithPermissions('admin.locations.view'))
        ->test(Locations::class)
        ->set('search', 'gazi')
        ->assertSee('Dhaka › Gazipur');
});

test('adding a child and renaming go through the sheet', function () {
    $component = Livewire::actingAs(userWithPermissions('admin.locations.view', 'admin.locations.create', 'admin.locations.update'))
        ->test(Locations::class)
        ->call('addChild', $this->district->id)
        ->assertDispatched('open-sheet-location')
        ->set('name', 'Kaliakair')
        ->call('save')
        ->assertHasNoErrors();

    $thana = Location::query()->where('name', 'Kaliakair')->firstOrFail();
    expect($thana->full_path)->toBe('Dhaka › Gazipur › Kaliakair');

    $component->call('edit', $this->district->id)->set('name', 'Gazipur City')->call('save')->assertHasNoErrors();
    expect($thana->fresh()->full_path)->toBe('Dhaka › Gazipur City › Kaliakair');
});

test('duplicate names show on the name field', function () {
    Livewire::actingAs(userWithPermissions('admin.locations.view', 'admin.locations.create'))
        ->test(Locations::class)
        ->call('addChild', $this->division->id)
        ->set('name', 'GAZIPUR')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('activating and deactivating needs admin.locations.deactivate', function () {
    Livewire::actingAs(userWithPermissions('admin.locations.view', 'admin.locations.update'))
        ->test(Locations::class)
        ->call('toggleActive', $this->district->id)
        ->assertForbidden();

    Livewire::actingAs(userWithPermissions('admin.locations.view', 'admin.locations.deactivate'))
        ->test(Locations::class)
        ->call('toggleActive', $this->district->id);

    expect($this->district->fresh()->is_active)->toBeFalse();
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/LocationsScreenTest.php`
Expected: FAIL with "Route [admin.locations.index] not defined".

- [ ] **Step 3: Add the route**

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Livewire\Admin\Locations;`. Inside the group, add:

```php
    Route::livewire('locations', Locations::class)->middleware('can:admin.locations.view')->name('locations.index');
```

- [ ] **Step 4: Write the component**

`app/Modules/Foundation/Livewire/Admin/Locations.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Actions\SaveLocation;
use App\Modules\Foundation\Actions\SetLocationActive;
use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Division → district → thana → area tree (docs/01 §5.9). Children load when a node expands.
 */
#[Title('Locations')]
class Locations extends Component
{
    /** @var list<int> */
    public array $expanded = [];

    public string $search = '';

    public ?int $editingId = null;

    public ?int $parent_id = null;

    public string $name = '';

    public string $name_bn = '';

    public function mount(): void
    {
        $this->authorize('admin.locations.view');
    }

    public function toggle(int $id): void
    {
        $this->expanded = in_array($id, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$id]))
            : [...$this->expanded, $id];
    }

    public function addChild(?int $parentId = null): void
    {
        $this->authorize('admin.locations.create');

        $this->reset('editingId', 'name', 'name_bn');
        $this->parent_id = $parentId;
        $this->resetErrorBag();
        $this->dispatch('open-sheet-location');
    }

    public function edit(int $id): void
    {
        $this->authorize('admin.locations.update');

        $location = Location::query()->findOrFail($id);
        $this->editingId = $location->id;
        $this->parent_id = $location->parent_id;
        $this->name = $location->name;
        $this->name_bn = (string) $location->name_bn;
        $this->resetErrorBag();
        $this->dispatch('open-sheet-location');
    }

    public function save(SaveLocation $saveLocation): void
    {
        if ($this->editingId === null) {
            $this->authorize('admin.locations.create');
            $saveLocation->handle(['name' => $this->name, 'name_bn' => $this->name_bn ?: null, 'parent_id' => $this->parent_id]);

            if ($this->parent_id !== null && ! in_array($this->parent_id, $this->expanded, true)) {
                $this->expanded[] = $this->parent_id;
            }
        } else {
            $this->authorize('admin.locations.update');
            $saveLocation->handle(['name' => $this->name, 'name_bn' => $this->name_bn ?: null], Location::query()->findOrFail($this->editingId));
        }

        $this->dispatch('close-sheet-location');
        $this->dispatch('toast', type: 'success', description: __('Location saved.'));
    }

    public function toggleActive(int $id, SetLocationActive $setLocationActive): void
    {
        $this->authorize('admin.locations.deactivate');

        $location = Location::query()->findOrFail($id);
        $setLocationActive->handle($location, ! $location->is_active);
    }

    public function render(): View
    {
        $term = trim($this->search);

        return view('livewire.admin.locations', [
            'roots' => Location::query()->whereNull('parent_id')->withCount('children')->orderBy('name')->get(),
            'childrenByParent' => Location::query()->whereIn('parent_id', $this->expanded)->withCount('children')->orderBy('name')->get()->groupBy('parent_id'),
            'results' => mb_strlen($term) >= 2
                ? Location::query()
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($term)).'%'])
                    ->orderBy('full_path')->limit(50)->get()
                : null,
            'parentPath' => $this->parent_id !== null ? Location::query()->whereKey($this->parent_id)->value('full_path') : null,
            'areaLevelId' => LocationLevel::query()->where('code', LocationLevel::AREA)->value('id'),
        ]);
    }
}
```

- [ ] **Step 5: Write the views**

`resources/views/livewire/admin/locations.blade.php`:

```blade
<div class="flex flex-col gap-4">
    <div class="flex items-center gap-2">
        <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Search locations')" class="h-11 flex-1 text-base md:h-9 md:max-w-xs md:text-sm" />
        @can('admin.locations.create')
            <x-ui.button class="hidden md:inline-flex" wire:click="addChild"><x-lucide-plus /> {{ __('Add division') }}</x-ui.button>
        @endcan
    </div>

    @if ($results !== null)
        <x-ui.item-group class="gap-2">
            @forelse ($results as $location)
                <x-ui.item variant="outline" class="min-h-14" wire:key="result-{{ $location->id }}">
                    <x-ui.item-content>
                        <x-ui.item-title class="text-base md:text-sm">{{ $location->name }}</x-ui.item-title>
                        <x-ui.item-description class="text-sm">{{ $location->full_path }}</x-ui.item-description>
                    </x-ui.item-content>
                    @can('admin.locations.update')
                        <x-ui.button variant="ghost" size="icon" class="size-11" wire:click="edit({{ $location->id }})" :aria-label="__('Edit')"><x-lucide-pencil class="size-5" /></x-ui.button>
                    @endcan
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No locations match.') }}</p>
            @endforelse
        </x-ui.item-group>
    @else
        <ul class="flex flex-col gap-1" role="tree">
            @foreach ($roots as $node)
                @include('livewire.admin.locations.node', ['node' => $node, 'depth' => 0])
            @endforeach
        </ul>
    @endif

    @can('admin.locations.create')
        <button type="button" wire:click="addChild" aria-label="{{ __('Add division') }}"
            class="fixed end-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg active:scale-95 md:hidden">
            <x-lucide-plus class="size-6" />
        </button>
    @endcan

    <x-shell.sheet id="location" :title="$editingId ? __('Edit location') : __('Add location')" :description="$parentPath ? __('Under :path', ['path' => $parentPath]) : null">
        <form wire:submit="save" id="location-form" class="flex flex-col gap-4 pb-2">
            <x-ui.field>
                <x-ui.field-label for="location-name">{{ __('Name') }} *</x-ui.field-label>
                <x-ui.input id="location-name" wire:model="name" class="h-11 text-base md:h-9 md:text-sm" />
                <x-ui.field-error :messages="$errors->get('name')" />
                <x-ui.field-error :messages="$errors->get('parent_id')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="location-name-bn">{{ __('Name (Bangla)') }}</x-ui.field-label>
                <x-ui.input id="location-name-bn" wire:model="name_bn" lang="bn" class="h-11 text-base md:h-9 md:text-sm" />
                <x-ui.field-error :messages="$errors->get('name_bn')" />
            </x-ui.field>
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="location-form">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
```

`resources/views/livewire/admin/locations/node.blade.php`:

```blade
@php($isOpen = in_array($node->id, $expanded, true))
<li role="treeitem" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" wire:key="node-{{ $node->id }}">
    <div class="flex min-h-11 items-center gap-1 rounded-md pe-1 hover:bg-accent/50" style="padding-inline-start: {{ $depth * 1.25 }}rem">
        @if ($node->children_count > 0)
            <button type="button" wire:click="toggle({{ $node->id }})" class="flex size-11 items-center justify-center" aria-label="{{ $isOpen ? __('Collapse') : __('Expand') }}">
                <x-dynamic-component :component="$isOpen ? 'lucide-chevron-down' : 'lucide-chevron-right'" class="size-4" />
            </button>
        @else
            <span class="size-11"></span>
        @endif

        <span @class(['flex-1 truncate text-base md:text-sm', 'text-muted-foreground line-through' => ! $node->is_active])>{{ $node->name }}</span>

        @if ($node->location_level_id !== $areaLevelId)
            @can('admin.locations.create')
                <x-ui.button variant="ghost" size="icon" class="size-11 md:size-8" wire:click="addChild({{ $node->id }})" :aria-label="__('Add child')"><x-lucide-plus /></x-ui.button>
            @endcan
        @endif
        @can('admin.locations.update')
            <x-ui.button variant="ghost" size="icon" class="size-11 md:size-8" wire:click="edit({{ $node->id }})" :aria-label="__('Edit')"><x-lucide-pencil /></x-ui.button>
        @endcan
        @can('admin.locations.deactivate')
            <x-ui.button variant="ghost" size="icon" class="size-11 md:size-8" wire:click="toggleActive({{ $node->id }})" :aria-label="$node->is_active ? __('Deactivate') : __('Activate')">
                <x-dynamic-component :component="$node->is_active ? 'lucide-eye-off' : 'lucide-eye'" />
            </x-ui.button>
        @endcan
    </div>

    @if ($isOpen)
        <ul role="group" class="flex flex-col gap-1">
            @foreach ($childrenByParent->get($node->id, collect()) as $child)
                @include('livewire.admin.locations.node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
```

`$expanded`, `$childrenByParent` and `$areaLevelId` reach the partial because `@include` inherits the parent view's variables.

- [ ] **Step 6: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/LocationsScreenTest.php`
Expected: PASS.

- [ ] **Step 7: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add routes/modules/foundation.php && git commit -m "Add locations route"
git add app/Modules/Foundation/Livewire/Admin/Locations.php && git commit -m "Add locations tree component with lazy children and search"
git add resources/views/livewire/admin/locations.blade.php && git commit -m "Add locations view with search and edit sheet"
git add resources/views/livewire/admin/locations/node.blade.php && git commit -m "Add recursive location tree node"
git add tests/Feature/Foundation/Admin/LocationsScreenTest.php && git commit -m "Test locations screen"
```

---

### Task 13: Company profile and print letterhead

**Files:**
- Create: `app/Modules/Foundation/Actions/UpdateCompanyProfile.php`
- Create: `app/Modules/Foundation/Livewire/Admin/Company.php`, `resources/views/livewire/admin/company.blade.php`
- Create: `resources/views/components/print/letterhead.blade.php`
- Modify: `resources/views/components/shell/form-page.blade.php` (a `null` submit label hides the actions)
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/CompanyProfileTest.php`

**Interfaces:**
- Produces:
  - `UpdateCompanyProfile::handle(array $input, ?UploadedFile $logo = null): CompanyProfile`. The logo is stored on the `public` disk under `company/`, and the old file is deleted.
  - `<x-print.letterhead :company="$company" />` (FD-AC-08), reused by every later print.
  - Route `admin.company.edit` (`admin/company`, `can:admin.company.view`).
  - `<x-shell.form-page :submit-label="null">` renders no action bars.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/CompanyProfileTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\Company;
use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\CompanyProfile;
use Database\Seeders\Foundation\CompanyProfileSeeder;
use Database\Seeders\Foundation\CurrencySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CurrencySeeder::class, CompanyProfileSeeder::class]);
    Storage::fake('public');
});

test('the company page needs admin.company.view and is read-only without update', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.company.edit'))->assertForbidden();

    $this->actingAs(userWithPermissions('admin.company.view'))
        ->get(route('admin.company.edit'))
        ->assertOk()
        ->assertSee('SOC Consultant')
        ->assertDontSee(__('Save company profile'));
});

test('saving updates the profile, audits it and shows TIN and BIN on the letterhead', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view', 'admin.company.update'))
        ->test(Company::class)
        ->set('tin', '123456789012')
        ->set('bin', '000111222-0101')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('123456789012')
        ->assertSee('000111222-0101');

    expect(CompanyProfile::current()->tin)->toBe('123456789012')
        ->and(AuditLog::query()->where('auditable_type', 'company')->where('event', 'updated')->exists())->toBeTrue();
});

test('a new logo replaces the old file', function () {
    $component = Livewire::actingAs(userWithPermissions('admin.company.view', 'admin.company.update'))->test(Company::class);

    $component->set('logo', UploadedFile::fake()->image('logo.png', 200, 80))->call('save')->assertHasNoErrors();
    $first = CompanyProfile::current()->logo_path;
    Storage::disk('public')->assertExists($first);

    $component->set('logo', UploadedFile::fake()->image('logo2.jpg', 200, 80))->call('save')->assertHasNoErrors();
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists(CompanyProfile::current()->logo_path);
});

test('logos must be png or jpg up to 1 MB', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view', 'admin.company.update'))
        ->test(Company::class)
        ->set('logo', UploadedFile::fake()->create('logo.png', 2048, 'image/png'))
        ->call('save')
        ->assertHasErrors(['logo']);
});

test('saving without update permission is forbidden', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view'))->test(Company::class)->call('save')->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/CompanyProfileTest.php`
Expected: FAIL with "Route [admin.company.edit] not defined".

- [ ] **Step 3: Write the action**

`app/Modules/Foundation/Actions/UpdateCompanyProfile.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\CompanyProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Edits the single company profile row (docs/01 §3.3, §5.5).
 */
class UpdateCompanyProfile
{
    public const FIELDS = ['name', 'short_name', 'address', 'phone', 'email', 'website', 'tin', 'bin', 'trade_license_no', 'base_currency_id', 'fiscal_year_start_month', 'print_footer'];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?UploadedFile $logo = null): CompanyProfile
    {
        $input = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $input);

        /** @var array<string, mixed> $data */
        $data = Validator::make([...$input, 'logo' => $logo], [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url', 'max:150'],
            'tin' => ['nullable', 'string', 'max:30'],
            'bin' => ['nullable', 'string', 'max:30'],
            'trade_license_no' => ['nullable', 'string', 'max:60'],
            'base_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'print_footer' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ])->validate();

        $profile = CompanyProfile::current() ?? new CompanyProfile;
        $oldLogo = $profile->logo_path;
        $newLogo = $logo?->store('company', 'public');

        DB::transaction(function () use ($profile, $data, $newLogo): void {
            $profile->fill(Arr::only($data, self::FIELDS));

            if ($newLogo !== null && $newLogo !== false) {
                $profile->logo_path = $newLogo;
            }

            $profile->save();
        });

        if ($newLogo && $oldLogo !== null && $oldLogo !== $newLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        return $profile;
    }
}
```

- [ ] **Step 4: Write the letterhead component**

`resources/views/components/print/letterhead.blade.php`:

```blade
@props(['company'])

{{-- Company letterhead for A4 prints (docs/00 §7.5, FD-AC-08). --}}
<header {{ $attributes->merge(['class' => 'flex items-start gap-4 border-b pb-4']) }}>
    @if ($company?->logo_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($company->logo_path) }}" alt="{{ $company->name }}" class="h-16 w-auto object-contain">
    @endif
    <div class="flex flex-1 flex-col gap-0.5 text-sm">
        <p class="text-lg font-semibold">{{ $company?->name }}</p>
        @if ($company?->address)
            <p class="whitespace-pre-line">{{ $company->address }}</p>
        @endif
        <p>{{ collect([$company?->phone, $company?->email, $company?->website])->filter()->implode(' · ') }}</p>
        <p>
            @if ($company?->tin) <span>{{ __('TIN') }}: {{ $company->tin }}</span> @endif
            @if ($company?->bin) <span class="ms-3">{{ __('BIN') }}: {{ $company->bin }}</span> @endif
        </p>
    </div>
</header>
```

- [ ] **Step 5: Hide the form actions when there is no submit label**

In `resources/views/components/shell/form-page.blade.php`:
- wrap the `x-ui.card-footer` element and the `data-test="mobile-action-bar"` div each in `@if ($submitLabel) … @endif`;
- change the form class merge to `'flex flex-col gap-6 '.($submitLabel ? 'pb-28 md:pb-0' : '')`.

- [ ] **Step 6: Write the component, view and route**

`app/Modules/Foundation/Livewire/Admin/Company.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Actions\UpdateCompanyProfile;
use App\Modules\Foundation\Models\CompanyProfile;
use App\Support\Facades\Lookup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Company profile')]
class Company extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $short_name = '';

    public string $address = '';

    public string $phone = '';

    public string $email = '';

    public string $website = '';

    public string $tin = '';

    public string $bin = '';

    public string $trade_license_no = '';

    public ?int $base_currency_id = null;

    public int $fiscal_year_start_month = 7;

    public string $print_footer = '';

    /** @var UploadedFile|null */
    public $logo = null;

    public function mount(): void
    {
        $this->authorize('admin.company.view');

        $profile = CompanyProfile::current();

        foreach (UpdateCompanyProfile::FIELDS as $field) {
            $value = $profile?->getAttribute($field);

            if ($value === null) {
                continue;
            }

            $this->{$field} = in_array($field, ['base_currency_id', 'fiscal_year_start_month'], true) ? (int) $value : (string) $value;
        }
    }

    public function save(UpdateCompanyProfile $updateCompanyProfile): void
    {
        $this->authorize('admin.company.update');

        $updateCompanyProfile->handle($this->only(UpdateCompanyProfile::FIELDS), $this->logo instanceof UploadedFile ? $this->logo : null);

        $this->reset('logo');
        $this->dispatch('toast', type: 'success', description: __('Company profile saved.'));
    }

    public function render(): View
    {
        return view('livewire.admin.company', [
            'company' => CompanyProfile::current(),
            'currencies' => Lookup::options('currencies', $this->base_currency_id),
            'readOnly' => ! auth()->user()->can('admin.company.update'),
        ]);
    }
}
```

`resources/views/livewire/admin/company.blade.php`:

```blade
<div class="flex flex-col gap-6 lg:grid lg:grid-cols-[1fr_24rem]">
    <x-shell.form-page wire:submit="save" :submit-label="$readOnly ? null : __('Save company profile')">
        <fieldset @disabled($readOnly) class="flex flex-col gap-6">
            @foreach ([
                'name' => [__('Company name').' *', 'text', null],
                'short_name' => [__('Short name'), 'text', null],
                'phone' => [__('Phone'), 'tel', 'tel'],
                'email' => [__('Email'), 'email', 'email'],
                'website' => [__('Website'), 'url', 'url'],
                'tin' => [__('TIN'), 'text', null],
                'bin' => [__('VAT BIN'), 'text', null],
                'trade_license_no' => [__('Trade license no.'), 'text', null],
                'print_footer' => [__('Print footer'), 'text', null],
            ] as $field => [$label, $type, $inputmode])
                <x-ui.field>
                    <x-ui.field-label for="{{ $field }}">{{ $label }}</x-ui.field-label>
                    <x-ui.input id="{{ $field }}" type="{{ $type }}" :inputmode="$inputmode" wire:model="{{ $field }}" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get($field)" />
                </x-ui.field>
            @endforeach

            <x-ui.field>
                <x-ui.field-label for="address">{{ __('Address') }}</x-ui.field-label>
                <x-ui.textarea id="address" wire:model="address" rows="3" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('address')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="base_currency_id">{{ __('Base currency') }} *</x-ui.field-label>
                <x-ui.select native id="base_currency_id" wire:model="base_currency_id" class="h-11 text-base md:h-9 md:text-sm">
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}">{{ $currency->code }} — {{ $currency->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.field-error :messages="$errors->get('base_currency_id')" />
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="fiscal_year_start_month">{{ __('Fiscal year starts in') }} *</x-ui.field-label>
                <x-ui.select native id="fiscal_year_start_month" wire:model="fiscal_year_start_month" class="h-11 text-base md:h-9 md:text-sm">
                    @foreach (range(1, 12) as $month)
                        <option value="{{ $month }}">{{ \Illuminate\Support\Carbon::create(2026, $month, 1)->format('F') }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.field-error :messages="$errors->get('fiscal_year_start_month')" />
            </x-ui.field>

            @unless ($readOnly)
                <x-ui.field>
                    <x-ui.field-label for="logo">{{ __('Logo (PNG or JPG, up to 1 MB)') }}</x-ui.field-label>
                    <x-ui.input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg" class="h-11 md:h-9" />
                    <x-ui.field-error :messages="$errors->get('logo')" />
                </x-ui.field>
            @endunless
        </fieldset>
    </x-shell.form-page>

    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title>{{ __('Print preview') }}</x-ui.card-title>
        </x-ui.card-header>
        <x-ui.card-content>
            <x-print.letterhead :company="$company" />
        </x-ui.card-content>
    </x-ui.card>
</div>
```

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Livewire\Admin\Company;` and, inside the group:

```php
    Route::livewire('company', Company::class)->middleware('can:admin.company.view')->name('company.edit');
```

- [ ] **Step 7: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/CompanyProfileTest.php tests/Feature/Foundation/Admin/UsersScreenTest.php`
Expected: PASS. The users screens use the form page too, so their run checks the change to it.

Logos are served from `/storage`. If `public/storage` doesn't exist locally, run `php artisan storage:link` once.

- [ ] **Step 8: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Foundation/Actions/UpdateCompanyProfile.php && git commit -m "Add update company profile action with logo replacement"
git add resources/views/components/print/letterhead.blade.php && git commit -m "Add company letterhead for prints"
git add resources/views/components/shell/form-page.blade.php && git commit -m "Hide form actions on read-only pages"
git add app/Modules/Foundation/Livewire/Admin/Company.php && git commit -m "Add company profile component"
git add resources/views/livewire/admin/company.blade.php && git commit -m "Add company profile view with print preview"
git add routes/modules/foundation.php && git commit -m "Add company profile route"
git add tests/Feature/Foundation/Admin/CompanyProfileTest.php && git commit -m "Test company profile editing and logo upload"
```

---

### Task 14: Settings screen

**Files:**
- Create: `app/Modules/Foundation/Actions/UpdateSettings.php`
- Create: `app/Modules/Foundation/Livewire/Admin/Settings.php`, `resources/views/livewire/admin/settings.blade.php`
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/SettingsScreenTest.php`

**Interfaces:**
- Consumes: `SettingsRepository::set()` (casts by type, flushes the cache, audits), the `roles` type (Task 5).
- Produces:
  - `UpdateSettings::handle(string $group, array<string, mixed> $values): void`. It validates each key by its type, and unknown keys fail validation. Errors are keyed by the short key.
  - Route `admin.settings.edit` (`admin/settings`, `can:admin.settings.view`).
  - Component state `#[Url] string $group` and `array<string, array<string, mixed>> $values` (group → key → value). JSON settings are edited as pretty-printed strings.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/SettingsScreenTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\Settings as SettingsScreen;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    ensureRole('finance_manager');
});

test('settings need admin.settings.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.settings.edit'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.settings.view'))->get(route('admin.settings.edit'))->assertOk()->assertSee(__('Session idle timeout (minutes)'));
});

test('a tab saves typed values and flushes the cache', function () {
    Livewire::actingAs(userWithPermissions('admin.settings.view', 'admin.settings.update'))
        ->test(SettingsScreen::class)
        ->set('values.general.session_timeout_minutes', '45')
        ->set('values.general.require_2fa_roles', ['finance_manager'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    expect(Settings::get('general.session_timeout_minutes'))->toBe(45)
        ->and(Settings::get('general.require_2fa_roles'))->toBe(['finance_manager']);
});

test('invalid values show on their fields', function () {
    Livewire::actingAs(userWithPermissions('admin.settings.view', 'admin.settings.update'))
        ->test(SettingsScreen::class)
        ->set('values.general.session_timeout_minutes', 'soon')
        ->set('values.general.require_2fa_roles', ['not_a_role'])
        ->call('save')
        ->assertHasErrors(['values.general.session_timeout_minutes', 'values.general.require_2fa_roles.0']);
});

test('switching tabs saves only that group', function () {
    Livewire::actingAs(userWithPermissions('admin.settings.view', 'admin.settings.update'))
        ->test(SettingsScreen::class)
        ->set('group', 'notifications')
        ->set('values.notifications.sms_enabled', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Settings::get('notifications.sms_enabled'))->toBeTrue();
});

test('saving without update permission is forbidden', function () {
    Livewire::actingAs(userWithPermissions('admin.settings.view'))->test(SettingsScreen::class)->call('save')->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/SettingsScreenTest.php`
Expected: FAIL with "Route [admin.settings.edit] not defined".

- [ ] **Step 3: Write the action**

`app/Modules/Foundation/Actions/UpdateSettings.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Setting;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saves one settings group (docs/01 §5.7). Each value is validated by its declared type.
 */
class UpdateSettings
{
    public function __construct(private SettingsRepository $settings) {}

    /**
     * @param  array<string, mixed>  $values  short key => value
     *
     * @throws ValidationException
     */
    public function handle(string $group, array $values): void
    {
        $types = Setting::query()->where('group', $group)->pluck('type', 'key');
        $rules = [];

        foreach (array_keys($values) as $key) {
            $type = $types[$key] ?? null;

            $rules[$key] = match (true) {
                $type === null => [fn (string $attribute, mixed $value, \Closure $fail) => $fail(__('Unknown setting.'))],
                $type === 'int' => ['required', 'integer'],
                $type === 'decimal' => ['required', 'numeric'],
                $type === 'bool' => ['boolean'],
                $type === 'json' => ['nullable', 'json'],
                $type === 'roles' => ['array'],
                str_starts_with($type, 'fk:') => ['nullable', 'integer', Rule::exists(substr($type, 3), 'id')],
                default => ['nullable', 'string', 'max:255'],
            };

            if ($type === 'roles') {
                $rules[$key.'.*'] = ['string', Rule::exists('roles', 'code')];
            }
        }

        $validated = Validator::make($values, $rules)->validate();

        DB::transaction(function () use ($group, $validated, $types): void {
            foreach ($validated as $key => $value) {
                if (($types[$key] ?? null) === 'json' && is_string($value)) {
                    $value = json_decode($value, true);
                }

                $this->settings->set($group.'.'.$key, $value);
            }
        });
    }
}
```

- [ ] **Step 4: Write the component**

`app/Modules/Foundation/Livewire/Admin/Settings.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Actions\UpdateSettings;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Models\Setting;
use App\Support\Facades\Lookup;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Settings')]
class Settings extends Component
{
    #[Url(except: 'general')]
    public string $group = 'general';

    /** @var array<string, array<string, mixed>> */
    public array $values = [];

    public function mount(): void
    {
        $this->authorize('admin.settings.view');

        foreach (Setting::query()->orderBy('id')->get() as $setting) {
            $this->values[$setting->group][$setting->key] = $setting->type === 'json'
                ? json_encode($setting->value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : $setting->value;
        }

        if (! array_key_exists($this->group, $this->values)) {
            $this->group = (string) array_key_first($this->values);
        }
    }

    public function save(UpdateSettings $updateSettings): void
    {
        $this->authorize('admin.settings.update');

        try {
            $updateSettings->handle($this->group, $this->values[$this->group] ?? []);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["values.{$this->group}.{$key}" => $messages])->all(),
            );
        }

        $this->dispatch('toast', type: 'success', description: __('Settings saved.'));
    }

    public function render(): View
    {
        $settings = Setting::query()->orderBy('id')->get();

        return view('livewire.admin.settings', [
            'groups' => $settings->pluck('group')->unique()->values(),
            'fields' => $settings->where('group', $this->group)->values(),
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(['code', 'name']),
            'lookupOptions' => fn (string $table, mixed $current) => Lookup::options($table, is_numeric($current) ? (int) $current : null),
            'readOnly' => ! auth()->user()->can('admin.settings.update'),
        ]);
    }
}
```

- [ ] **Step 5: Write the view and route**

`resources/views/livewire/admin/settings.blade.php`:

```blade
<div class="flex flex-col gap-4">
    <div class="hidden md:block">
        <x-ui.tabs-list variant="underline">
            @foreach ($groups as $tab)
                <button type="button" wire:click="$set('group', '{{ $tab }}')"
                    @class(['px-3 py-2 text-sm capitalize', 'border-b-2 border-primary font-medium' => $tab === $group, 'text-muted-foreground' => $tab !== $group])>
                    {{ __(ucfirst($tab)) }}
                </button>
            @endforeach
        </x-ui.tabs-list>
    </div>
    <div class="overflow-x-auto md:hidden">
        <x-ui.segmented-control name="settings-group" wire:model.live="group" :value="$group"
            :options="$groups->mapWithKeys(fn ($tab) => [$tab => __(ucfirst($tab))])->all()" class="h-11" />
    </div>

    <x-shell.form-page wire:submit="save" :submit-label="$readOnly ? null : __('Save :group settings', ['group' => __($group)])">
        <fieldset @disabled($readOnly) class="flex flex-col gap-6" wire:key="group-{{ $group }}">
            @foreach ($fields as $setting)
                @php($model = "values.{$group}.{$setting->key}")
                @php($id = "setting-{$setting->key}")
                @if ($setting->type === 'bool')
                    <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                        <x-ui.switch :id="$id" wire:model="{{ $model }}" :checked="(bool) data_get($values, $group.'.'.$setting->key)" />
                        <x-ui.field-label :for="$id">{{ __($setting->label) }}</x-ui.field-label>
                    </x-ui.field>
                @else
                    <x-ui.field>
                        <x-ui.field-label :for="$id">{{ __($setting->label) }}</x-ui.field-label>
                        @if ($setting->type === 'roles')
                            <div class="flex flex-col gap-1">
                                @foreach ($roles as $role)
                                    <label class="flex min-h-11 items-center gap-3 text-sm md:min-h-8">
                                        <x-ui.checkbox native wire:model="{{ $model }}" value="{{ $role->code }}" />
                                        {{ $role->name }}
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($setting->type === 'json')
                            <x-ui.textarea :id="$id" wire:model="{{ $model }}" rows="4" class="font-mono text-base md:text-sm" />
                        @elseif (str_starts_with($setting->type, 'fk:'))
                            <x-ui.select native :id="$id" wire:model="{{ $model }}" class="h-11 text-base md:h-9 md:text-sm">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($lookupOptions(substr($setting->type, 3), data_get($values, $group.'.'.$setting->key)) as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </x-ui.select>
                        @else
                            <x-ui.input :id="$id" wire:model="{{ $model }}"
                                :type="$setting->type === 'int' ? 'number' : 'text'"
                                :inputmode="match ($setting->type) { 'int' => 'numeric', 'decimal' => 'decimal', default => null }"
                                class="h-11 text-base md:h-9 md:text-sm" />
                        @endif
                        @if ($setting->help)
                            <x-ui.field-description>{{ __($setting->help) }}</x-ui.field-description>
                        @endif
                        <x-ui.field-error :messages="$errors->get($model)" />
                        <x-ui.field-error :messages="collect($errors->get($model.'.*'))->flatten()->all()" />
                    </x-ui.field>
                @endif
            @endforeach
        </fieldset>
    </x-shell.form-page>
</div>
```

The desktop tab strip reuses `x-ui.tabs-list` only for its styling, with plain buttons that set `group`. This keeps the active tab in the component, so the URL and "save this tab" stay in step. If `x-ui.tabs-list` renders nothing outside `x-ui.tabs`, replace it with a `<div role="tablist" class="flex gap-1 border-b">`.

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Livewire\Admin\Settings;` and, inside the group:

```php
    Route::livewire('settings', Settings::class)->middleware('can:admin.settings.view')->name('settings.edit');
```

- [ ] **Step 6: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/SettingsScreenTest.php tests/Feature/Foundation/SettingsTest.php`
Expected: PASS.

- [ ] **Step 7: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Foundation/Actions/UpdateSettings.php && git commit -m "Add action to save a settings group with typed validation"
git add app/Modules/Foundation/Livewire/Admin/Settings.php && git commit -m "Add settings screen component"
git add resources/views/livewire/admin/settings.blade.php && git commit -m "Add settings view with tabs and typed fields"
git add routes/modules/foundation.php && git commit -m "Add settings route"
git add tests/Feature/Foundation/Admin/SettingsScreenTest.php && git commit -m "Test settings screen"
```

---

### Task 15: Number sequences screen

**Files:**
- Create: `app/Modules/Foundation/Actions/UpdateSequenceFormat.php`, `app/Modules/Foundation/Actions/IncreaseSequenceNumber.php`
- Create: `app/Modules/Foundation/Livewire/Admin/Sequences.php`, `resources/views/livewire/admin/sequences.blade.php`
- Modify: `app/Support/NumberSequenceService.php` (public `preview()`, shared token building)
- Modify: `app/Modules/Foundation/Models/NumberSequenceFormat.php` (`sequences()` relation)
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/SequencesTest.php`

**Interfaces:**
- Produces:
  - `NumberSequenceService::preview(string $format, int $number, array $context = []): string` renders the tokens without touching the database.
  - `NumberSequenceFormat::sequences(): HasMany` relates on `document_type`.
  - `UpdateSequenceFormat::handle(NumberSequenceFormat $definition, string $format): void`. It also rewrites `format` on every existing counter of that type, because the service renders from the counter row.
  - `IncreaseSequenceNumber::handle(NumberSequence $sequence, int $nextNumber): void` (FD-BR-07, checked under `lockForUpdate`). Errors use the keys `format` and `next_number`.
  - Route `admin.sequences.index` (`admin/sequences`, `can:admin.sequences.view`).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/SequencesTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Actions\IncreaseSequenceNumber;
use App\Modules\Foundation\Actions\UpdateSequenceFormat;
use App\Modules\Foundation\Livewire\Admin\Sequences;
use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use Livewire\Livewire;

beforeEach(function () {
    $this->definition = NumberSequenceFormat::query()->create(['document_type' => 'invoice', 'format' => 'INV-{yy}-{seq:5}', 'reset_policy' => 'fiscal_year']);
    $this->counter = NumberSequence::query()->create(['document_type' => 'invoice', 'scope_key' => 'fy:27', 'format' => 'INV-{yy}-{seq:5}', 'next_number' => 10, 'reset_policy' => 'fiscal_year']);
});

test('a format must contain a seq token and only known tokens', function () {
    expectValidationError(fn () => app(UpdateSequenceFormat::class)->handle($this->definition, 'INV-{yy}'), 'format');
    expectValidationError(fn () => app(UpdateSequenceFormat::class)->handle($this->definition, 'INV-{month}-{seq:5}'), 'format');
    expectValidationError(fn () => app(UpdateSequenceFormat::class)->handle($this->definition, 'INV-{seq:0}'), 'format');
});

test('a new format is applied to the definition and its counters', function () {
    app(UpdateSequenceFormat::class)->handle($this->definition, 'SI-{yyyy}-{seq:6}');

    expect($this->definition->fresh()->format)->toBe('SI-{yyyy}-{seq:6}')
        ->and($this->counter->fresh()->format)->toBe('SI-{yyyy}-{seq:6}');
});

test('the next number can only increase (FD-BR-07)', function () {
    expectValidationError(fn () => app(IncreaseSequenceNumber::class)->handle($this->counter, 10), 'next_number');
    expectValidationError(fn () => app(IncreaseSequenceNumber::class)->handle($this->counter, 3), 'next_number');

    app(IncreaseSequenceNumber::class)->handle($this->counter, 25);
    expect($this->counter->fresh()->next_number)->toBe(25);
});

test('the screen needs admin.sequences.view and shows a sample number', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.sequences.index'))->assertForbidden();

    $this->actingAs(userWithPermissions('admin.sequences.view'))
        ->get(route('admin.sequences.index'))
        ->assertOk()
        ->assertSee('INV-{yy}-{seq:5}')
        ->assertSee('00010');
});

test('editing from the screen needs admin.sequences.update', function () {
    Livewire::actingAs(userWithPermissions('admin.sequences.view'))
        ->test(Sequences::class)
        ->call('editFormat', $this->definition->id)
        ->assertForbidden();

    Livewire::actingAs(userWithPermissions('admin.sequences.view', 'admin.sequences.update'))
        ->test(Sequences::class)
        ->call('editCounter', $this->counter->id)
        ->set('nextNumber', 5)
        ->call('saveCounter')
        ->assertHasErrors(['nextNumber']);
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/SequencesTest.php`
Expected: FAIL with "Class ...UpdateSequenceFormat not found".

- [ ] **Step 3: Expose a preview on the service**

In `app/Support/NumberSequenceService.php`, move the token building out of `next()` into a private method and add `preview()`:

```php
    /**
     * Render a format for display (e.g. the admin screen) without issuing a number.
     *
     * @param  array{date?: CarbonInterface|string, bl_prefix?: string, branch?: string}  $context
     */
    public function preview(string $format, int $number, array $context = []): string
    {
        return $this->render($format, $number, $this->tokens($context));
    }

    /**
     * @param  array{date?: CarbonInterface|string, bl_prefix?: string, branch?: string}  $context
     * @return array{yy: string, yyyy: string, bl_prefix: string, branch: string}
     */
    private function tokens(array $context): array
    {
        $fiscalYear = FiscalYear::for(
            CarbonImmutable::parse($context['date'] ?? now()),
            CompanyProfile::fiscalYearStartMonth(),
        );

        return [
            'yy' => $fiscalYear->shortCode(),
            'yyyy' => $fiscalYear->longCode(),
            'bl_prefix' => $context['bl_prefix'] ?? '',
            'branch' => $context['branch'] ?? '',
        ];
    }
```

In `next()`, replace the `$fiscalYear = …;` and `$tokens = [ … ];` statements with `$tokens = $this->tokens($context);`. Run `php artisan test --compact tests/Feature/Foundation/NumberSequenceTest.php` and expect PASS (no behaviour change).

In `NumberSequenceFormat`, add (with `use Illuminate\Database\Eloquent\Relations\HasMany;`):

```php
    /**
     * @return HasMany<NumberSequence, $this>
     */
    public function sequences(): HasMany
    {
        return $this->hasMany(NumberSequence::class, 'document_type', 'document_type')->orderBy('scope_key');
    }
```

- [ ] **Step 4: Write the actions**

`app/Modules/Foundation/Actions/UpdateSequenceFormat.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSequenceFormat
{
    private const TOKEN = '/\{([^}]*)\}/';

    private const ALLOWED = '/^(seq:[1-9]|yy|yyyy|bl_prefix|branch)$/';

    /**
     * @throws ValidationException
     */
    public function handle(NumberSequenceFormat $definition, string $format): void
    {
        $format = trim($format);
        preg_match_all(self::TOKEN, $format, $matches);
        $tokens = $matches[1];

        $error = match (true) {
            $format === '' || mb_strlen($format) > 80 => __('The format must be 1 to 80 characters.'),
            ! in_array(true, array_map(fn (string $token): bool => str_starts_with($token, 'seq:'), $tokens), true) => __('The format must contain a {seq:N} token.'),
            array_filter($tokens, fn (string $token): bool => preg_match(self::ALLOWED, $token) !== 1) !== [] => __('Only {seq:N}, {yy}, {yyyy}, {bl_prefix} and {branch} tokens are allowed (N from 1 to 9).'),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['format' => $error]);
        }

        DB::transaction(function () use ($definition, $format): void {
            $definition->update(['format' => $format]);
            NumberSequence::query()->where('document_type', $definition->document_type)->update(['format' => $format]);
        });
    }
}
```

`app/Modules/Foundation/Actions/IncreaseSequenceNumber.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\NumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FD-BR-07: next_number can only increase. Checked again under the row lock so a document
 * issued in the meantime cannot make the new value a step backwards.
 */
class IncreaseSequenceNumber
{
    /**
     * @throws ValidationException
     */
    public function handle(NumberSequence $sequence, int $nextNumber): void
    {
        DB::transaction(function () use ($sequence, $nextNumber): void {
            $locked = NumberSequence::query()->whereKey($sequence->id)->lockForUpdate()->firstOrFail();

            if ($nextNumber <= $locked->next_number) {
                throw ValidationException::withMessages([
                    'next_number' => __('The next number can only increase (it is :current now).', ['current' => $locked->next_number]),
                ]);
            }

            $locked->update(['next_number' => $nextNumber]);
        });
    }
}
```

- [ ] **Step 5: Write the component, view and route**

`app/Modules/Foundation/Livewire/Admin/Sequences.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Actions\IncreaseSequenceNumber;
use App\Modules\Foundation\Actions\UpdateSequenceFormat;
use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use App\Support\NumberSequenceService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Number sequences')]
class Sequences extends Component
{
    public ?int $formatId = null;

    public string $format = '';

    public ?int $counterId = null;

    public ?int $nextNumber = null;

    public function mount(): void
    {
        $this->authorize('admin.sequences.view');
    }

    public function editFormat(int $id): void
    {
        $this->authorize('admin.sequences.update');

        $definition = NumberSequenceFormat::query()->findOrFail($id);
        $this->formatId = $definition->id;
        $this->format = $definition->format;
        $this->resetErrorBag();
        $this->dispatch('open-sheet-sequence-format');
    }

    public function saveFormat(UpdateSequenceFormat $updateSequenceFormat): void
    {
        $this->authorize('admin.sequences.update');

        $updateSequenceFormat->handle(NumberSequenceFormat::query()->findOrFail($this->formatId), $this->format);

        $this->dispatch('close-sheet-sequence-format');
        $this->dispatch('toast', type: 'success', description: __('Format saved.'));
    }

    public function editCounter(int $id): void
    {
        $this->authorize('admin.sequences.update');

        $counter = NumberSequence::query()->findOrFail($id);
        $this->counterId = $counter->id;
        $this->nextNumber = $counter->next_number;
        $this->resetErrorBag();
        $this->dispatch('open-sheet-sequence-counter');
    }

    public function saveCounter(IncreaseSequenceNumber $increaseSequenceNumber): void
    {
        $this->authorize('admin.sequences.update');
        $this->validate(['nextNumber' => ['required', 'integer', 'min:1']]);

        try {
            $increaseSequenceNumber->handle(NumberSequence::query()->findOrFail($this->counterId), (int) $this->nextNumber);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['nextNumber' => $exception->errors()['next_number'] ?? []]);
        }

        $this->dispatch('close-sheet-sequence-counter');
        $this->dispatch('toast', type: 'success', description: __('Next number updated.'));
    }

    public function render(NumberSequenceService $numbers): View
    {
        return view('livewire.admin.sequences', [
            'definitions' => NumberSequenceFormat::query()->with('sequences')->orderBy('document_type')->get(),
            'sample' => fn (string $format, int $number): string => $numbers->preview($format, $number, ['bl_prefix' => 'BL', 'branch' => 'HO']),
            'counter' => $this->counterId !== null ? NumberSequence::query()->find($this->counterId) : null,
        ]);
    }
}
```

`saveFormat` lets the Action's `format` error reach the `format` property directly. `saveCounter` maps `next_number` to the `nextNumber` property.

`resources/views/livewire/admin/sequences.blade.php`:

```blade
<div class="flex flex-col gap-3">
    @foreach ($definitions as $definition)
        <x-ui.card class="gap-3 py-4" wire:key="definition-{{ $definition->id }}">
            <x-ui.card-header class="flex flex-row items-start justify-between gap-2 px-4">
                <div class="min-w-0">
                    <x-ui.card-title class="text-base">{{ \Illuminate\Support\Str::headline($definition->document_type) }}</x-ui.card-title>
                    <x-ui.card-description class="font-mono text-sm">{{ $definition->format }}</x-ui.card-description>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ $definition->reset_policy === 'fiscal_year' ? __('Resets every fiscal year') : __('Never resets') }}
                        @if ($definition->scope_by) · {{ __('Separate per :scope', ['scope' => str_replace('_', ' ', $definition->scope_by)]) }} @endif
                    </p>
                </div>
                @can('admin.sequences.update')
                    <x-ui.button variant="ghost" size="icon" class="size-11 md:size-9" wire:click="editFormat({{ $definition->id }})" :aria-label="__('Edit format')"><x-lucide-pencil /></x-ui.button>
                @endcan
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-1 px-4">
                @forelse ($definition->sequences as $sequence)
                    <div class="flex min-h-11 items-center gap-2 border-t pt-1 text-sm" wire:key="counter-{{ $sequence->id }}">
                        <span class="w-24 shrink-0 font-mono text-muted-foreground">{{ $sequence->scope_key === '' ? __('global') : $sequence->scope_key }}</span>
                        <span class="flex-1 font-mono">{{ $sample($sequence->format, $sequence->next_number) }}</span>
                        @can('admin.sequences.update')
                            <x-ui.button variant="outline" size="sm" class="h-11 md:h-8" wire:click="editCounter({{ $sequence->id }})">{{ __('Next: :n', ['n' => $sequence->next_number]) }}</x-ui.button>
                        @else
                            <span class="text-muted-foreground">{{ __('Next: :n', ['n' => $sequence->next_number]) }}</span>
                        @endcan
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('Sample: :sample (no numbers issued yet)', ['sample' => $sample($definition->format, 1)]) }}</p>
                @endforelse
            </x-ui.card-content>
        </x-ui.card>
    @endforeach

    <x-shell.sheet id="sequence-format" :title="__('Edit format')">
        <form wire:submit="saveFormat" id="sequence-format-form" class="flex flex-col gap-3 pb-2">
            <x-ui.field>
                <x-ui.field-label for="format">{{ __('Format') }}</x-ui.field-label>
                <x-ui.input id="format" wire:model.live.debounce.300ms="format" autocapitalize="none" class="h-11 font-mono text-base md:h-9 md:text-sm" />
                <x-ui.field-description>{{ __('Tokens: {seq:N}, {yy}, {yyyy}, {bl_prefix}, {branch}') }}</x-ui.field-description>
                <x-ui.field-error :messages="$errors->get('format')" />
            </x-ui.field>
            <p class="text-sm">{{ __('Sample') }}: <span class="font-mono">{{ $sample($format, 1) }}</span></p>
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="sequence-format-form">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    <x-shell.sheet id="sequence-counter" :title="__('Change next number')" :description="$counter ? __(':type · :scope', ['type' => $counter->document_type, 'scope' => $counter->scope_key ?: __('global')]) : null">
        <form wire:submit="saveCounter" id="sequence-counter-form" class="flex flex-col gap-3 pb-2">
            <x-ui.alert>
                <x-lucide-triangle-alert />
                <x-ui.alert-description>{{ __('The next number can only go up. Skipped numbers are never reused.') }}</x-ui.alert-description>
            </x-ui.alert>
            <x-ui.field>
                <x-ui.field-label for="nextNumber">{{ __('Next number') }}</x-ui.field-label>
                <x-ui.input id="nextNumber" type="number" inputmode="numeric" wire:model="nextNumber" class="h-11 text-base md:h-9 md:text-sm" />
                <x-ui.field-error :messages="$errors->get('nextNumber')" />
            </x-ui.field>
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="sequence-counter-form">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
```

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Livewire\Admin\Sequences;` and, inside the group:

```php
    Route::livewire('sequences', Sequences::class)->middleware('can:admin.sequences.view')->name('sequences.index');
```

- [ ] **Step 6: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/SequencesTest.php tests/Feature/Foundation/NumberSequenceTest.php`
Expected: PASS.

- [ ] **Step 7: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/NumberSequenceService.php && git commit -m "Add number format preview to the sequence service"
git add app/Modules/Foundation/Models/NumberSequenceFormat.php && git commit -m "Relate number formats to their counters"
git add app/Modules/Foundation/Actions/UpdateSequenceFormat.php && git commit -m "Add action to change a number format" -m "Validates tokens and updates every counter of that document type."
git add app/Modules/Foundation/Actions/IncreaseSequenceNumber.php && git commit -m "Only allow sequence numbers to increase" -m "FD-BR-07, re-checked under the row lock."
git add app/Modules/Foundation/Livewire/Admin/Sequences.php && git commit -m "Add number sequences screen component"
git add resources/views/livewire/admin/sequences.blade.php && git commit -m "Add number sequences view with edit sheets"
git add routes/modules/foundation.php && git commit -m "Add number sequences route"
git add tests/Feature/Foundation/Admin/SequencesTest.php && git commit -m "Test number sequence editing rules"
```

---

### Task 16: Audit log screen

**Files:**
- Create: `app/Modules/Foundation/Livewire/Admin/AuditLog.php`, `resources/views/livewire/admin/audit-log.blade.php`
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/AuditLogScreenTest.php`

**Interfaces:**
- Consumes: `WithListing`, `ListingExport`, `x-shell.list`, `x-shell.sheet`.
- Produces:
  - Route `admin.audit.index` (`admin/audit`, `can:admin.audit.view`).
  - Filters `user` (username), `type` (morph alias), `record` (id), `event`, `from`, `to` (dates).
  - Methods `show(int $id)` (opens the `audit-entry` sheet) and `export()` (needs `admin.audit.export`).
- The spec allows an "expandable row" on desktop. This plan uses the same right-side sheet on desktop and the bottom sheet on mobile, so one change view serves both.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/AuditLogScreenTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\AuditLog as AuditLogScreen;
use App\Modules\Foundation\Models\AuditLog;
use App\Support\AuditTrail\AuditTrail;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->subject = User::factory()->create(['username' => 'subject1']);
    AuditTrail::record($this->subject, 'updated', ['phone' => '01711111111'], ['phone' => '01822222222']);
});

test('the audit log needs admin.audit.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.audit.index'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.audit.view'))->get(route('admin.audit.index'))->assertOk();
});

test('filters narrow by record type, record id, event and date', function () {
    Livewire::actingAs(userWithPermissions('admin.audit.view'))
        ->test(AuditLogScreen::class)
        ->set('filters.type', 'user')
        ->set('filters.record', (string) $this->subject->id)
        ->set('filters.event', 'updated')
        ->assertSee('user #'.$this->subject->id)
        ->set('filters.from', now()->addDay()->toDateString())
        ->assertDontSee('user #'.$this->subject->id);
});

test('an entry shows each changed field old and new (FD-AC-06 view)', function () {
    $entry = AuditLog::query()->where('event', 'updated')->latest('id')->firstOrFail();

    Livewire::actingAs(userWithPermissions('admin.audit.view'))
        ->test(AuditLogScreen::class)
        ->call('show', $entry->id)
        ->assertDispatched('open-sheet-audit-entry')
        ->assertSee('01711111111')
        ->assertSee('01822222222');
});

test('exporting needs admin.audit.export', function () {
    Excel::fake();
    Excel::matchByRegex();

    Livewire::actingAs(userWithPermissions('admin.audit.view'))->test(AuditLogScreen::class)->call('export')->assertForbidden();

    Livewire::actingAs(userWithPermissions('admin.audit.view', 'admin.audit.export'))->test(AuditLogScreen::class)->call('export');
    Excel::assertDownloaded('/^audit-log-\d{8}-\d{6}\.xlsx$/');
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/AuditLogScreenTest.php`
Expected: FAIL with "Route [admin.audit.index] not defined".

- [ ] **Step 3: Write the component**

`app/Modules/Foundation/Livewire/Admin/AuditLog.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Models\AuditLog as AuditEntry;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Audit log')]
class AuditLog extends Component
{
    use WithListing;

    public ?int $selectedId = null;

    public function mount(): void
    {
        $this->authorize('admin.audit.view');
    }

    public function show(int $id): void
    {
        $this->authorize('admin.audit.view');

        $this->selectedId = AuditEntry::query()->findOrFail($id)->id;
        $this->dispatch('open-sheet-audit-entry');
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('admin.audit.export');

        return ListingExport::download('audit-log', $this->filteredQuery(), [
            'When' => fn (AuditEntry $entry): string => $entry->created_at->format('d-M-Y H:i:s'),
            'User' => 'user.username',
            'Event' => 'event',
            'Record type' => 'auditable_type',
            'Record id' => 'auditable_id',
            'Old values' => fn (AuditEntry $entry): string => (string) json_encode($entry->old_values, JSON_UNESCAPED_UNICODE),
            'New values' => fn (AuditEntry $entry): string => (string) json_encode($entry->new_values, JSON_UNESCAPED_UNICODE),
            'IP' => 'ip_address',
        ]);
    }

    /**
     * @return Builder<AuditEntry>
     */
    protected function listingQuery(): Builder
    {
        return AuditEntry::query()->with('user:id,name,username')->latest('id');
    }

    protected function searchColumns(): array
    {
        return ['auditable_type', 'event'];
    }

    protected function sortColumns(): array
    {
        return ['created_at' => 'created_at'];
    }

    protected function applyFilters(Builder $query): void
    {
        $filters = $this->filters;

        if (filled($filters['user'] ?? null)) {
            $query->whereHas('user', fn (Builder $users) => $users->where('username', Str::lower(trim($filters['user']))));
        }

        if (filled($filters['type'] ?? null)) {
            $query->where('auditable_type', $filters['type']);
        }

        if (filled($filters['record'] ?? null) && ctype_digit((string) $filters['record'])) {
            $query->where('auditable_id', (int) $filters['record']);
        }

        if (filled($filters['event'] ?? null)) {
            $query->where('event', $filters['event']);
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
    }

    public function render(): View
    {
        return view('livewire.admin.audit-log', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'types' => AuditEntry::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
            'events' => AuditEntry::query()->distinct()->orderBy('event')->pluck('event'),
            'selected' => $this->selectedId !== null ? AuditEntry::query()->with('user')->find($this->selectedId) : null,
        ]);
    }
}
```

- [ ] **Step 4: Write the view and route**

`resources/views/livewire/admin/audit-log.blade.php`:

```blade
<div>
    <x-shell.list
        :search-placeholder="__('Search record type or event')"
        :exportable="auth()->user()->can('admin.audit.export')"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-user">{{ __('Username') }}</x-ui.field-label>
                <x-ui.input id="filter-user" wire:model.live.debounce.300ms="filters.user" autocapitalize="none" class="h-11 text-base md:h-9 md:text-sm" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-type">{{ __('Record type') }}</x-ui.field-label>
                <x-ui.select native id="filter-type" wire:model.live="filters.type" class="h-11 md:h-9">
                    <option value="">{{ __('All types') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-record">{{ __('Record id') }}</x-ui.field-label>
                <x-ui.input id="filter-record" type="number" inputmode="numeric" wire:model.live.debounce.300ms="filters.record" class="h-11 text-base md:h-9 md:text-sm" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-event">{{ __('Event') }}</x-ui.field-label>
                <x-ui.select native id="filter-event" wire:model.live="filters.event" class="h-11 md:h-9">
                    <option value="">{{ __('All events') }}</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}">{{ str_replace('_', ' ', $event) }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <div class="grid grid-cols-2 gap-2">
                <x-ui.field>
                    <x-ui.field-label for="filter-from">{{ __('From') }}</x-ui.field-label>
                    <x-ui.input id="filter-from" type="date" wire:model.live="filters.from" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="filter-to">{{ __('To') }}</x-ui.field-label>
                    <x-ui.input id="filter-to" type="date" wire:model.live="filters.to" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
            </div>
        </x-slot:filters>

        <x-slot:desktop>
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head><x-shell.sort-header key="created_at" :label="__('When')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('User') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Event') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Record') }}</x-ui.table-head>
                        <x-ui.table-head class="w-10"><span class="sr-only">{{ __('Changes') }}</span></x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $entry)
                        <x-ui.table-row wire:key="audit-{{ $entry->id }}">
                            <x-ui.table-cell class="whitespace-nowrap">{{ $entry->created_at->format('d-M-Y H:i') }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $entry->user?->username ?? __('system') }}</x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge variant="secondary">{{ str_replace('_', ' ', $entry->event) }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell class="font-mono text-sm">{{ $entry->auditable_type }} #{{ $entry->auditable_id }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.button variant="ghost" size="icon" wire:click="show({{ $entry->id }})" :aria-label="__('View changes')"><x-lucide-eye /></x-ui.button>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="5" class="py-10 text-center text-muted-foreground">{{ __('No entries found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $entry)
                <button type="button" wire:click="show({{ $entry->id }})" wire:key="m-audit-{{ $entry->id }}" class="w-full text-start">
                    <x-ui.item variant="outline" class="min-h-16 active:bg-accent">
                        <x-ui.item-content>
                            <x-ui.item-title class="text-base">{{ $entry->auditable_type }} #{{ $entry->auditable_id }}</x-ui.item-title>
                            <x-ui.item-description class="text-sm">{{ $entry->user?->username ?? __('system') }} · {{ $entry->created_at->format('d-M-Y H:i') }}</x-ui.item-description>
                        </x-ui.item-content>
                        <x-ui.badge variant="secondary">{{ str_replace('_', ' ', $entry->event) }}</x-ui.badge>
                        <x-lucide-chevron-right class="size-4 text-muted-foreground" />
                    </x-ui.item>
                </button>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No entries found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>

    <x-shell.sheet id="audit-entry" :title="$selected ? $selected->auditable_type.' #'.$selected->auditable_id : null"
        :description="$selected ? str_replace('_', ' ', $selected->event).' · '.($selected->user?->username ?? __('system')).' · '.$selected->created_at->format('d-M-Y H:i:s') : null">
        @if ($selected)
            @php($fields = collect(array_keys([...($selected->old_values ?? []), ...($selected->new_values ?? [])])))
            @php($show = fn ($value) => is_scalar($value) || $value === null ? (string) ($value ?? '—') : json_encode($value, JSON_UNESCAPED_UNICODE))
            <div class="flex flex-col gap-3 pb-4">
                @forelse ($fields as $field)
                    <div class="rounded-md border p-3 text-sm">
                        <p class="font-medium">{{ $field }}</p>
                        <p class="break-all text-destructive line-through">{{ $show($selected->old_values[$field] ?? null) }}</p>
                        <p class="break-all text-success">{{ $show($selected->new_values[$field] ?? null) }}</p>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('No field changes recorded for this event.') }}</p>
                @endforelse
                <p class="text-sm text-muted-foreground">{{ $selected->ip_address }} · {{ \Illuminate\Support\Str::limit((string) $selected->user_agent, 80) }}</p>
            </div>
        @endif
    </x-shell.sheet>
</div>
```

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Livewire\Admin\AuditLog;` and, inside the group:

```php
    Route::livewire('audit', AuditLog::class)->middleware('can:admin.audit.view')->name('audit.index');
```

- [ ] **Step 5: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/AuditLogScreenTest.php`
Expected: PASS.

- [ ] **Step 6: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Foundation/Livewire/Admin/AuditLog.php && git commit -m "Add audit log screen component with filters and export"
git add resources/views/livewire/admin/audit-log.blade.php && git commit -m "Add audit log view with change sheet"
git add routes/modules/foundation.php && git commit -m "Add audit log route"
git add tests/Feature/Foundation/Admin/AuditLogScreenTest.php && git commit -m "Test audit log screen"
```

---

### Task 17: Login history screen

**Files:**
- Create: `app/Modules/Foundation/Livewire/Admin/LoginHistory.php`, `resources/views/livewire/admin/login-history.blade.php`
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/LoginHistoryScreenTest.php`

**Interfaces:**
- Produces:
  - Route `admin.login-history.index` (`admin/login-history`, `can:admin.login_history.view`).
  - Filters `user` (username), `result` (`1` succeeded / `0` failed), `from`, `to`.
  - `export()`, which needs only view permission (doc 01 §10).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/LoginHistoryScreenTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\LoginHistory as LoginHistoryScreen;
use App\Modules\Foundation\Models\LoginHistory;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    LoginHistory::query()->create(['username_attempted' => 'goodlogin', 'succeeded' => true, 'ip_address' => '10.0.0.1']);
    LoginHistory::query()->create(['username_attempted' => 'badlogin', 'succeeded' => false, 'ip_address' => '10.0.0.2']);
});

test('login history needs admin.login_history.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.login-history.index'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.login_history.view'))->get(route('admin.login-history.index'))->assertOk();
});

test('results and usernames filter the rows', function () {
    Livewire::actingAs(userWithPermissions('admin.login_history.view'))
        ->test(LoginHistoryScreen::class)
        ->set('filters.result', '0')
        ->assertSee('badlogin')
        ->assertDontSee('goodlogin')
        ->set('filters.result', '')
        ->set('filters.user', 'GOODLOGIN')
        ->assertSee('goodlogin')
        ->assertDontSee('badlogin');
});

test('login history exports to excel', function () {
    Excel::fake();
    Excel::matchByRegex();

    Livewire::actingAs(userWithPermissions('admin.login_history.view'))->test(LoginHistoryScreen::class)->call('export');

    Excel::assertDownloaded('/^login-history-\d{8}-\d{6}\.xlsx$/');
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/LoginHistoryScreenTest.php`
Expected: FAIL with "Route [admin.login-history.index] not defined".

- [ ] **Step 3: Write the component**

`app/Modules/Foundation/Livewire/Admin/LoginHistory.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Models\LoginHistory as LoginEntry;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Login history')]
class LoginHistory extends Component
{
    use WithListing;

    public function mount(): void
    {
        $this->authorize('admin.login_history.view');
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('admin.login_history.view');

        return ListingExport::download('login-history', $this->filteredQuery(), [
            'When' => fn (LoginEntry $entry): string => $entry->created_at->format('d-M-Y H:i:s'),
            'Username attempted' => 'username_attempted',
            'User' => 'user.name',
            'Result' => fn (LoginEntry $entry): string => $entry->succeeded ? 'Success' : 'Failed',
            'IP' => 'ip_address',
            'User agent' => 'user_agent',
        ]);
    }

    /**
     * @return Builder<LoginEntry>
     */
    protected function listingQuery(): Builder
    {
        return LoginEntry::query()->with('user:id,name')->latest('id');
    }

    protected function searchColumns(): array
    {
        return ['username_attempted', 'ip_address'];
    }

    protected function sortColumns(): array
    {
        return ['created_at' => 'created_at'];
    }

    protected function applyFilters(Builder $query): void
    {
        $filters = $this->filters;

        if (filled($filters['user'] ?? null)) {
            $query->where('username_attempted', Str::lower(trim($filters['user'])));
        }

        if (($filters['result'] ?? '') !== '') {
            $query->where('succeeded', $filters['result'] === '1');
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
    }

    public function render(): View
    {
        return view('livewire.admin.login-history', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
        ]);
    }
}
```

- [ ] **Step 4: Write the view and route**

`resources/views/livewire/admin/login-history.blade.php`:

```blade
<div>
    <x-shell.list
        :search-placeholder="__('Search username or IP')"
        exportable
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-user">{{ __('Username') }}</x-ui.field-label>
                <x-ui.input id="filter-user" wire:model.live.debounce.300ms="filters.user" autocapitalize="none" class="h-11 text-base md:h-9 md:text-sm" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-result">{{ __('Result') }}</x-ui.field-label>
                <x-ui.select native id="filter-result" wire:model.live="filters.result" class="h-11 md:h-9">
                    <option value="">{{ __('All') }}</option>
                    <option value="1">{{ __('Succeeded') }}</option>
                    <option value="0">{{ __('Failed') }}</option>
                </x-ui.select>
            </x-ui.field>
            <div class="grid grid-cols-2 gap-2">
                <x-ui.field>
                    <x-ui.field-label for="filter-from">{{ __('From') }}</x-ui.field-label>
                    <x-ui.input id="filter-from" type="date" wire:model.live="filters.from" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="filter-to">{{ __('To') }}</x-ui.field-label>
                    <x-ui.input id="filter-to" type="date" wire:model.live="filters.to" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
            </div>
        </x-slot:filters>

        <x-slot:desktop>
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head><x-shell.sort-header key="created_at" :label="__('When')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Username attempted') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('User') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Result') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('IP') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Device') }}</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $entry)
                        <x-ui.table-row wire:key="login-{{ $entry->id }}">
                            <x-ui.table-cell class="whitespace-nowrap">{{ $entry->created_at->format('d-M-Y H:i') }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $entry->username_attempted }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $entry->user?->name ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge :tone="$entry->succeeded ? 'success' : 'danger'">{{ $entry->succeeded ? __('Success') : __('Failed') }}</x-ui.badge>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="font-mono text-sm">{{ $entry->ip_address }}</x-ui.table-cell>
                            <x-ui.table-cell class="max-w-64 truncate text-sm text-muted-foreground" title="{{ $entry->user_agent }}">{{ \Illuminate\Support\Str::limit((string) $entry->user_agent, 40) }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="6" class="py-10 text-center text-muted-foreground">{{ __('No sign-in attempts found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $entry)
                <x-ui.item variant="outline" class="min-h-16" wire:key="m-login-{{ $entry->id }}">
                    <x-ui.item-content>
                        <x-ui.item-title class="text-base">{{ $entry->username_attempted }}</x-ui.item-title>
                        <x-ui.item-description class="text-sm">{{ $entry->created_at->format('d-M-Y H:i') }} · {{ $entry->ip_address }}</x-ui.item-description>
                    </x-ui.item-content>
                    <x-ui.badge :tone="$entry->succeeded ? 'success' : 'danger'">{{ $entry->succeeded ? __('Success') : __('Failed') }}</x-ui.badge>
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No sign-in attempts found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
```

These rows have no detail screen, so they show no chevron.

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Livewire\Admin\LoginHistory;` and, inside the group:

```php
    Route::livewire('login-history', LoginHistory::class)->middleware('can:admin.login_history.view')->name('login-history.index');
```

- [ ] **Step 5: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/LoginHistoryScreenTest.php`
Expected: PASS.

- [ ] **Step 6: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Foundation/Livewire/Admin/LoginHistory.php && git commit -m "Add login history screen component with filters and export"
git add resources/views/livewire/admin/login-history.blade.php && git commit -m "Add login history view"
git add routes/modules/foundation.php && git commit -m "Add login history route"
git add tests/Feature/Foundation/Admin/LoginHistoryScreenTest.php && git commit -m "Test login history screen"
```

---

### Task 18: Two-factor authentication: enable, challenge, enforce (spec D7)

**Files:**
- Modify: `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`
- Modify: `app/Models/User.php` (`TwoFactorAuthenticatable`, QR label)
- Modify: `database/factories/UserFactory.php` (`withTwoFactor()`)
- Create: `resources/views/pages/auth/two-factor-challenge.blade.php`
- Create: `app/Modules/Foundation/Services/TwoFactorPolicy.php`
- Create: `app/Http/Middleware/EnsureTwoFactorEnabled.php`; modify `bootstrap/app.php`
- Create: `app/Modules/Foundation/Livewire/Profile/TwoFactor.php`, `resources/views/livewire/profile/two-factor.blade.php`
- Create: `app/Modules/Foundation/Livewire/Profile/TwoFactorSetup.php`, `resources/views/livewire/profile/two-factor-setup.blade.php`
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/TwoFactorTest.php`

**Interfaces:**
- Produces:
  - `TwoFactorPolicy::requires(User $user): bool`: true when any of the user's active roles is listed in `general.require_2fa_roles`.
  - `EnsureTwoFactorEnabled` middleware (in the `app` group after `EnsurePasswordChanged`). It redirects to `two-factor.setup` and lets `two-factor.setup`, `password.change` and `logout` through. Task 20 adds the impersonation bypass.
  - `<livewire:… Profile\TwoFactor :forced="bool">` panel with methods `enable()`, `confirm()`, `regenerateRecoveryCodes()`, `disable()`. Disabling requires `current_password` and is refused while `TwoFactorPolicy::requires()`.
  - Route `two-factor.setup` (`two-factor/setup`, `app` group).
  - `User::twoFactorQrCodeUrl()` labels the QR code with `username`.
  - `UserFactory::withTwoFactor()` creates a user with confirmed 2FA.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/TwoFactorTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Profile\TwoFactor;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    ensureRole('finance_manager');
});

function financeManager(): User
{
    $user = User::factory()->create(['username' => 'fm1', 'password' => Hash::make('password')]);
    $user->syncRoles(['finance_manager']);

    return $user;
}

test('users in a required role without 2FA are sent to set it up', function () {
    Settings::set('general.require_2fa_roles', ['finance_manager']);

    $this->actingAs(financeManager())->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));
    $this->get(route('two-factor.setup'))->assertOk();
});

test('nobody is forced while the role list is empty', function () {
    $this->actingAs(financeManager())->get(route('dashboard'))->assertOk();
});

test('users who already use 2FA are not redirected', function () {
    Settings::set('general.require_2fa_roles', ['finance_manager']);
    $user = User::factory()->withTwoFactor()->create();
    $user->syncRoles(['finance_manager']);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('login asks for the code when 2FA is on', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'otpuser', 'password' => Hash::make('password')]);

    $this->post(route('login.store'), ['login' => 'otpuser', 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('enabling and confirming 2FA from the panel, with the QR labelled by username', function () {
    $user = financeManager();

    $component = Livewire::actingAs($user)->test(TwoFactor::class)->call('enable');

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and(urldecode($user->twoFactorQrCodeUrl()))->toContain('fm1');

    $component->set('code', '000000')->call('confirm')->assertHasErrors(['code']);

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    $component->set('code', $code)->call('confirm')->assertHasNoErrors();

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('2FA cannot be turned off while a role requires it', function () {
    Settings::set('general.require_2fa_roles', ['finance_manager']);
    $user = User::factory()->withTwoFactor()->create(['password' => Hash::make('password')]);
    $user->syncRoles(['finance_manager']);

    Livewire::actingAs($user)->test(TwoFactor::class)
        ->set('current_password', 'password')
        ->call('disable')
        ->assertDispatched('toast', type: 'error');

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});

test('turning 2FA off needs the current password', function () {
    $user = User::factory()->withTwoFactor()->create(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(TwoFactor::class)
        ->set('current_password', 'wrong')
        ->call('disable')
        ->assertHasErrors(['current_password']);

    Livewire::actingAs($user)->test(TwoFactor::class)
        ->set('current_password', 'password')
        ->call('disable')
        ->assertHasNoErrors();

    expect($user->fresh()->two_factor_secret)->toBeNull();
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/TwoFactorTest.php`
Expected: FAIL with "Route [two-factor.setup] not defined" (and `withTwoFactor()` returning `null`).

- [ ] **Step 3: Turn on the Fortify feature and challenge view**

In `config/fortify.php`, set:

```php
    'features' => [
        Features::resetPasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],
```

In `FortifyServiceProvider::configureViews()`, add:

```php
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
```

`resources/views/pages/auth/two-factor-challenge.blade.php`:

```blade
<x-layouts::auth :title="__('Two-factor code')">
    <div class="flex flex-col gap-6" x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <x-auth-header :title="__('Two-factor authentication')" :description="__('Enter the 6-digit code from your authenticator app, or one of your recovery codes.')" />

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-ui.field x-show="! recovery">
                <x-ui.field-label for="code">{{ __('Code') }}</x-ui.field-label>
                <x-ui.input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus x-bind:disabled="recovery" class="h-11 text-base tracking-widest" :aria-invalid="$errors->has('code') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('code')" />
            </x-ui.field>

            <x-ui.field x-show="recovery" x-cloak>
                <x-ui.field-label for="recovery_code">{{ __('Recovery code') }}</x-ui.field-label>
                <x-ui.input id="recovery_code" name="recovery_code" autocomplete="off" x-bind:disabled="! recovery" class="h-11 text-base" :aria-invalid="$errors->has('recovery_code') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('recovery_code')" />
            </x-ui.field>

            <x-ui.button type="submit" class="h-11 w-full">{{ __('Continue') }}</x-ui.button>
        </form>

        <x-ui.button variant="link" class="h-11" x-on:click="recovery = ! recovery">
            <span x-show="! recovery">{{ __('Use a recovery code') }}</span>
            <span x-show="recovery" x-cloak>{{ __('Use an authentication code') }}</span>
        </x-ui.button>
    </div>
</x-layouts::auth>
```

- [ ] **Step 4: Update `User` and the factory**

In `app/Models/User.php`:
- add `use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;`, `use Laravel\Fortify\Fortify;` and `use Laravel\Fortify\TwoFactorAuthenticatable;`;
- add `TwoFactorAuthenticatable` to the `use` list in the class;
- add:

```php
    /**
     * Label the authenticator entry with the username. Fortify's default reads the `login`
     * form field name as a column, which does not exist (Fortify::username() is 'login').
     */
    public function twoFactorQrCodeUrl(): string
    {
        return app(TwoFactorAuthenticationProvider::class)->qrCodeUrl(
            (string) config('app.name'),
            $this->username,
            Fortify::currentEncrypter()->decrypt($this->two_factor_secret),
        );
    }
```

In `database/factories/UserFactory.php`, replace the empty `withTwoFactor()` with:

```php
    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt(app(\PragmaRX\Google2FA\Google2FA::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1', 'recovery-code-2'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
```

- [ ] **Step 5: Write the policy and middleware**

`app/Modules/Foundation/Services/TwoFactorPolicy.php`:

```php
<?php

namespace App\Modules\Foundation\Services;

use App\Models\User;
use App\Support\Facades\Settings;

/**
 * Decides whether a user must use 2FA (docs/01 §5.1, setting general.require_2fa_roles).
 */
final class TwoFactorPolicy
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function requires(User $user): bool
    {
        $required = array_map(strval(...), (array) Settings::get('general.require_2fa_roles', []));

        return $required !== [] && array_intersect($required, $this->registrar->rolesFor($user)) !== [];
    }
}
```

`app/Http/Middleware/EnsureTwoFactorEnabled.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Modules\Foundation\Services\TwoFactorPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends users whose role requires 2FA to the setup page until they have confirmed it.
 */
class EnsureTwoFactorEnabled
{
    public function __construct(private TwoFactorPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && ! $request->routeIs('two-factor.setup', 'password.change', 'logout')
            && ! $user->hasEnabledTwoFactorAuthentication()
            && $this->policy->requires($user)) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
```

In `bootstrap/app.php`, add `use App\Http\Middleware\EnsureTwoFactorEnabled;` and append `EnsureTwoFactorEnabled::class` to the `app` group after `EnsurePasswordChanged::class`.

- [ ] **Step 6: Write the 2FA panel**

`app/Modules/Foundation/Livewire/Profile/TwoFactor.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Profile;

use App\Models\User;
use App\Modules\Foundation\Services\TwoFactorPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Component;

/**
 * Self-service 2FA: enable → scan → confirm → recovery codes; regenerate; disable.
 * Used on the profile page and, with forced=true, on the forced setup page.
 */
class TwoFactor extends Component
{
    public bool $forced = false;

    public string $code = '';

    public string $current_password = '';

    public bool $showingRecoveryCodes = false;

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $enable($this->user());
        $this->showingRecoveryCodes = false;
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => ['required', 'string']]);

        try {
            $confirm($this->user(), $this->code);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['code' => $exception->errors()['code'] ?? [__('The code is invalid.')]]);
        }

        $this->reset('code');
        $this->showingRecoveryCodes = true;
        $this->dispatch('toast', type: 'success', description: __('Two-factor authentication is on.'));
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate($this->user());
        $this->showingRecoveryCodes = true;
    }

    public function disable(DisableTwoFactorAuthentication $disable, TwoFactorPolicy $policy): void
    {
        $this->validate(['current_password' => ['required', 'string', 'current_password']]);
        $this->reset('current_password');

        if ($policy->requires($this->user())) {
            $this->dispatch('toast', type: 'error', description: __('Your role requires two-factor authentication.'));

            return;
        }

        $disable($this->user());
        $this->showingRecoveryCodes = false;
        $this->dispatch('toast', type: 'success', description: __('Two-factor authentication is off.'));
    }

    public function render(): View
    {
        $user = $this->user()->refresh();
        $pending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;

        return view('livewire.profile.two-factor', [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'pending' => $pending,
            'qrSvg' => $pending ? $user->twoFactorQrCodeSvg() : null,
            'setupKey' => $pending ? decrypt((string) $user->two_factor_secret) : null,
            'recoveryCodes' => $this->showingRecoveryCodes && $user->two_factor_recovery_codes !== null ? $user->recoveryCodes() : [],
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
```

`resources/views/livewire/profile/two-factor.blade.php`:

```blade
<div class="flex flex-col gap-4">
    @if ($enabled)
        <x-ui.alert>
            <x-lucide-shield-check />
            <x-ui.alert-title>{{ __('Two-factor authentication is on') }}</x-ui.alert-title>
            <x-ui.alert-description>{{ __('You will be asked for a code from your authenticator app when you sign in.') }}</x-ui.alert-description>
        </x-ui.alert>
    @elseif ($pending)
        <p class="text-sm">{{ __('Scan this QR code with Google Authenticator, Microsoft Authenticator or a similar app, then enter the 6-digit code it shows.') }}</p>
        <div class="flex justify-center rounded-md border bg-white p-4 [&_svg]:size-48">{!! $qrSvg !!}</div>
        <p class="text-sm text-muted-foreground">{{ __('Or enter this key:') }} <span class="font-mono break-all">{{ $setupKey }}</span></p>
        <form wire:submit="confirm" class="flex flex-col gap-3">
            <x-ui.field>
                <x-ui.field-label for="code">{{ __('Code') }}</x-ui.field-label>
                <x-ui.input id="code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" class="h-11 text-base tracking-widest" :aria-invalid="$errors->has('code') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('code')" />
            </x-ui.field>
            <x-ui.button type="submit" class="h-11 md:h-9 md:self-start">{{ __('Confirm') }}</x-ui.button>
        </form>
    @else
        <p class="text-sm text-muted-foreground">{{ __('Add a second step to sign-in: a code from an authenticator app on your phone.') }}</p>
        <x-ui.button class="h-11 md:h-9 md:self-start" wire:click="enable">{{ __('Turn on two-factor authentication') }}</x-ui.button>
    @endif

    @if ($recoveryCodes !== [])
        <div class="flex flex-col gap-2 rounded-md border p-4">
            <p class="text-sm font-medium">{{ __('Recovery codes') }}</p>
            <p class="text-sm text-muted-foreground">{{ __('Store these somewhere safe. Each one signs you in once if you lose your phone.') }}</p>
            <ul class="grid grid-cols-1 gap-1 font-mono text-sm sm:grid-cols-2">
                @foreach ($recoveryCodes as $recoveryCode)
                    <li>{{ $recoveryCode }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($enabled)
        <div class="flex flex-col gap-3 md:flex-row md:items-end">
            <x-ui.button variant="outline" class="h-11 md:h-9" wire:click="regenerateRecoveryCodes">{{ __('New recovery codes') }}</x-ui.button>
            @if ($forced)
                <x-ui.button class="h-11 md:h-9" :href="route('dashboard')">{{ __('Continue') }}</x-ui.button>
            @else
                <form wire:submit="disable" class="flex flex-col gap-2 md:flex-row md:items-end">
                    <x-ui.field>
                        <x-ui.field-label for="tf-current-password">{{ __('Current password to turn off') }}</x-ui.field-label>
                        <x-ui.input id="tf-current-password" type="password" wire:model="current_password" autocomplete="current-password" class="h-11 text-base md:h-9 md:text-sm" />
                        <x-ui.field-error :messages="$errors->get('current_password')" />
                    </x-ui.field>
                    <x-ui.button type="submit" variant="destructive" class="h-11 md:h-9">{{ __('Turn off') }}</x-ui.button>
                </form>
            @endif
        </div>
    @endif
</div>
```

The forced "Continue" link uses a full page load, not `wire:navigate`, so the app shell re-renders after setup.

- [ ] **Step 7: Write the forced setup page and route**

`app/Modules/Foundation/Livewire/Profile/TwoFactorSetup.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Profile;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Set up two-factor authentication')]
#[Layout('layouts::auth')]
class TwoFactorSetup extends Component
{
    public function render(): View
    {
        return view('livewire.profile.two-factor-setup');
    }
}
```

`resources/views/livewire/profile/two-factor-setup.blade.php`:

```blade
<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Set up two-factor authentication')" :description="__('Your role requires a code from an authenticator app at sign-in. Set it up to continue.')" />

    <livewire:foundation.profile.two-factor :forced="true" />

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <x-ui.button type="submit" variant="link">{{ __('Log out') }}</x-ui.button>
    </form>
</div>
```

Register the module's class components under a stable tag name so Blade can render nested ones. In `FoundationServiceProvider::boot()`, add `Livewire::component('foundation.profile.two-factor', \App\Modules\Foundation\Livewire\Profile\TwoFactor::class);`. `Livewire` is already imported.

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Livewire\Profile;`. Then, outside the admin group, add:

```php
Route::middleware('app')->group(function () {
    Route::livewire('two-factor/setup', Profile\TwoFactorSetup::class)->name('two-factor.setup');
});
```

- [ ] **Step 8: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/TwoFactorTest.php tests/Feature/Auth`
Expected: PASS. If an existing auth test asserts that login lands on the dashboard for a factory user, it still passes, because factory users have no 2FA unless `withTwoFactor()` is used.

- [ ] **Step 9: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add config/fortify.php && git commit -m "Enable Fortify two-factor authentication with confirmation"
git add app/Providers/FortifyServiceProvider.php && git commit -m "Register the two-factor challenge view"
git add resources/views/pages/auth/two-factor-challenge.blade.php && git commit -m "Add two-factor challenge page"
git add app/Models/User.php && git commit -m "Use Fortify 2FA on users and label the QR code with the username" -m "Fortify::username() is the login form field, not a column."
git add database/factories/UserFactory.php && git commit -m "Make the withTwoFactor factory state work"
git add app/Modules/Foundation/Services/TwoFactorPolicy.php && git commit -m "Add policy for roles that must use 2FA"
git add app/Http/Middleware/EnsureTwoFactorEnabled.php && git commit -m "Redirect users who must use 2FA to its setup page"
git add bootstrap/app.php && git commit -m "Enforce 2FA setup in the app middleware group"
git add app/Modules/Foundation/Livewire/Profile/TwoFactor.php && git commit -m "Add self-service two-factor panel"
git add resources/views/livewire/profile/two-factor.blade.php && git commit -m "Add two-factor panel view"
git add app/Modules/Foundation/Livewire/Profile/TwoFactorSetup.php && git commit -m "Add forced two-factor setup page"
git add resources/views/livewire/profile/two-factor-setup.blade.php && git commit -m "Add forced two-factor setup view"
git add app/Modules/Foundation/FoundationServiceProvider.php && git commit -m "Register the two-factor panel component name"
git add routes/modules/foundation.php && git commit -m "Add two-factor setup route"
git add tests/Feature/Foundation/Admin/TwoFactorTest.php && git commit -m "Test 2FA setup, challenge and enforcement"
```

---

### Task 19: Profile page (details, password, 2FA, notifications)

**Files:**
- Create: `app/Modules/Foundation/Actions/UpdateProfile.php`
- Create: `app/Modules/Foundation/Livewire/Profile/Edit.php`, `resources/views/livewire/profile/edit.blade.php`
- Modify: `routes/modules/foundation.php`, `routes/settings.php`
- Modify: `resources/views/components/desktop-user-menu.blade.php`, `resources/views/components/shell/mobile-bottom-nav.blade.php`
- Delete: `resources/views/pages/settings/⚡profile.blade.php`, `resources/views/pages/settings/⚡security.blade.php`, `resources/views/pages/settings/layout.blade.php`, `resources/views/partials/settings-heading.blade.php` (replaced by the profile page)
- Rewrite in place: `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Feature/Settings/SecurityTest.php`

**Interfaces:**
- Consumes: `ChangePassword` (existing), `SaveNotificationPreferences` and `NotificationPreference::matrixFor()` (Task 4), the `foundation.profile.two-factor` panel (Task 18).
- Produces:
  - `UpdateProfile::handle(User $user, array $input, ?UploadedFile $avatar = null): User`. Input keys are `name` and `phone`. The avatar is stored on the `public` disk under `avatars/`, and the old file is deleted.
  - Route `profile.edit` (`/profile`). `settings`, `settings/profile` and `settings/security` redirect to it, and `security.edit` is removed.
  - Tabs via `#[Url] $tab`: `details`, `password`, `two-factor`, `notifications`.

- [ ] **Step 1: Rewrite the settings tests for the new page**

Replace `tests/Feature/Settings/ProfileUpdateTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Profile\Edit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk();
});

test('old settings urls redirect to the profile', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/settings')->assertRedirect('/profile');
    $this->get('/settings/profile')->assertRedirect('/profile');
    $this->get('/settings/security')->assertRedirect('/profile?tab=password');
});

test('name and phone can be updated', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Edit::class)
        ->set('name', 'Test User')
        ->set('phone', '+8801912345678')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Test User')->and($user->fresh()->phone)->toBe('01912345678');
});

test('an avatar can be uploaded and replaces the old one', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test(Edit::class);

    $component->set('avatar', UploadedFile::fake()->image('me.png', 100, 100))->call('saveDetails')->assertHasNoErrors();
    $first = $user->fresh()->avatar_path;

    $component->set('avatar', UploadedFile::fake()->image('me2.jpg', 100, 100))->call('saveDetails')->assertHasNoErrors();
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);
});

test('the profile page does not offer account deletion', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('profile.edit'))->assertDontSee(__('Delete account'));
});
```

Replace `tests/Feature/Settings/SecurityTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Profile\Edit;
use App\Modules\Foundation\Models\NotificationPreference;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('password can be updated', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(Edit::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('savePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(Edit::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('savePassword')
        ->assertHasErrors(['current_password']);
});

test('the new password follows the minimum length setting', function () {
    $this->seed(SettingSeeder::class);
    Settings::set('general.password_min_length', 12);
    $user = User::factory()->create(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(Edit::class)
        ->set('current_password', 'password')
        ->set('password', 'elevenchars')
        ->set('password_confirmation', 'elevenchars')
        ->call('savePassword')
        ->assertHasErrors(['password']);
});

test('the two-factor tab renders the 2FA panel', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit', ['tab' => 'two-factor']))
        ->assertOk()
        ->assertSee(__('Turn on two-factor authentication'));
});

test('notification preferences are saved from the profile', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Edit::class)
        ->set('notifications.security__login_new_ip.mail', false)
        ->call('saveNotifications')
        ->assertHasNoErrors();

    expect(NotificationPreference::matrixFor($user)['security.login_new_ip']['mail'])->toBeFalse();
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Settings`
Expected: FAIL with "Class ...Profile\Edit not found".

- [ ] **Step 3: Write the action**

`app/Modules/Foundation/Actions/UpdateProfile.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Self-service profile edit (docs/01 §5.13). Username and email stay admin-managed.
 */
class UpdateProfile
{
    /**
     * @param  array{name?: mixed, phone?: mixed}  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input, ?UploadedFile $avatar = null): User
    {
        $phone = (string) preg_replace('/[\s\-()]/', '', (string) ($input['phone'] ?? ''));

        /** @var array{name: string, phone: ?string} $data */
        $data = Validator::make(['name' => $input['name'] ?? '', 'phone' => $phone === '' ? null : $phone, 'avatar' => $avatar], [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^(?:\+?880|0)1[3-9]\d{8}$/'],
            'avatar' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ])->validate();

        $oldAvatar = $user->avatar_path;
        $user->fill(['name' => $data['name'], 'phone' => $data['phone']]);

        if ($avatar !== null) {
            $user->avatar_path = (string) $avatar->store('avatars', 'public');
        }

        $user->save();

        if ($avatar !== null && $oldAvatar !== null) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return $user;
    }
}
```

- [ ] **Step 4: Write the component**

`app/Modules/Foundation/Livewire/Profile/Edit.php`:

```php
<?php

namespace App\Modules\Foundation\Livewire\Profile;

use App\Models\User;
use App\Modules\Foundation\Actions\ChangePassword;
use App\Modules\Foundation\Actions\SaveNotificationPreferences;
use App\Modules\Foundation\Actions\UpdateProfile;
use App\Modules\Foundation\Models\NotificationPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Profile')]
class Edit extends Component
{
    use WithFileUploads;

    public const TABS = ['details', 'password', 'two-factor', 'notifications'];

    #[Url(except: 'details')]
    public string $tab = 'details';

    public string $name = '';

    public string $phone = '';

    /** @var UploadedFile|null */
    public $avatar = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Notification key (dots replaced by "__", so wire:model paths stay intact) → channel → enabled.
     *
     * @var array<string, array<string, bool>>
     */
    public array $notifications = [];

    public function mount(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'details';
        }

        $user = $this->user();
        $this->name = $user->name;
        $this->phone = (string) $user->phone;

        foreach (NotificationPreference::matrixFor($user) as $key => $channels) {
            $this->notifications[str_replace('.', '__', $key)] = $channels;
        }
    }

    public function saveDetails(UpdateProfile $updateProfile): void
    {
        $updateProfile->handle($this->user(), ['name' => $this->name, 'phone' => $this->phone], $this->avatar instanceof UploadedFile ? $this->avatar : null);

        $this->reset('avatar');
        $this->dispatch('toast', type: 'success', description: __('Profile saved.'));
    }

    public function savePassword(ChangePassword $changePassword): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::default()],
        ]);

        $changePassword->handle($this->user(), $this->password);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', type: 'success', description: __('Password updated.'));
    }

    public function saveNotifications(SaveNotificationPreferences $savePreferences): void
    {
        $matrix = [];

        foreach ($this->notifications as $key => $channels) {
            $matrix[str_replace('__', '.', $key)] = array_map(fn (mixed $enabled): bool => (bool) $enabled, $channels);
        }

        $savePreferences->handle($this->user(), $matrix);
        $this->dispatch('toast', type: 'success', description: __('Notification preferences saved.'));
    }

    public function render(): View
    {
        return view('livewire.profile.edit', [
            'user' => $this->user(),
            'notificationKeys' => config('notifications.keys', []),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
```

- [ ] **Step 5: Write the view**

`resources/views/livewire/profile/edit.blade.php`:

```blade
@php($tabs = ['details' => __('Details'), 'password' => __('Password'), 'two-factor' => __('Two-factor'), 'notifications' => __('Notifications')])

<div class="flex flex-col gap-4">
    <div class="flex items-center gap-3">
        <x-ui.avatar class="size-14">
            @if ($user->avatar_path)
                <x-ui.avatar-image :src="\Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path)" :alt="$user->name" />
            @endif
            <x-ui.avatar-fallback>{{ $user->initials() }}</x-ui.avatar-fallback>
        </x-ui.avatar>
        <div class="min-w-0">
            <p class="truncate text-base font-semibold">{{ $user->name }}</p>
            <p class="truncate text-sm text-muted-foreground">{{ '@'.$user->username }} @if ($user->email) · {{ $user->email }} @endif</p>
        </div>
    </div>

    <div role="tablist" class="hidden gap-1 border-b md:flex">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')"
                @class(['px-3 py-2 text-sm', 'border-b-2 border-primary font-medium' => $tab === $key, 'text-muted-foreground' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>
    <div class="overflow-x-auto md:hidden">
        <x-ui.segmented-control name="profile-tab" wire:model.live="tab" :value="$tab" :options="$tabs" class="h-11" />
    </div>

    <div class="max-w-xl">
        @if ($tab === 'details')
            <form wire:submit="saveDetails" class="flex flex-col gap-6">
                <x-ui.field>
                    <x-ui.field-label for="name">{{ __('Name') }}</x-ui.field-label>
                    <x-ui.input id="name" wire:model="name" autocomplete="name" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('name')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="phone">{{ __('Phone') }}</x-ui.field-label>
                    <x-ui.input id="phone" type="tel" inputmode="tel" wire:model="phone" placeholder="01XXXXXXXXX" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('phone')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="avatar">{{ __('Photo (PNG or JPG, up to 1 MB)') }}</x-ui.field-label>
                    <x-ui.input id="avatar" type="file" wire:model="avatar" accept="image/png,image/jpeg" class="h-11 md:h-9" />
                    <x-ui.field-error :messages="$errors->get('avatar')" />
                </x-ui.field>
                <x-ui.button type="submit" class="h-11 md:h-9 md:self-start">{{ __('Save') }}</x-ui.button>
            </form>
        @elseif ($tab === 'password')
            <form wire:submit="savePassword" class="flex flex-col gap-6">
                <x-ui.field>
                    <x-ui.field-label for="current_password">{{ __('Current password') }}</x-ui.field-label>
                    <x-ui.input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('current_password')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="password">{{ __('New password') }}</x-ui.field-label>
                    <x-ui.input id="password" type="password" wire:model="password" autocomplete="new-password" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('password')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="password_confirmation">{{ __('Confirm new password') }}</x-ui.field-label>
                    <x-ui.input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="h-11 text-base md:h-9 md:text-sm" />
                </x-ui.field>
                <x-ui.button type="submit" class="h-11 md:h-9 md:self-start">{{ __('Update password') }}</x-ui.button>
            </form>
        @elseif ($tab === 'two-factor')
            <livewire:foundation.profile.two-factor :key="'two-factor-panel'" />
        @else
            <form wire:submit="saveNotifications" class="flex flex-col gap-4">
                @foreach ($notificationKeys as $key => $definition)
                    @php($field = str_replace('.', '__', $key))
                    <div class="flex flex-col gap-1 border-b pb-3">
                        <p class="text-sm font-medium">{{ __($definition['label']) }}</p>
                        @foreach ($definition['channels'] as $channel)
                            <label class="flex min-h-11 items-center gap-3 text-sm">
                                <x-ui.switch wire:model="notifications.{{ $field }}.{{ $channel }}" :checked="(bool) ($notifications[$field][$channel] ?? true)" />
                                {{ __(match ($channel) { 'mail' => 'Email', 'sms' => 'SMS', default => 'In-app' }) }}
                            </label>
                        @endforeach
                    </div>
                @endforeach
                <x-ui.button type="submit" class="h-11 md:h-9 md:self-start">{{ __('Save preferences') }}</x-ui.button>
            </form>
        @endif
    </div>
</div>
```

- [ ] **Step 6: Wire the routes and links, and remove the old pages**

In `routes/modules/foundation.php`, extend the non-admin `app` group from Task 18:

```php
Route::middleware('app')->group(function () {
    Route::livewire('profile', Profile\Edit::class)->name('profile.edit');
    Route::livewire('two-factor/setup', Profile\TwoFactorSetup::class)->name('two-factor.setup');
});
```

Replace `routes/settings.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

/*
| The starter's settings pages were folded into /profile (docs/01 §5.13). Old links redirect.
*/

Route::middleware('app')->group(function () {
    Route::redirect('settings', '/profile');
    Route::redirect('settings/profile', '/profile');
    Route::redirect('settings/security', '/profile?tab=password');
});
```

In `resources/views/components/desktop-user-menu.blade.php`, change the `Settings` menu item to `<x-lucide-circle-user /> {{ __('Profile') }}`. The href stays `route('profile.edit')`.

In `resources/views/components/shell/mobile-bottom-nav.blade.php`:
- change the More sheet's Settings item label and icon to Profile (`circle-user`);
- change `request()->routeIs('profile.*', 'security.*')` to `request()->routeIs('profile.*')`.

Delete the replaced starter pages:

```bash
git rm "resources/views/pages/settings/⚡profile.blade.php" "resources/views/pages/settings/⚡security.blade.php" resources/views/pages/settings/layout.blade.php resources/views/partials/settings-heading.blade.php
```

Then check nothing still references them: `grep -rn "pages::settings\|settings-heading\|security.edit" app resources routes tests` should print nothing.

- [ ] **Step 7: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Settings tests/Feature/Foundation/AppShellTest.php tests/Feature/Auth`
Expected: PASS.

- [ ] **Step 8: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Foundation/Actions/UpdateProfile.php && git commit -m "Add self-service profile update action"
git add app/Modules/Foundation/Livewire/Profile/Edit.php && git commit -m "Add profile page with details, password, 2FA and notifications"
git add resources/views/livewire/profile/edit.blade.php && git commit -m "Add profile view with tabs and segmented control"
git add routes/modules/foundation.php && git commit -m "Add profile route"
git add routes/settings.php && git commit -m "Redirect old settings urls to the profile"
git add resources/views/components/desktop-user-menu.blade.php && git commit -m "Link the desktop user menu to the profile"
git add resources/views/components/shell/mobile-bottom-nav.blade.php && git commit -m "Link the mobile More sheet to the profile"
git commit -m "Remove starter settings pages replaced by the profile"
git add tests/Feature/Settings/ProfileUpdateTest.php && git commit -m "Point profile tests at the new profile page"
git add tests/Feature/Settings/SecurityTest.php && git commit -m "Point security tests at the profile password, 2FA and notification tabs"
```

---

### Task 20: Impersonation (FD-BR-10, spec D8)

**Files:**
- Create: `app/Modules/Foundation/Actions/StartImpersonation.php`, `app/Modules/Foundation/Actions/StopImpersonation.php`
- Create: `app/Http/Middleware/HandleImpersonation.php`
- Create: `resources/views/components/shell/impersonation-banner.blade.php`
- Modify: `bootstrap/app.php`, `app/Modules/Foundation/FoundationServiceProvider.php` (persistent middleware)
- Modify: `app/Http/Middleware/EnsurePasswordChanged.php`, `app/Http/Middleware/EnsureTwoFactorEnabled.php`
- Modify: `app/Modules/Foundation/Listeners/RecordAuthenticationAudit.php`
- Modify: `resources/views/layouts/app/sidebar.blade.php`
- Modify: `app/Modules/Foundation/Livewire/Admin/Users/Index.php`, `resources/views/livewire/admin/users/index.blade.php`
- Modify: `routes/modules/foundation.php`
- Test: `tests/Feature/Foundation/Admin/ImpersonationTest.php`

**Interfaces:**
- Produces:
  - `HandleImpersonation::SESSION_KEY = 'impersonator_id'`.
  - `StartImpersonation::handle(User $impersonator, User $target): void` (errors on key `user`).
  - `StopImpersonation::handle(): ?User` returns the restored impersonator.
  - Route `impersonation.stop` (POST `/impersonation/stop`).
  - `Users\Index::impersonate(int $userId)`.
  - Audit events `impersonation_started` and `impersonation_ended`, recorded on the target user with `new_values = ['impersonator_id' => …, 'user_id' => …]` and the impersonator as actor.
- **While impersonating:**
  - no `login` audit row is written, and no login history (the switch never goes through `AuthenticateUser`);
  - the password-change and 2FA middleware pass through;
  - `admin.users.*` and `admin.roles.*` return 403.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Foundation/Admin/ImpersonationTest.php`:

```php
<?php

use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use App\Modules\Foundation\Actions\StartImpersonation;
use App\Modules\Foundation\Livewire\Admin\Users\Index;
use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\LoginHistory;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = superAdmin();
    $this->target = User::factory()->create(['name' => 'Target Person']);
});

test('only super admins can impersonate, and never themselves, super admins or inactive users', function () {
    $this->actingAs($this->admin);
    $plain = userWithPermissions('admin.users.impersonate');

    expectValidationError(fn () => app(StartImpersonation::class)->handle($plain, $this->target), 'user');
    expectValidationError(fn () => app(StartImpersonation::class)->handle($this->admin, $this->admin), 'user');
    expectValidationError(fn () => app(StartImpersonation::class)->handle($this->admin, superAdmin()), 'user');
    expectValidationError(fn () => app(StartImpersonation::class)->handle($this->admin, User::factory()->inactive()->create()), 'user');
});

test('starting switches the user, audits both ids and writes no login rows', function () {
    $this->actingAs($this->admin);

    app(StartImpersonation::class)->handle($this->admin, $this->target);

    expect(Auth::id())->toBe($this->target->id)
        ->and(session(HandleImpersonation::SESSION_KEY))->toBe($this->admin->id)
        ->and(AuditLog::query()->where('event', 'impersonation_started')->first()?->new_values)->toBe(['impersonator_id' => $this->admin->id, 'user_id' => $this->target->id])
        ->and(AuditLog::query()->where('event', 'login')->where('auditable_id', $this->target->id)->exists())->toBeFalse()
        ->and(LoginHistory::query()->count())->toBe(0);
});

test('the banner shows and the forced password change is skipped while impersonating', function () {
    $this->target->forceFill(['must_change_password' => true])->save();

    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="impersonation-banner"', false)
        ->assertSee('Target Person');
});

test('user and role admin is blocked while impersonating', function () {
    createPermissions('admin.users.view');
    $this->target->syncDirectPermissions(['admin.users.view']);

    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id])
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('stopping restores the super admin and audits the end', function () {
    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id])
        ->post(route('impersonation.stop'))
        ->assertRedirect(route('admin.users.index'));

    expect(Auth::id())->toBe($this->admin->id)
        ->and(session()->has(HandleImpersonation::SESSION_KEY))->toBeFalse()
        ->and(AuditLog::query()->where('event', 'impersonation_ended')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

test('super admins start impersonation from the users list', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('impersonate', $this->target->id)
        ->assertRedirect(route('dashboard'));

    expect(Auth::id())->toBe($this->target->id);
});
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/ImpersonationTest.php`
Expected: FAIL with "Class ...HandleImpersonation not found".

- [ ] **Step 3: Write the middleware and actions**

`app/Http/Middleware/HandleImpersonation.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a super admin is signed in as someone else (FD-BR-10), user and role administration
 * is off limits so the session cannot be used to escalate the impersonated account.
 */
class HandleImpersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()
            && $request->session()->has(self::SESSION_KEY)
            && $request->routeIs('admin.users.*', 'admin.roles.*')) {
            abort(403, __('Return to your own account to manage users and roles.'));
        }

        return $next($request);
    }
}
```

`app/Modules/Foundation/Actions/StartImpersonation.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use App\Modules\Foundation\Models\Role;
use App\Support\AuditTrail\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StartImpersonation
{
    /**
     * @throws ValidationException
     */
    public function handle(User $impersonator, User $target): void
    {
        $error = match (true) {
            ! $impersonator->hasRole(Role::SUPER_ADMIN) => __('Only super admins can sign in as another user.'),
            session()->has(HandleImpersonation::SESSION_KEY) => __('Return to your own account first.'),
            $target->is($impersonator) => __('You are already signed in as yourself.'),
            $target->hasRole(Role::SUPER_ADMIN) => __('Super admins cannot be impersonated.'),
            ! $target->is_active => __('Inactive users cannot be impersonated.'),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['user' => $error]);
        }

        AuditTrail::record($target, 'impersonation_started', null, ['impersonator_id' => $impersonator->id, 'user_id' => $target->id], $impersonator);

        session()->put(HandleImpersonation::SESSION_KEY, $impersonator->id);
        Auth::guard('web')->login($target);
    }
}
```

`app/Modules/Foundation/Actions/StopImpersonation.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use App\Support\AuditTrail\AuditTrail;
use Illuminate\Support\Facades\Auth;

class StopImpersonation
{
    /**
     * Sign back in as the impersonator. The session key is removed only after the switch, so
     * the Login listener still treats the switch as part of impersonation and skips its audit.
     */
    public function handle(): ?User
    {
        $impersonatorId = session(HandleImpersonation::SESSION_KEY);
        $target = Auth::user();

        if ($impersonatorId === null || ! $target instanceof User) {
            return null;
        }

        $impersonator = User::query()->find($impersonatorId);

        if ($impersonator === null) {
            Auth::guard('web')->logout();
            session()->forget(HandleImpersonation::SESSION_KEY);

            return null;
        }

        AuditTrail::record($target, 'impersonation_ended', null, ['impersonator_id' => $impersonator->id, 'user_id' => $target->id], $impersonator);

        Auth::guard('web')->login($impersonator);
        session()->forget(HandleImpersonation::SESSION_KEY);

        return $impersonator;
    }
}
```

- [ ] **Step 4: Skip login audits and forced pages while impersonating**

In `RecordAuthenticationAudit::handleLogin()`, add as the first statement (with `use App\Http\Middleware\HandleImpersonation;`):

```php
        if (app()->bound('session') && session()->has(HandleImpersonation::SESSION_KEY)) {
            return;
        }
```

In `EnsurePasswordChanged::handle()`, change the condition to:

```php
        if ($request->user()?->must_change_password
            && ! $request->session()->has(HandleImpersonation::SESSION_KEY)
            && ! $request->routeIs('password.change', 'logout')) {
```

In `EnsureTwoFactorEnabled::handle()`, add `&& ! $request->session()->has(HandleImpersonation::SESSION_KEY)` to the condition.

In `bootstrap/app.php`, add `HandleImpersonation::class` to the `app` group right after `EnsureUserIsActive::class`, with the `use` import. In `FoundationServiceProvider::boot()`, add `HandleImpersonation::class` to the `Livewire::addPersistentMiddleware([...])` list so Livewire requests from user and role pages are blocked too.

- [ ] **Step 5: Add the banner, the stop route and the list action**

`resources/views/components/shell/impersonation-banner.blade.php`:

```blade
@php($impersonator = \App\Models\User::query()->find(session(\App\Http\Middleware\HandleImpersonation::SESSION_KEY)))

@if ($impersonator)
    <div data-test="impersonation-banner" role="status" class="flex flex-wrap items-center gap-2 bg-warning px-4 py-2 text-sm text-warning-foreground">
        <x-lucide-venetian-mask class="size-4 shrink-0" />
        <span class="flex-1">{{ __('Signed in as :name', ['name' => auth()->user()->name]) }}</span>
        <form method="POST" action="{{ route('impersonation.stop') }}">
            @csrf
            <x-ui.button type="submit" size="sm" variant="outline" class="h-11 bg-background md:h-8">{{ __('Return to :name', ['name' => $impersonator->name]) }}</x-ui.button>
        </form>
    </div>
@endif
```

Check `ls vendor/mallardduck/blade-lucide-icons/resources/svg/icons/venetian-mask.svg`. If it's missing, use `user-round-cog`.

In `resources/views/layouts/app/sidebar.blade.php`, add the banner as the first child inside `<x-ui.sidebar-inset …>`. It sits under the fixed mobile top bar because the inset already pads for it:

```blade
                @if (session()->has(\App\Http\Middleware\HandleImpersonation::SESSION_KEY))
                    <x-shell.impersonation-banner />
                @endif
```

In `routes/modules/foundation.php`, add `use App\Modules\Foundation\Actions\StopImpersonation;`. Inside the non-admin `app` group, add:

```php
    Route::post('impersonation/stop', function (StopImpersonation $stopImpersonation) {
        $stopImpersonation->handle();

        return redirect()->route('admin.users.index');
    })->name('impersonation.stop');
```

In `Users\Index`, add (with `use App\Modules\Foundation\Actions\StartImpersonation;`):

```php
    public function impersonate(int $userId, StartImpersonation $startImpersonation): void
    {
        $this->authorize('admin.users.impersonate');

        try {
            $startImpersonation->handle($this->actor(), User::query()->findOrFail($userId));
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->redirect(route('dashboard'));
    }
```

The redirect is a full page load (no `navigate`), so the shell and banner re-render for the new user.

In `resources/views/livewire/admin/users/index.blade.php`, add an Impersonate action in both the desktop dropdown and the mobile sheet. It shows only when `auth()->user()->hasRole(\App\Modules\Foundation\Models\Role::SUPER_ADMIN)` and the row is active and isn't the current user:

```blade
@if (auth()->user()->hasRole(\App\Modules\Foundation\Models\Role::SUPER_ADMIN) && $user->is_active && ! $user->is(auth()->user()))
    <x-ui.dropdown-menu-item wire:click="impersonate({{ $user->id }})">{{ __('Sign in as') }}</x-ui.dropdown-menu-item>
@endif
```

In the sheet, use the `$actionUser` variable and an `h-11` outline button with `<x-lucide-log-in />`.

- [ ] **Step 6: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Foundation/Admin/ImpersonationTest.php tests/Feature/Foundation/Admin/UsersScreenTest.php tests/Feature/Auth`
Expected: PASS.

- [ ] **Step 7: Commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/HandleImpersonation.php && git commit -m "Block user and role admin while impersonating"
git add app/Modules/Foundation/Actions/StartImpersonation.php && git commit -m "Add start impersonation action" -m "Super admins only; never self, other super admins or inactive users. Audited with both ids."
git add app/Modules/Foundation/Actions/StopImpersonation.php && git commit -m "Add stop impersonation action"
git add app/Modules/Foundation/Listeners/RecordAuthenticationAudit.php && git commit -m "Skip login audit for impersonation switches"
git add app/Http/Middleware/EnsurePasswordChanged.php && git commit -m "Skip forced password change while impersonating"
git add app/Http/Middleware/EnsureTwoFactorEnabled.php && git commit -m "Skip forced 2FA setup while impersonating"
git add bootstrap/app.php && git commit -m "Add impersonation guard to the app middleware group"
git add app/Modules/Foundation/FoundationServiceProvider.php && git commit -m "Apply impersonation guard to Livewire requests"
git add resources/views/components/shell/impersonation-banner.blade.php && git commit -m "Add impersonation banner"
git add resources/views/layouts/app/sidebar.blade.php && git commit -m "Show the impersonation banner in the app shell"
git add routes/modules/foundation.php && git commit -m "Add stop impersonation route"
git add app/Modules/Foundation/Livewire/Admin/Users/Index.php && git commit -m "Let super admins sign in as a user from the list"
git add resources/views/livewire/admin/users/index.blade.php && git commit -m "Add sign-in-as action to the users list"
git add tests/Feature/Foundation/Admin/ImpersonationTest.php && git commit -m "Test impersonation rules, banner and audit"
```

---

### Task 21: Final checks and spec touch-ups

**Files:**
- Modify: `docs/superpowers/specs/2026-10-06-admin-screens-design.md` (record deviations, mark done)

- [ ] **Step 1: Format and static analysis**

Run: `vendor/bin/pint --format agent`. Expected: no changes. If anything changes, commit it per file.
Run: `vendor/bin/phpstan analyse --memory-limit=1G`. Expected: no errors. Fix any reported errors, commit per file, and re-run.

- [ ] **Step 2: Full SQLite suite**

Run: `php artisan test --compact`
Expected: everything passes. The MySQL concurrency test is skipped.

- [ ] **Step 3: Fresh seed, re-seed and assets**

Run: `php artisan migrate:fresh --seed`, then `php artisan db:seed`. Expected: both succeed, and the second run adds no duplicate rows. Spot-check with the Boost `database-query` tool: `select count(*) from locations` stays the same after the re-seed.
Run: `php artisan storage:link` (it's fine if the link already exists) and `npm run build`. Expected: the build succeeds.

- [ ] **Step 4: Record deviations in the spec**

In the spec, record these deviations:
- **§4.3:** the `exported` audit row is recorded on the acting user, with `export` and `rows` in `new_values`.
- **§5.2:** the mobile matrix uses checkbox rows, because `x-ui.switch` cannot bind to arrays.
- **§5.8:** each entry opens in a sheet on both desktop and mobile.
- **§5.3:** delete confirmation is `wire:confirm` inside the edit sheet.
- **§5.10:** turning 2FA off needs the current password.
- **Permissions:** `admin.locations.deactivate` was added.

Change **Status** to "Done, <date>. Plan: `docs/superpowers/plans/2026-10-06-admin-screens.md`." and commit:

```bash
git add docs/superpowers/specs/2026-10-06-admin-screens-design.md && git commit -m "Record admin screens deviations and mark the spec done"
```

- [ ] **Step 5: Hand over**

Ask the user to:
- run `php artisan test --compact` themselves;
- check these screens at 390×844 and at desktop width (no browser runs unless they ask): Users list and form, Roles list and form, Master data, Branches, Locations, Company, Settings, Number sequences, Audit log, Login history, Profile (all four tabs), Two-factor setup, the 2FA challenge, and the impersonation banner;
- review `database/seeders/Foundation/data/locations.php` against the official list.
