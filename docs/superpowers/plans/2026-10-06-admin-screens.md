# Admin Screens Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> **STATUS: DRAFT — Tasks 1–10 written; Tasks 11–20 still to be written (outline at the end).**

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

<!-- PLAN IN PROGRESS: Tasks 11–20 still to be written:
11 Master data screen (MasterData component, master-data/{table} route, admin.branches.index redirect, wire:sort, sheet editor)
12 Locations screen (lazy tree, search, add child / edit / activate sheets)
13 Company profile (UpdateCompanyProfile, logo upload on public disk, x-print.letterhead preview)
14 Settings screen (UpdateSettings, tabs/segmented control, typed fields incl. roles checklist)
15 Number sequences (UpdateSequenceFormat token validation, IncreaseSequenceNumber FD-BR-07 under lockForUpdate)
16 Audit log screen (filters, old→new table, export gated by admin.audit.export)
17 Login history screen (filters, export)
18 2FA: Fortify feature, TwoFactorAuthenticatable + QR label override, challenge view, TwoFactorPolicy, EnsureTwoFactorEnabled, UserFactory::withTwoFactor fix
19 Profile page (details/password/2FA/notifications, settings/* redirect, rewrite tests/Feature/Settings in place) + two-factor/setup page
20 Impersonation (Start/StopImpersonation, HandleImpersonation, banner, listener + AuthenticateUser skips) then final checks (pint, phpstan, full suite, migrate:fresh --seed)
-->
