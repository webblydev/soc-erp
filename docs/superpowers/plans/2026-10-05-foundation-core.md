# Foundation Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Phase 1 foundation: admin-only accounts with username login, custom roles/permissions with data scope, audit trail, settings, lookups, number sequences, company/branch/currency master data, and the desktop + native-feeling mobile app shell.

**Architecture:** Foundation code lives in `app/Modules/Foundation` (Models, Actions, Services, Listeners, Concerns), with cross-module helpers in `app/Support` (Money, FiscalYear, NumberSequenceService, AuditTrail, Lookups, Settings, DataScope, Database macros). A `FoundationServiceProvider` wires migrations (`database/migrations/foundation`), singletons, facades, the permission `Gate::before`, and auth listeners. Permissions come from per-module manifest files that seeders sync into the database.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4 (single-file `pages::` components), Fortify, BlatUI (`x-ui.*`), Tailwind v4, Pest 5, brick/money 0.15 (brick/math 1.0 — `RoundingMode::HalfUp` enum case), MySQL 8 (dev), SQLite in-memory (tests).

**Status:** Done, 06 Oct 2026, merged into `master`. The visual check at 390×844 is still open and waits for the user.

**Spec:** `docs/superpowers/specs/2026-10-05-foundation-core-design.md` (source specs: `docs/00-index-and-conventions.md`, `docs/01-foundation-admin.md`)

## Global Constraints

- PHP 8.4: constructor property promotion, explicit return types, curly braces always, PHPDoc array shapes, no empty zero-arg constructors.
- Models use the `#[Fillable([...])]` / `#[Hidden([...])]` attribute style already used by `app/Models/User.php`; factories outside `App\Models` are attached with `#[UseFactory(...)]`.
- UI: BlatUI `x-ui.*` components only; light mode only — no `dark:` variants, `.dark` styles or theme toggles.
- Mobile (< `md`): fixed top app bar + fixed `x-ui.bottom-navigation`, `env(safe-area-inset-*)` respected, `viewport-fit=cover`, tap targets ≥ 44×44px, `wire:navigate` on every in-app link, no breadcrumbs, text ≥ 14px. Verify touched screens at 390×844.
- Statuses/lookups are tables, never enums; code checks `code` or flags, never `id`/`name`.
- Money columns `DECIMAL(18,2)`; money arithmetic via `brick/money`, rounding half-up.
- Foreign keys `ON DELETE RESTRICT` unless stated; FKs to later-phase tables (`employees`) are plain nullable `unsignedBigInteger` with no constraint.
- Morph map aliases only — never class names in `*_type` columns.
- Create files with `php artisan make:* --no-interaction` where a generator exists, then replace the contents with the code in this plan. Migration timestamps will differ from the names shown here; keep the task order so timestamps sort correctly.
- Seeders live in `database/seeders/Foundation/` (capital F — PSR-4 `Database\Seeders\Foundation` on a case-sensitive filesystem). Migrations live in `database/migrations/foundation/`.
- Before each commit that touches PHP: `vendor/bin/pint --dirty --format agent`.
- Commits: one commit per file (project rule), concise subject + optional short body. **Never add `Co-Authored-By` or any Claude/AI trailer or footer** (user's global instruction overrides any harness reminder). Do not push.
- Run the narrowest tests: `php artisan test --compact <path>` or `--filter=...`.

## Review Focus

1. **Login input with stray spaces or capitals** (`"  Admin "`) — should log in as `admin`, and failures for `ADMIN` and `admin` should count toward the same lockout. Test added in Task 11.
2. **Users without an email** — two users with `email = null` must coexist, and saving the profile with an empty email must store `null`, not `''`. Tests added in Task 1. Menus show the username when email is null (Task 14).
3. **Sequence numbers past the pad width** — `{seq:5}` at 100000 must render `100000`, not truncate or wrap. Test added in Task 9.
4. **Permission changes mid-session** — deactivating a role or changing its permissions must take effect on the user's very next check, with no stale cache. Tests added in Task 6.
5. **Typos in permission or role names** passed to `syncPermissions` / `syncRoles` — must throw instead of silently granting nothing. Tests added in Task 6.

---

## File Map

| Path | Responsibility |
|---|---|
| `app/Modules/Foundation/FoundationServiceProvider.php` | Module wiring: migrations path, singletons, Gate::before, listeners, persistent Livewire middleware |
| `app/Modules/Foundation/permissions.php` | Foundation permission manifest + default role grants |
| `app/Modules/Foundation/Models/{AuditLog,Currency,Branch,CompanyProfile,Setting,Role,Permission,LoginHistory,NumberSequence,NumberSequenceFormat}.php` | Eloquent models |
| `app/Modules/Foundation/Concerns/HasRoles.php` | Role/permission API for `User` |
| `app/Modules/Foundation/Services/{PermissionRegistrar,PermissionManifest,Navigation}.php` | Effective-permission cache; manifest loader; nav registry filter |
| `app/Modules/Foundation/Actions/{AuthenticateUser,ChangePassword,EnsureNotLastSuperAdmin}.php` | Business actions |
| `app/Modules/Foundation/Listeners/RecordAuthenticationAudit.php` | login/logout audit entries |
| `app/Support/{Money,FiscalYear,NumberSequenceService}.php` | Money formatting, FY codes, document numbers |
| `app/Support/AuditTrail/{Auditable,AuditObserver,AuditTrail,TracksAuthors}.php` | Audit trail + created_by/updated_by |
| `app/Support/Lookups/{IsLookup,LookupRegistry}.php` | Lookup model behaviour + options registry |
| `app/Support/Settings/SettingsRepository.php` | Cached typed settings |
| `app/Support/DataScope/HasDataScope.php` | view_all/view_team/view_own query scope |
| `app/Support/Database/BlueprintMacros.php` | `lookupColumns()`, `auditColumns()` Blueprint macros |
| `app/Support/Facades/{Lookup,Settings}.php` | Facades |
| `app/Http/Middleware/{EnsureUserIsActive,EnforceSessionTimeout,EnsurePasswordChanged}.php` | Session guards |
| `config/{lookups,navigation,foundation}.php` | Lookup registry, nav registry, initial admin |
| `database/migrations/foundation/*` | Foundation tables |
| `database/seeders/Foundation/*` | Foundation seed data |
| `resources/views/pages/auth/⚡change-password.blade.php` | Forced change-password page |
| `resources/views/components/shell/{sidebar-nav,mobile-top-bar,mobile-bottom-nav}.blade.php` | App shell pieces |

---

### Task 1: Admin-only accounts and the reworked `users` table

**Files:**
- Modify: `config/fortify.php` (features)
- Modify: `app/Providers/FortifyServiceProvider.php`
- Delete: `app/Actions/Fortify/CreateNewUser.php`, `resources/views/pages/auth/register.blade.php`, `resources/views/pages/auth/verify-email.blade.php`, `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Auth/EmailVerificationTest.php`
- Modify: `routes/web.php`, `routes/settings.php` (drop `verified`)
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`
- Modify: `app/Concerns/ProfileValidationRules.php`, `resources/views/pages/settings/⚡profile.blade.php`
- Modify: `tests/Feature/Settings/ProfileUpdateTest.php`
- Test: `tests/Feature/Auth/AccountProvisioningTest.php`

**Interfaces:**
- Produces: `users` columns `name, username, email?, phone?, password, employee_id?, branch_id?, avatar_path?, is_active, must_change_password, two_factor_*, last_login_at?, last_login_ip?, remember_token, timestamps, created_by?, updated_by?, deleted_at`. `User` uses `SoftDeletes`; casts `is_active`, `must_change_password` to bool. `UserFactory` states `inactive()`, `mustChangePassword()`; default `must_change_password = false`, `is_active = true`.

- [x] **Step 1: Write the failing test**

`tests/Feature/Auth/AccountProvisioningTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

test('public registration and email verification routes are not registered', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('verification.notice'))->toBeFalse();
});

test('users may exist without an email address', function () {
    User::factory()->count(2)->create(['email' => null]);

    expect(User::query()->whereNull('email')->count())->toBe(2);
});

test('saving the profile with an empty email stores null', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', 'Karim')
        ->set('email', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->email)->toBeNull();
});

test('new users must change their password by default', function () {
    $user = User::query()->create([
        'name' => 'Rahim',
        'username' => 'rahim',
        'password' => 'secret-password',
    ]);

    expect($user->refresh()->must_change_password)->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Auth/AccountProvisioningTest.php`
Expected: FAIL (`register` route exists; `username` column missing).

- [x] **Step 3: Disable registration and email verification**

`config/fortify.php` — replace the `features` array:

```php
    'features' => [
        Features::resetPasswords(),
    ],
```

`app/Providers/FortifyServiceProvider.php` — remove the `CreateNewUser` import, the `Fortify::createUsersUsing(...)` line, and the `registerView` and `verifyEmailView` lines. `configureActions()` becomes:

```php
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }
```

Delete the files:

```bash
git rm -q app/Actions/Fortify/CreateNewUser.php resources/views/pages/auth/register.blade.php resources/views/pages/auth/verify-email.blade.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/EmailVerificationTest.php
```

`routes/web.php` — the dashboard group becomes `Route::middleware(['auth'])->group(...)`.
`routes/settings.php` — the security group becomes `Route::middleware(['auth'])->group(...)`.

- [x] **Step 4: Rewrite the users migration**

Replace the `users` `Schema::create` block in `database/migrations/0001_01_01_000000_create_users_table.php` (leave `password_reset_tokens` and `sessions` unchanged):

```php
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('username', 60)->unique();
            $table->string('email', 150)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('password');
            $table->unsignedBigInteger('employee_id')->nullable()->unique();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('avatar_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('must_change_password')->default(true);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
        });
```

(`branch_id` gets its FK in Task 4 once `branches` exists; `employee_id` stays unconstrained until Phase 9.)

- [x] **Step 5: Update the `User` model**

`app/Models/User.php`:

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string|null $email
 * @property string|null $phone
 * @property string $password
 * @property int|null $employee_id
 * @property int|null $branch_id
 * @property string|null $avatar_path
 * @property bool $is_active
 * @property bool $must_change_password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $remember_token
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'username', 'email', 'phone', 'password', 'employee_id', 'branch_id', 'avatar_path', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
```

Note: a freshly created model does not hold DB defaults until refreshed. That's why the test calls `refresh()`. Code that reads `is_active` right after `create()` must pass it explicitly.

- [x] **Step 6: Update the factory**

`database/factories/UserFactory.php`: replace `definition()` and `unverified()`, and keep `withTwoFactor()`:

```php
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => Str::lower(fake()->unique()->lexify('user??????')),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Indicate that the user must change their password on next login.
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => ['must_change_password' => true]);
    }
```

- [x] **Step 7: Make the profile email optional and remove verification UI**

`app/Concerns/ProfileValidationRules.php` → `emailRules()`: replace `'required'` with `'nullable'`.

`resources/views/pages/settings/⚡profile.blade.php`:
- Remove the `MustVerifyEmail` and `Session` imports.
- Remove the `resendVerificationNotification()` method and the `hasUnverifiedEmail` computed property.
- `showDeleteUser` returns `true`.
- Change `public string $email = '';` to `public ?string $email = '';`. In `mount()`, use `$this->email = Auth::user()->email ?? '';`.
- In `updateProfileInformation()`, replace the fill/verification block with:

```php
        $validated = $this->validate($this->profileRules($user->id));

        $user->fill([
            'name' => $validated['name'],
            'email' => filled($validated['email'] ?? null) ? $validated['email'] : null,
        ]);

        $user->save();
```

- In the markup, remove `required` from the email input and delete the whole `@if ($this->hasUnverifiedEmail) … @endif` block.

`tests/Feature/Settings/ProfileUpdateTest.php`:
- Delete the `expect($user->email_verified_at)->toBeNull();` line.
- Delete the whole `'email verification status is unchanged when email address is unchanged'` test.

- [x] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Auth/AccountProvisioningTest.php tests/Feature/Settings tests/Feature/DashboardTest.php`
Expected: PASS. `tests/Feature/Auth/AuthenticationTest.php` still posts `email`, which works until Task 11.

- [x] **Step 9: Pint and commit (one commit per file)**

```bash
vendor/bin/pint --dirty --format agent
git add config/fortify.php && git commit -m "Disable public registration and email verification"
git add app/Providers/FortifyServiceProvider.php && git commit -m "Drop registration and verify-email Fortify wiring"
git add app/Actions/Fortify/CreateNewUser.php && git commit -m "Remove self-registration action"
git add resources/views/pages/auth/register.blade.php && git commit -m "Remove register page"
git add resources/views/pages/auth/verify-email.blade.php && git commit -m "Remove verify email page"
git add tests/Feature/Auth/RegistrationTest.php && git commit -m "Remove registration tests"
git add tests/Feature/Auth/EmailVerificationTest.php && git commit -m "Remove email verification tests"
git add routes/web.php && git commit -m "Drop verified middleware from dashboard routes"
git add routes/settings.php && git commit -m "Drop verified middleware from settings routes"
git add database/migrations/0001_01_01_000000_create_users_table.php && git commit -m "Reshape users table for admin-managed accounts"
git add app/Models/User.php && git commit -m "Add username, status and soft deletes to User"
git add database/factories/UserFactory.php && git commit -m "Add username and status states to UserFactory"
git add app/Concerns/ProfileValidationRules.php && git commit -m "Make profile email optional"
git add "resources/views/pages/settings/⚡profile.blade.php" && git commit -m "Remove email verification from profile page"
git add tests/Feature/Settings/ProfileUpdateTest.php && git commit -m "Drop email verification assertions from profile tests"
git add tests/Feature/Auth/AccountProvisioningTest.php && git commit -m "Test admin-only account provisioning"
```

---

### Task 2: Foundation module wiring and the audit trail

**Files:**
- Create: `app/Modules/Foundation/FoundationServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Create: `app/Support/Database/BlueprintMacros.php`
- Create: `database/migrations/foundation/2026_10_05_000100_create_audit_logs_table.php`
- Create: `app/Modules/Foundation/Models/AuditLog.php`
- Create: `app/Support/AuditTrail/{Auditable,AuditObserver,AuditTrail,TracksAuthors}.php`
- Modify: `app/Providers/AppServiceProvider.php` (morph map, macros)
- Modify: `app/Models/User.php` (use `Auditable`, `TracksAuthors`)
- Test: `tests/Feature/Foundation/AuditTrailTest.php`

**Interfaces:**
- Produces:
  - `Blueprint::lookupColumns()` adds `id, code(40) unique, name(120), description?, sort_order smallint 0, color(20)?, is_active 1, is_system 0, timestamps`.
  - `Blueprint::auditColumns()` adds nullable `created_by` and `updated_by` FKs to users.
  - `AuditTrail::record(Model $model, string $event, ?array $old = null, ?array $new = null, ?User $actor = null): AuditLog`
  - The `Auditable` trait (`auditExcludedAttributes(): list<string>`, `auditLogs(): MorphMany`) and the `TracksAuthors` trait (`creator()`, `updater()`).
  - `FoundationServiceProvider`: later tasks add bindings to `register()` and wiring to `boot()`.
  - The morph map is enforced in `AppServiceProvider::configureMorphMap()`; later tasks append aliases there.

- [x] **Step 1: Write the failing test**

`tests/Feature/Foundation/AuditTrailTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Models\AuditLog;
use App\Support\AuditTrail\AuditTrail;

function auditEntries(User $user, string $event)
{
    return AuditLog::query()
        ->where('auditable_type', 'user')
        ->where('auditable_id', $user->id)
        ->where('event', $event)
        ->get();
}

test('creating an auditable model writes a created entry without hidden fields', function () {
    $user = User::factory()->create(['name' => 'Rahim']);

    $entry = auditEntries($user, 'created')->sole();

    expect($entry->new_values)->toHaveKey('name', 'Rahim')
        ->and($entry->new_values)->not->toHaveKeys(['password', 'remember_token', 'created_at'])
        ->and($entry->old_values)->toBeNull();
});

test('updating writes only the changed attributes with old and new values', function () {
    $user = User::factory()->create(['phone' => '+8801811000000']);

    $user->update(['phone' => '+8801711000000']);

    $entry = auditEntries($user, 'updated')->sole();

    expect($entry->old_values)->toBe(['phone' => '+8801811000000'])
        ->and($entry->new_values)->toBe(['phone' => '+8801711000000']);
});

test('saving with only excluded attributes changed writes no updated entry', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'another-password', 'last_login_ip' => '10.0.0.1']);

    expect(auditEntries($user, 'updated'))->toBeEmpty();
});

test('deleting and restoring write entries', function () {
    $user = User::factory()->create();

    $user->delete();
    $user->restore();

    expect(auditEntries($user, 'deleted'))->toHaveCount(1)
        ->and(auditEntries($user, 'restored'))->toHaveCount(1);
});

test('custom events record the acting user', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($admin);

    $entry = AuditTrail::record($user, 'approved', null, ['note' => 'ok']);

    expect($entry->user_id)->toBe($admin->id)
        ->and($entry->event)->toBe('approved')
        ->and($entry->auditable_type)->toBe('user')
        ->and($entry->new_values)->toBe(['note' => 'ok']);
});

test('an explicit actor overrides the authenticated user', function () {
    $user = User::factory()->create();

    $entry = AuditTrail::record($user, 'login', actor: $user);

    expect($entry->user_id)->toBe($user->id);
});

test('created_by and updated_by are stamped from the authenticated user', function () {
    $admin = User::factory()->create();
    $editor = User::factory()->create();

    $this->actingAs($admin);
    $user = User::factory()->create();

    $this->actingAs($editor);
    $user->update(['name' => 'Changed']);

    expect($user->refresh()->created_by)->toBe($admin->id)
        ->and($user->updated_by)->toBe($editor->id);
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Foundation/AuditTrailTest.php`
Expected: FAIL (`AuditLog` class not found).

- [x] **Step 3: Blueprint macros**

`app/Support/Database/BlueprintMacros.php`:

```php
<?php

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;

final class BlueprintMacros
{
    /**
     * Register the schema macros for the shared column blocks in docs/00 §4.2.
     */
    public static function register(): void
    {
        Blueprint::macro('lookupColumns', function (): void {
            /** @var Blueprint $this */
            $this->id();
            $this->string('code', 40)->unique();
            $this->string('name', 120);
            $this->string('description')->nullable();
            $this->smallInteger('sort_order')->default(0);
            $this->string('color', 20)->nullable();
            $this->boolean('is_active')->default(true);
            $this->boolean('is_system')->default(false);
            $this->timestamps();
        });

        Blueprint::macro('auditColumns', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users');
            $this->foreignId('updated_by')->nullable()->constrained('users');
        });
    }
}
```

- [x] **Step 4: Foundation service provider and morph map**

`app/Modules/Foundation/FoundationServiceProvider.php`:

```php
<?php

namespace App\Modules\Foundation;

use Illuminate\Support\ServiceProvider;

class FoundationServiceProvider extends ServiceProvider
{
    /**
     * Register foundation services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap foundation services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/foundation'));
    }
}
```

`bootstrap/providers.php`: add `App\Modules\Foundation\FoundationServiceProvider::class,` after `AppServiceProvider`.

`app/Providers/AppServiceProvider.php`: add these imports:
- `use App\Models\User;`
- `use App\Support\Database\BlueprintMacros;`
- `use Illuminate\Database\Eloquent\Relations\Relation;`

Then call `BlueprintMacros::register();` in `register()`, and `$this->configureMorphMap();` in `boot()` after `configureDefaults()`. Add:

```php
    /**
     * Register morph aliases so polymorphic columns never store class names (docs/00 §4.5).
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
        ]);
    }
```

- [x] **Step 5: Migration and model**

```bash
php artisan make:migration create_audit_logs_table --path=database/migrations/foundation --no-interaction
```

```php
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 30)->index();
            $table->string('auditable_type', 40);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
```

`app/Modules/Foundation/Models/AuditLog.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $event
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'url', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [x] **Step 6: Audit trail classes**

`app/Support/AuditTrail/AuditTrail.php`:

```php
<?php

namespace App\Support\AuditTrail;

use App\Models\User;
use App\Modules\Foundation\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class AuditTrail
{
    /**
     * Write an audit_logs row for the model (CM-BR-01).
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(Model $model, string $event, ?array $old = null, ?array $new = null, ?User $actor = null): AuditLog
    {
        $request = app()->bound('request') ? request() : null;

        return AuditLog::query()->create([
            'user_id' => $actor?->id ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'url' => $request ? Str::limit($request->fullUrl(), 497) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 252) : null,
        ]);
    }

    /**
     * Remove attributes that must never be audited.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function filter(Model $model, array $attributes): array
    {
        $excluded = method_exists($model, 'auditExcludedAttributes')
            ? $model->auditExcludedAttributes()
            : $model->getHidden();

        return Arr::except($attributes, $excluded);
    }
}
```

`app/Support/AuditTrail/AuditObserver.php`:

```php
<?php

namespace App\Support\AuditTrail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditObserver
{
    public function created(Model $model): void
    {
        AuditTrail::record($model, 'created', null, AuditTrail::filter($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $new = AuditTrail::filter($model, $model->getChanges());

        if ($new === []) {
            return;
        }

        AuditTrail::record($model, 'updated', Arr::only($model->getPrevious(), array_keys($new)), $new);
    }

    public function deleted(Model $model): void
    {
        AuditTrail::record($model, 'deleted', AuditTrail::filter($model, $model->getAttributes()));
    }

    public function restored(Model $model): void
    {
        AuditTrail::record($model, 'restored');
    }
}
```

`app/Support/AuditTrail/Auditable.php`:

```php
<?php

namespace App\Support\AuditTrail;

use App\Modules\Foundation\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    /**
     * Attributes never written to the audit log.
     *
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return array_values(array_unique([
            ...$this->getHidden(),
            'created_at',
            'updated_at',
            'deleted_at',
            'created_by',
            'updated_by',
        ]));
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }
}
```

`app/Support/AuditTrail/TracksAuthors.php`:

```php
<?php

namespace App\Support\AuditTrail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait TracksAuthors
{
    public static function bootTracksAuthors(): void
    {
        static::creating(function (Model $model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            $model->setAttribute('created_by', $model->getAttribute('created_by') ?? $userId);
            $model->setAttribute('updated_by', $model->getAttribute('updated_by') ?? $userId);
        });

        static::updating(function (Model $model): void {
            if (Auth::id() !== null) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
```

- [x] **Step 7: Make `User` auditable**

`app/Models/User.php`:
- Add the imports `use App\Support\AuditTrail\Auditable;` and `use App\Support\AuditTrail\TracksAuthors;`.
- Change the trait line to `use Auditable, HasFactory, Notifiable, SoftDeletes, TracksAuthors;`.
- Add:

```php
    /**
     * Attributes never written to the audit log.
     *
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return [
            'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
            'last_login_at', 'last_login_ip', 'created_at', 'updated_at', 'deleted_at',
            'created_by', 'updated_by',
        ];
    }
```

- [x] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Foundation/AuditTrailTest.php`
Expected: PASS.

Then run `php artisan test --compact` to confirm Task 1 tests are still green.

- [x] **Step 9: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Database/BlueprintMacros.php && git commit -m "Add lookup and audit column Blueprint macros"
git add app/Modules/Foundation/FoundationServiceProvider.php && git commit -m "Add Foundation module service provider"
git add bootstrap/providers.php && git commit -m "Register Foundation service provider"
git add app/Providers/AppServiceProvider.php && git commit -m "Enforce morph map and register schema macros"
git add database/migrations/foundation && git commit -m "Create audit_logs table"
git add app/Modules/Foundation/Models/AuditLog.php && git commit -m "Add AuditLog model"
git add app/Support/AuditTrail/AuditTrail.php && git commit -m "Add AuditTrail recorder"
git add app/Support/AuditTrail/AuditObserver.php && git commit -m "Add audit observer for model lifecycle events"
git add app/Support/AuditTrail/Auditable.php && git commit -m "Add Auditable trait"
git add app/Support/AuditTrail/TracksAuthors.php && git commit -m "Add TracksAuthors trait for created_by/updated_by"
git add app/Models/User.php && git commit -m "Audit user changes and track authors"
git add tests/Feature/Foundation/AuditTrailTest.php && git commit -m "Test audit trail and author tracking"
```

---

### Task 3: Money and FiscalYear helpers

**Files:**
- Create: `app/Support/Money.php`, `app/Support/FiscalYear.php`
- Test: `tests/Unit/Support/MoneyTest.php`, `tests/Unit/Support/FiscalYearTest.php`

**Interfaces:**
- Produces:
  - `Money::of(BigNumber|int|string $amount, string $currency = 'BDT'): Brick\Money\Money`, rounding half-up to the currency scale.
  - `Money::format(Brick\Money\Money|BigNumber|int|string $amount, bool $withSymbol = true): string`.
  - `FiscalYear::for(CarbonInterface $date, int $startMonth = 7): FiscalYear` with public readonly `startYear`, `startMonth`, plus `endYear(): int`, `shortCode(): string`, `longCode(): string` and `label(): string`.

- [x] **Step 1: Write the failing tests**

`tests/Unit/Support/MoneyTest.php`:

```php
<?php

use App\Support\Money;

test('formats with Bangladeshi digit grouping and the taka symbol', function () {
    expect(Money::format('1234567'))->toBe('৳ 12,34,567.00')
        ->and(Money::format('123'))->toBe('৳ 123.00')
        ->and(Money::format('12345678.5'))->toBe('৳ 1,23,45,678.50');
});

test('formats negatives with a leading minus', function () {
    expect(Money::format('-1000'))->toBe('-৳ 1,000.00');
});

test('can omit the symbol', function () {
    expect(Money::format('100', false))->toBe('100.00');
});

test('rounds half up to two decimals', function () {
    expect((string) Money::of('10.005')->getAmount())->toBe('10.01')
        ->and((string) Money::of('10.004')->getAmount())->toBe('10.00');
});

test('uses the currency symbol of other currencies', function () {
    expect(Money::format(Money::of('1500', 'USD')))->toBe('$ 1,500.00');
});
```

`tests/Unit/Support/FiscalYearTest.php`:

```php
<?php

use App\Support\FiscalYear;
use Carbon\CarbonImmutable;

test('july starts the next bangladesh fiscal year', function () {
    $fiscalYear = FiscalYear::for(CarbonImmutable::parse('2026-07-01'));

    expect($fiscalYear->startYear)->toBe(2026)
        ->and($fiscalYear->shortCode())->toBe('27')
        ->and($fiscalYear->longCode())->toBe('2027')
        ->and($fiscalYear->label())->toBe('2026-27');
});

test('june belongs to the fiscal year that started the previous july', function () {
    $fiscalYear = FiscalYear::for(CarbonImmutable::parse('2026-06-30'));

    expect($fiscalYear->shortCode())->toBe('26')
        ->and($fiscalYear->label())->toBe('2025-26');
});

test('a january start month makes the fiscal year the calendar year', function () {
    $fiscalYear = FiscalYear::for(CarbonImmutable::parse('2026-03-01'), 1);

    expect($fiscalYear->shortCode())->toBe('26')
        ->and($fiscalYear->label())->toBe('2026');
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Support`
Expected: FAIL (classes not found).

- [x] **Step 3: Implement**

`app/Support/Money.php`:

```php
<?php

namespace App\Support;

use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;

final class Money
{
    public const BASE_CURRENCY = 'BDT';

    /** @var array<string, string> */
    private const SYMBOLS = ['BDT' => '৳', 'USD' => '$'];

    /**
     * Build a money value rounded half-up to the currency scale (CM-BR-07).
     */
    public static function of(BigNumber|int|string $amount, string $currency = self::BASE_CURRENCY): BrickMoney
    {
        return BrickMoney::of($amount, $currency, roundingMode: RoundingMode::HalfUp);
    }

    /**
     * Format as "৳ 12,34,567.00" using Bangladeshi/Indian digit grouping.
     */
    public static function format(BrickMoney|BigNumber|int|string $amount, bool $withSymbol = true): string
    {
        $money = $amount instanceof BrickMoney ? $amount : self::of($amount);
        $decimal = $money->getAmount()->toScale(2, RoundingMode::HalfUp);

        [$integer, $fraction] = explode('.', (string) $decimal->abs());

        $code = $money->getCurrency()->getCurrencyCode();
        $symbol = $withSymbol ? (self::SYMBOLS[$code] ?? $code).' ' : '';

        return ($decimal->isNegative() ? '-' : '').$symbol.self::groupDigits($integer).'.'.$fraction;
    }

    private static function groupDigits(string $integer): string
    {
        if (strlen($integer) <= 3) {
            return $integer;
        }

        $leading = strrev(implode(',', str_split(strrev(substr($integer, 0, -3)), 2)));

        return $leading.','.substr($integer, -3);
    }
}
```

`app/Support/FiscalYear.php`:

```php
<?php

namespace App\Support;

use Carbon\CarbonInterface;

final readonly class FiscalYear
{
    public function __construct(public int $startYear, public int $startMonth) {}

    /**
     * Resolve the fiscal year containing the date (Bangladesh FY starts in July by default).
     */
    public static function for(CarbonInterface $date, int $startMonth = 7): self
    {
        $startYear = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return new self($startYear, $startMonth);
    }

    public function endYear(): int
    {
        return $this->startMonth === 1 ? $this->startYear : $this->startYear + 1;
    }

    /**
     * Two-digit code used by the {yy} sequence token, e.g. "27" for FY 2026-27.
     */
    public function shortCode(): string
    {
        return substr((string) $this->endYear(), -2);
    }

    public function longCode(): string
    {
        return (string) $this->endYear();
    }

    public function label(): string
    {
        return $this->startMonth === 1
            ? (string) $this->startYear
            : $this->startYear.'-'.$this->shortCode();
    }
}
```

- [x] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Support`
Expected: PASS.

- [x] **Step 5: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Money.php && git commit -m "Add Money helper with BD digit grouping"
git add app/Support/FiscalYear.php && git commit -m "Add FiscalYear helper for FY codes"
git add tests/Unit/Support/MoneyTest.php && git commit -m "Test money formatting and rounding"
git add tests/Unit/Support/FiscalYearTest.php && git commit -m "Test fiscal year resolution"
```

---

### Task 4: Currencies, branches, company profile and the lookup registry

**Files:**
- Create migrations in `database/migrations/foundation/`: `create_currencies_table`, `create_branches_table`, `create_company_profile_table`
- Create: `app/Support/Lookups/IsLookup.php`, `app/Support/Lookups/LookupRegistry.php`, `app/Support/Facades/Lookup.php`, `config/lookups.php`
- Create: `app/Modules/Foundation/Models/{Currency,Branch,CompanyProfile}.php`
- Create: `database/factories/Foundation/{CurrencyFactory,BranchFactory}.php`
- Create: `database/seeders/Foundation/{CurrencySeeder,BranchSeeder,CompanyProfileSeeder}.php`
- Modify: `app/Modules/Foundation/FoundationServiceProvider.php`, `app/Providers/AppServiceProvider.php` (morph aliases)
- Test: `tests/Feature/Foundation/LookupTest.php`

**Interfaces:**
- Consumes: `lookupColumns()`, `auditColumns()`, `Auditable`, `TracksAuthors` (Task 2).
- Produces:
  - `Lookup::options(string $table, int|array|null $include = null): Collection<int, stdClass>`, whose rows have `id, code, name, color, is_active`.
  - `Lookup::all(): array` and `Lookup::get(string $table): array`.
  - `IsLookup` scopes `active()` and `ordered()`.
  - `CompanyProfile::current(): ?CompanyProfile` and `CompanyProfile::fiscalYearStartMonth(): int`, which defaults to 7.
  - Morph aliases `currency`, `branch` and `company`.

- [x] **Step 1: Write the failing test**

`tests/Feature/Foundation/LookupTest.php`:

```php
<?php

use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\Currency;
use App\Support\Facades\Lookup;

test('options return active rows ordered by sort order then name', function () {
    Branch::factory()->create(['code' => 'B', 'name' => 'Beta', 'sort_order' => 2]);
    Branch::factory()->create(['code' => 'A', 'name' => 'Alpha', 'sort_order' => 2]);
    Branch::factory()->create(['code' => 'Z', 'name' => 'Zulu', 'sort_order' => 1]);
    Branch::factory()->create(['code' => 'X', 'name' => 'Hidden', 'is_active' => false]);

    expect(Lookup::options('branches')->pluck('code')->all())->toBe(['Z', 'A', 'B']);
});

test('options include an inactive row that an existing record still references', function () {
    $inactive = Branch::factory()->create(['code' => 'OLD', 'is_active' => false]);
    Branch::factory()->create(['code' => 'NEW']);

    expect(Lookup::options('branches', $inactive->id)->pluck('code')->all())
        ->toContain('OLD', 'NEW');
});

test('unregistered lookup tables are rejected', function () {
    Lookup::options('users');
})->throws(InvalidArgumentException::class);

test('fiscal year start month defaults to july and follows the company profile', function () {
    expect(CompanyProfile::fiscalYearStartMonth())->toBe(7);

    CompanyProfile::query()->create([
        'name' => 'SOC Consultant & Development Ltd',
        'base_currency_id' => Currency::factory()->create(['code' => 'BDT'])->id,
        'fiscal_year_start_month' => 1,
    ]);

    expect(CompanyProfile::fiscalYearStartMonth())->toBe(1);
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Foundation/LookupTest.php`
Expected: FAIL (classes not found).

- [x] **Step 3: Migrations**

Generate the three migrations in this order with `php artisan make:migration <name> --path=database/migrations/foundation --no-interaction`, then fill them in.

`create_currencies_table`:

```php
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->lookupColumns();
            $table->string('symbol', 5);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_base')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
```

`create_branches_table`:

```php
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->lookupColumns();
            $table->text('address')->nullable();
            $table->string('phone', 60)->nullable();
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->boolean('is_head_office')->default(false);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
        });

        Schema::dropIfExists('branches');
    }
```

`create_company_profile_table`:

```php
    public function up(): void
    {
        Schema::create('company_profile', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('short_name', 40)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 150)->nullable();
            $table->string('tin', 30)->nullable();
            $table->string('bin', 30)->nullable();
            $table->string('trade_license_no', 60)->nullable();
            $table->foreignId('base_currency_id')->constrained('currencies');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(7);
            $table->string('print_footer')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profile');
    }
```

- [x] **Step 4: Lookup trait, registry, facade, config**

`app/Support/Lookups/IsLookup.php`:

```php
<?php

namespace App\Support\Lookups;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared behaviour for [LOOKUP] tables (docs/00 §4.2).
 */
trait IsLookup
{
    public function initializeIsLookup(): void
    {
        $this->mergeCasts([
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ]);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
```

`app/Support/Lookups/LookupRegistry.php`:

```php
<?php

namespace App\Support\Lookups;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

final class LookupRegistry
{
    /**
     * @param  array<string, array{label: string, module: string, permission: string, extra_fields?: list<string>}>  $tables
     */
    public function __construct(private array $tables) {}

    /**
     * @return array<string, array{label: string, module: string, permission: string, extra_fields?: list<string>}>
     */
    public function all(): array
    {
        return $this->tables;
    }

    /**
     * @return array{label: string, module: string, permission: string, extra_fields?: list<string>}
     */
    public function get(string $table): array
    {
        return $this->tables[$table]
            ?? throw new InvalidArgumentException("Lookup table [{$table}] is not registered.");
    }

    /**
     * Active options for a dropdown, plus any ids an existing record still references (CM-BR-03).
     *
     * @param  int|list<int>|null  $include
     * @return Collection<int, stdClass>
     */
    public function options(string $table, int|array|null $include = null): Collection
    {
        $this->get($table);

        $include = array_values(array_filter(Arr::wrap($include)));

        return DB::table($table)
            ->select(['id', 'code', 'name', 'color', 'is_active'])
            ->where(function (Builder $query) use ($include): void {
                $query->where('is_active', true);

                if ($include !== []) {
                    $query->orWhereIn('id', $include);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
```

`app/Support/Facades/Lookup.php`:

```php
<?php

namespace App\Support\Facades;

use App\Support\Lookups\LookupRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection<int, \stdClass> options(string $table, int|list<int>|null $include = null)
 * @method static array<string, array{label: string, module: string, permission: string, extra_fields?: list<string>}> all()
 * @method static array{label: string, module: string, permission: string, extra_fields?: list<string>} get(string $table)
 *
 * @see LookupRegistry
 */
class Lookup extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LookupRegistry::class;
    }
}
```

`config/lookups.php`:

```php
<?php

/*
| Registry of lookup tables edited through the generic Master Data screen (docs/01 §5.8).
| Each module appends its own lookup tables here.
*/

return [
    'branches' => [
        'label' => 'Branches',
        'module' => 'admin',
        'permission' => 'admin.branches',
        'extra_fields' => ['address', 'phone', 'is_head_office'],
    ],
    'currencies' => [
        'label' => 'Currencies',
        'module' => 'admin',
        'permission' => 'admin.master_data',
        'extra_fields' => ['symbol', 'decimal_places', 'is_base'],
    ],
];
```

`FoundationServiceProvider::register()` replace `//` with:

```php
        $this->app->singleton(LookupRegistry::class, fn (): LookupRegistry => new LookupRegistry(config('lookups', [])));
```

(import `App\Support\Lookups\LookupRegistry`).

- [x] **Step 5: Models and factories**

`app/Modules/Foundation/Models/Currency.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Foundation\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $decimal_places
 * @property bool $is_base
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'symbol', 'decimal_places', 'is_base'])]
#[UseFactory(CurrencyFactory::class)]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use Auditable, HasFactory, IsLookup;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_base' => 'boolean',
        ];
    }
}
```

`app/Modules/Foundation/Models/Branch.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Foundation\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property int|null $manager_employee_id
 * @property bool $is_head_office
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'address', 'phone', 'manager_employee_id', 'is_head_office'])]
#[UseFactory(BranchFactory::class)]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use Auditable, HasFactory, IsLookup;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_head_office' => 'boolean'];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
```

`app/Modules/Foundation/Models/CompanyProfile.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string|null $short_name
 * @property string|null $logo_path
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $tin
 * @property string|null $bin
 * @property string|null $trade_license_no
 * @property int $base_currency_id
 * @property int $fiscal_year_start_month
 * @property string|null $print_footer
 */
#[Fillable(['name', 'short_name', 'logo_path', 'address', 'phone', 'email', 'website', 'tin', 'bin', 'trade_license_no', 'base_currency_id', 'fiscal_year_start_month', 'print_footer'])]
class CompanyProfile extends Model
{
    use Auditable, TracksAuthors;

    protected $table = 'company_profile';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fiscal_year_start_month' => 'integer'];
    }

    /**
     * The single company profile row (docs/01 §3.3).
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    public static function fiscalYearStartMonth(): int
    {
        return (int) (static::query()->value('fiscal_year_start_month') ?? 7);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }
}
```

`database/factories/Foundation/CurrencyFactory.php`:

```php
<?php

namespace Database\Factories\Foundation;

use App\Modules\Foundation\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->currencyCode(),
            'name' => fake()->word(),
            'symbol' => '¤',
            'decimal_places' => 2,
            'is_base' => false,
            'is_active' => true,
        ];
    }
}
```

`database/factories/Foundation/BranchFactory.php`:

```php
<?php

namespace Database\Factories\Foundation;

use App\Modules\Foundation\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('BR???')),
            'name' => fake()->city().' Office',
            'sort_order' => 0,
            'is_active' => true,
            'is_head_office' => false,
        ];
    }
}
```

`AppServiceProvider::configureMorphMap()` adds `'currency' => Currency::class, 'branch' => Branch::class, 'company' => CompanyProfile::class,`, with the matching imports.

- [x] **Step 6: Seeders**

`database/seeders/Foundation/CurrencySeeder.php`:

```php
<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->firstOrCreate(['code' => 'BDT'], [
            'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'is_base' => true,
            'is_system' => true, 'sort_order' => 1,
        ]);

        Currency::query()->firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 2,
        ]);
    }
}
```

`database/seeders/Foundation/BranchSeeder.php`:

```php
<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()->firstOrCreate(['code' => 'HO'], [
            'name' => 'Head Office', 'is_head_office' => true, 'is_system' => true, 'sort_order' => 1,
        ]);
    }
}
```

`database/seeders/Foundation/CompanyProfileSeeder.php`:

```php
<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\Currency;
use Illuminate\Database\Seeder;

class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (CompanyProfile::query()->exists()) {
            return;
        }

        CompanyProfile::query()->create([
            'name' => 'SOC Consultant & Development Ltd',
            'short_name' => 'SOC',
            'base_currency_id' => Currency::query()->where('code', 'BDT')->value('id'),
            'fiscal_year_start_month' => 7,
        ]);
    }
}
```

- [x] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Foundation/LookupTest.php`
Expected: PASS.

- [x] **Step 8: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
for f in database/migrations/foundation/*currencies*; do git add "$f"; done && git commit -m "Create currencies table"
for f in database/migrations/foundation/*branches*; do git add "$f"; done && git commit -m "Create branches table and link users to branches"
for f in database/migrations/foundation/*company_profile*; do git add "$f"; done && git commit -m "Create company_profile table"
git add app/Support/Lookups/IsLookup.php && git commit -m "Add IsLookup trait for lookup tables"
git add app/Support/Lookups/LookupRegistry.php && git commit -m "Add lookup registry with dropdown options"
git add app/Support/Facades/Lookup.php && git commit -m "Add Lookup facade"
git add config/lookups.php && git commit -m "Register branches and currencies as lookups"
git add app/Modules/Foundation/FoundationServiceProvider.php && git commit -m "Bind lookup registry"
git add app/Modules/Foundation/Models/Currency.php && git commit -m "Add Currency model"
git add app/Modules/Foundation/Models/Branch.php && git commit -m "Add Branch model"
git add app/Modules/Foundation/Models/CompanyProfile.php && git commit -m "Add CompanyProfile model"
git add database/factories/Foundation/CurrencyFactory.php && git commit -m "Add Currency factory"
git add database/factories/Foundation/BranchFactory.php && git commit -m "Add Branch factory"
git add app/Providers/AppServiceProvider.php && git commit -m "Add currency, branch and company morph aliases"
git add database/seeders/Foundation/CurrencySeeder.php && git commit -m "Seed BDT and USD currencies"
git add database/seeders/Foundation/BranchSeeder.php && git commit -m "Seed head office branch"
git add database/seeders/Foundation/CompanyProfileSeeder.php && git commit -m "Seed company profile"
git add tests/Feature/Foundation/LookupTest.php && git commit -m "Test lookup options and fiscal year setting"
```

---

### Task 5: Settings

**Files:**
- Create: `database/migrations/foundation/..._create_settings_table.php`
- Create: `app/Modules/Foundation/Models/Setting.php`
- Create: `app/Support/Settings/SettingsRepository.php`, `app/Support/Facades/Settings.php`
- Create: `database/seeders/Foundation/SettingSeeder.php`
- Modify: `app/Modules/Foundation/FoundationServiceProvider.php`, `app/Providers/AppServiceProvider.php` (morph `setting`)
- Test: `tests/Feature/Foundation/SettingsTest.php`

**Interfaces:**
- Produces: `Settings::get(string $key, mixed $default = null): mixed`, `Settings::set(string $key, mixed $value): void`, `Settings::all(): array<string, mixed>`, `Settings::flush(): void`. Keys are `group.key`. Types: `string`, `int`, `bool`, `decimal`, `json`, `fk:<table>`.

- [x] **Step 1: Write the failing test**

`tests/Feature/Foundation/SettingsTest.php`:

```php
<?php

use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\Setting;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(fn () => $this->seed(SettingSeeder::class));

test('values are returned with their declared type', function () {
    expect(Settings::get('general.session_timeout_minutes'))->toBe(120)
        ->and(Settings::get('notifications.email_enabled'))->toBeTrue()
        ->and(Settings::get('general.timezone'))->toBe('Asia/Dhaka')
        ->and(Settings::get('general.require_2fa_roles'))->toBe(['finance_manager', 'super_admin']);
});

test('missing keys return the default', function () {
    expect(Settings::get('general.nope', 'fallback'))->toBe('fallback');
});

test('setting a value updates the cache and writes an audit entry', function () {
    expect(Settings::get('general.session_timeout_minutes'))->toBe(120);

    Settings::set('general.session_timeout_minutes', '30');

    expect(Settings::get('general.session_timeout_minutes'))->toBe(30)
        ->and(AuditLog::query()->where('auditable_type', 'setting')->where('event', 'updated')->count())->toBe(1);
});

test('setting an unknown key fails', function () {
    Settings::set('general.unknown', 1);
})->throws(ModelNotFoundException::class);

test('re-seeding keeps values changed by an admin', function () {
    Settings::set('general.password_min_length', 12);

    $this->seed(SettingSeeder::class);

    expect(Setting::query()->where('key', 'password_min_length')->value('value'))->toBe(12);
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Foundation/SettingsTest.php`
Expected: FAIL (class not found).

- [x] **Step 3: Migration and model**

`create_settings_table`:

```php
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40);
            $table->string('key', 80);
            $table->json('value')->nullable();
            $table->string('type', 20);
            $table->string('label', 150);
            $table->string('help')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('updated_at')->nullable();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
```

`app/Modules/Foundation/Models/Setting.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property mixed $value
 * @property string $type
 * @property string $label
 * @property string|null $help
 * @property int|null $updated_by
 */
#[Fillable(['group', 'key', 'value', 'type', 'label', 'help', 'updated_by'])]
class Setting extends Model
{
    use Auditable;

    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
```

- [x] **Step 4: Repository, facade, binding**

`app/Support/Settings/SettingsRepository.php`:

```php
<?php

namespace App\Support\Settings;

use App\Modules\Foundation\Models\Setting;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class SettingsRepository
{
    public const CACHE_KEY = 'settings';

    public function __construct(private CacheRepository $cache) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
            ->get()
            ->mapWithKeys(fn (Setting $setting): array => [
                $setting->group.'.'.$setting->key => $this->cast($setting->type, $setting->value),
            ])
            ->all());
    }

    public function set(string $key, mixed $value): void
    {
        if (! str_contains($key, '.')) {
            throw new InvalidArgumentException("Setting key [{$key}] must be in group.key form.");
        }

        [$group, $name] = explode('.', $key, 2);

        $setting = Setting::query()->where('group', $group)->where('key', $name)->firstOrFail();
        $setting->value = $this->cast($setting->type, $value);
        $setting->updated_by = Auth::id();
        $setting->save();

        $this->flush();
    }

    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    private function cast(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (true) {
            $type === 'int', str_starts_with($type, 'fk:') => (int) $value,
            $type === 'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            $type === 'decimal', $type === 'string' => (string) $value,
            $type === 'json' => (array) $value,
            default => $value,
        };
    }
}
```

`app/Support/Facades/Settings.php`:

```php
<?php

namespace App\Support\Facades;

use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static array<string, mixed> all()
 * @method static void flush()
 *
 * @see SettingsRepository
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsRepository::class;
    }
}
```

In `FoundationServiceProvider::register()`, add `$this->app->singleton(SettingsRepository::class);`. In `AppServiceProvider::configureMorphMap()`, add `'setting' => Setting::class,`. Add the imports for both.

- [x] **Step 5: Seeder**

`database/seeders/Foundation/SettingSeeder.php`:

```php
<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Setting;
use App\Support\Facades\Settings;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Initial keys from docs/01 §3.5; other modules seed their own groups.
     *
     * @var list<array{group: string, key: string, type: string, value: mixed, label: string}>
     */
    private const SETTINGS = [
        ['group' => 'general', 'key' => 'date_format', 'type' => 'string', 'value' => 'd-M-Y', 'label' => 'Date format'],
        ['group' => 'general', 'key' => 'timezone', 'type' => 'string', 'value' => 'Asia/Dhaka', 'label' => 'Timezone'],
        ['group' => 'general', 'key' => 'money_grouping', 'type' => 'string', 'value' => 'bd', 'label' => 'Money digit grouping'],
        ['group' => 'general', 'key' => 'session_timeout_minutes', 'type' => 'int', 'value' => 120, 'label' => 'Session idle timeout (minutes)'],
        ['group' => 'general', 'key' => 'password_min_length', 'type' => 'int', 'value' => 8, 'label' => 'Minimum password length'],
        ['group' => 'general', 'key' => 'require_2fa_roles', 'type' => 'json', 'value' => ['finance_manager', 'super_admin'], 'label' => 'Roles that must use two-factor authentication'],
        ['group' => 'notifications', 'key' => 'email_enabled', 'type' => 'bool', 'value' => true, 'label' => 'Send email notifications'],
        ['group' => 'notifications', 'key' => 'sms_enabled', 'type' => 'bool', 'value' => false, 'label' => 'Send SMS notifications'],
        ['group' => 'notifications', 'key' => 'daily_digest_time', 'type' => 'string', 'value' => '09:00', 'label' => 'Daily digest time'],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $setting) {
            Setting::query()->firstOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                ['type' => $setting['type'], 'value' => $setting['value'], 'label' => $setting['label']],
            );
        }

        Settings::flush();
    }
}
```

- [x] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Foundation/SettingsTest.php`
Expected: PASS.

- [x] **Step 7: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
for f in database/migrations/foundation/*settings*; do git add "$f"; done && git commit -m "Create settings table"
git add app/Modules/Foundation/Models/Setting.php && git commit -m "Add Setting model"
git add app/Support/Settings/SettingsRepository.php && git commit -m "Add cached typed settings repository"
git add app/Support/Facades/Settings.php && git commit -m "Add Settings facade"
git add app/Modules/Foundation/FoundationServiceProvider.php && git commit -m "Bind settings repository"
git add app/Providers/AppServiceProvider.php && git commit -m "Add setting morph alias"
git add database/seeders/Foundation/SettingSeeder.php && git commit -m "Seed initial general and notification settings"
git add tests/Feature/Foundation/SettingsTest.php && git commit -m "Test settings casting, caching and seeding"
```

---

### Task 6: Roles, permissions and the permission runtime

**Files:**
- Create: `database/migrations/foundation/..._create_roles_and_permissions_tables.php`
- Create: `app/Modules/Foundation/Models/{Role,Permission}.php`
- Create: `database/factories/Foundation/RoleFactory.php`
- Create: `app/Modules/Foundation/Concerns/HasRoles.php`
- Create: `app/Modules/Foundation/Services/PermissionRegistrar.php`
- Modify: `app/Models/User.php`, `app/Modules/Foundation/FoundationServiceProvider.php`, `app/Providers/AppServiceProvider.php` (morph `role`), `tests/Pest.php` (helpers)
- Test: `tests/Feature/Foundation/PermissionsTest.php`

**Interfaces:**
- Consumes: `AuditTrail::record()` (Task 2).
- Produces:
  - `PermissionRegistrar` (singleton): `rolesFor(User): list<string>`, `permissionsFor(User): list<string>`, `forget(User|int): void`, `forgetRole(Role): void`.
  - `HasRoles` on `User`: `roles(): BelongsToMany`, `directPermissions(): BelongsToMany`, `hasRole(string): bool`, `hasPermission(string): bool`, `assignRole(string): void`, `syncRoles(list<string>): void`, `syncDirectPermissions(list<string>): void`.
  - `Role::syncPermissions(list<string>)` and `Role::grantPermissions(list<string>)` (add-only). `Role::users()` and `Role::permissions()`.
  - Test helpers in `tests/Pest.php`: `createPermissions(string ...$names): void` and `ensureRole(string $code, array $attributes = []): Role`.

- [x] **Step 1: Add test helpers**

Append to `tests/Pest.php` (replace the sample `something()` function):

```php
function createPermissions(string ...$names): void
{
    foreach ($names as $name) {
        $parts = explode('.', $name);

        \App\Modules\Foundation\Models\Permission::query()->firstOrCreate(['name' => $name], [
            'module' => $parts[0],
            'resource' => count($parts) === 3 ? $parts[1] : '',
            'action' => end($parts),
        ]);
    }
}

/**
 * @param  array<string, mixed>  $attributes
 */
function ensureRole(string $code, array $attributes = []): \App\Modules\Foundation\Models\Role
{
    return \App\Modules\Foundation\Models\Role::query()->firstOrCreate(
        ['code' => $code],
        ['name' => \Illuminate\Support\Str::headline($code), ...$attributes],
    );
}
```

- [x] **Step 2: Write the failing test**

`tests/Feature/Foundation/PermissionsTest.php`:

```php
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
```

- [x] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Foundation/PermissionsTest.php`
Expected: FAIL (classes not found).

- [x] **Step 4: Migration**

`create_roles_and_permissions_tables`:

```php
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('module', 40);
            $table->string('resource', 60);
            $table->string('action', 40);
            $table->string('label', 150)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['module', 'resource']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
```

- [x] **Step 5: Models**

`app/Modules/Foundation/Models/Permission.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Seeded from module manifests only; never edited in the UI.
 *
 * @property int $id
 * @property string $name
 * @property string $module
 * @property string $resource
 * @property string $action
 * @property string|null $label
 * @property int $sort_order
 */
#[Fillable(['name', 'module', 'resource', 'action', 'label', 'sort_order'])]
class Permission extends Model
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
```

`app/Modules/Foundation/Models/Role.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use App\Modules\Foundation\Services\PermissionRegistrar;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\AuditTrail;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\Foundation\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'description', 'is_system', 'is_active'])]
#[UseFactory(RoleFactory::class)]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use Auditable, HasFactory, TracksAuthors;

    public const SUPER_ADMIN = 'super_admin';

    protected static function booted(): void
    {
        static::saved(function (Role $role): void {
            if ($role->wasChanged('is_active')) {
                app(PermissionRegistrar::class)->forgetRole($role);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * Replace the role's permissions with exactly the given names.
     *
     * @param  list<string>  $names
     */
    public function syncPermissions(array $names): void
    {
        $this->changePermissions($names, detachMissing: true);
    }

    /**
     * Add the given permissions, keeping existing grants.
     *
     * @param  list<string>  $names
     */
    public function grantPermissions(array $names): void
    {
        $this->changePermissions($names, detachMissing: false);
    }

    /**
     * @param  list<string>  $names
     */
    private function changePermissions(array $names, bool $detachMissing): void
    {
        $names = array_values(array_unique($names));
        $target = Permission::query()->whereIn('name', $names)->pluck('id');

        if ($target->count() !== count($names)) {
            throw new InvalidArgumentException('Unknown permission: '.implode(', ', array_diff($names, Permission::query()->whereIn('name', $names)->pluck('name')->all())));
        }

        $before = $this->permissions()->pluck('name')->sort()->values()->all();
        $current = $this->permissions()->pluck('permissions.id');

        if ($detachMissing) {
            $this->permissions()->detach($current->diff($target)->all());
        }

        $this->permissions()->attach(
            $target->diff($current)->mapWithKeys(fn (int $id): array => [$id => ['created_at' => now()]])->all()
        );

        $after = $this->permissions()->pluck('name')->sort()->values()->all();

        if ($before !== $after) {
            AuditTrail::record($this, 'updated', ['permissions' => $before], ['permissions' => $after]);
        }

        app(PermissionRegistrar::class)->forgetRole($this);
    }
}
```

`database/factories/Foundation/RoleFactory.php`:

```php
<?php

namespace Database\Factories\Foundation;

use App\Modules\Foundation\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'code' => Str::snake(Str::limit($name, 30, '')),
            'name' => $name,
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
```

- [x] **Step 6: Registrar and HasRoles**

`app/Modules/Foundation/Services/PermissionRegistrar.php`:

```php
<?php

namespace App\Modules\Foundation\Services;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Resolves a user's effective roles and permissions (role grants ∪ direct grants),
 * memoised per request and cached per user until a role or grant changes (docs/00 §6.1).
 */
final class PermissionRegistrar
{
    /** @var array<int, array{roles: list<string>, permissions: list<string>}> */
    private array $resolved = [];

    public function __construct(private CacheRepository $cache) {}

    /**
     * @return list<string>
     */
    public function rolesFor(User $user): array
    {
        return $this->resolve($user)['roles'];
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        return $this->resolve($user)['permissions'];
    }

    public function forget(User|int $user): void
    {
        $id = $user instanceof User ? $user->id : $user;

        unset($this->resolved[$id]);
        $this->cache->forget($this->cacheKey($id));
    }

    public function forgetRole(Role $role): void
    {
        $role->users()->pluck('users.id')->each(fn (int $id) => $this->forget($id));
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    private function resolve(User $user): array
    {
        return $this->resolved[$user->id] ??= $this->cache->rememberForever(
            $this->cacheKey($user->id),
            fn (): array => $this->load($user),
        );
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    private function load(User $user): array
    {
        $roles = $user->roles()->where('is_active', true)->with('permissions:id,name')->get();

        $permissions = $roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->merge($user->directPermissions()->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'roles' => $roles->pluck('code')->sort()->values()->all(),
            'permissions' => $permissions,
        ];
    }

    private function cacheKey(int $userId): string
    {
        return "permissions.user.{$userId}";
    }
}
```

`app/Modules/Foundation/Concerns/HasRoles.php`:

```php
<?php

namespace App\Modules\Foundation\Concerns;

use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\PermissionRegistrar;
use App\Support\AuditTrail\AuditTrail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

trait HasRoles
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function hasRole(string $code): bool
    {
        return in_array($code, app(PermissionRegistrar::class)->rolesFor($this), true);
    }

    public function hasPermission(string $name): bool
    {
        return in_array($name, app(PermissionRegistrar::class)->permissionsFor($this), true);
    }

    public function assignRole(string $code): void
    {
        $this->syncRoles([...$this->roles()->pluck('code')->all(), $code]);
    }

    /**
     * @param  list<string>  $codes
     */
    public function syncRoles(array $codes): void
    {
        $codes = array_values(array_unique($codes));
        $roles = Role::query()->whereIn('code', $codes)->get();

        if ($roles->count() !== count($codes)) {
            throw new InvalidArgumentException('Unknown role: '.implode(', ', array_diff($codes, $roles->pluck('code')->all())));
        }

        $this->syncPivot($this->roles(), 'roles', 'roles.id', $roles->pluck('id')->all(), 'code');
    }

    /**
     * @param  list<string>  $names
     */
    public function syncDirectPermissions(array $names): void
    {
        $names = array_values(array_unique($names));
        $permissions = Permission::query()->whereIn('name', $names)->get();

        if ($permissions->count() !== count($names)) {
            throw new InvalidArgumentException('Unknown permission: '.implode(', ', array_diff($names, $permissions->pluck('name')->all())));
        }

        $this->syncPivot($this->directPermissions(), 'permissions', 'permissions.id', $permissions->pluck('id')->all(), 'name');
    }

    /**
     * @param  BelongsToMany<Role, $this>|BelongsToMany<Permission, $this>  $relation
     * @param  list<int>  $targetIds
     */
    private function syncPivot(BelongsToMany $relation, string $auditKey, string $qualifiedKey, array $targetIds, string $labelColumn): void
    {
        $before = $relation->pluck($labelColumn)->sort()->values()->all();
        $current = $relation->pluck($qualifiedKey)->all();

        $relation->detach(array_values(array_diff($current, $targetIds)));
        $relation->attach(collect(array_diff($targetIds, $current))
            ->mapWithKeys(fn (int $id): array => [$id => ['created_by' => Auth::id(), 'created_at' => now()]])
            ->all());

        $after = $relation->pluck($labelColumn)->sort()->values()->all();

        if ($before !== $after) {
            AuditTrail::record($this, 'updated', [$auditKey => $before], [$auditKey => $after]);
        }

        app(PermissionRegistrar::class)->forget($this);
    }
}
```

`app/Models/User.php`: import `App\Modules\Foundation\Concerns\HasRoles` and add `HasRoles` to the trait list.

`FoundationServiceProvider`:
- In `register()`, add `$this->app->singleton(PermissionRegistrar::class);`.
- In `boot()`, add:

```php
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole(Role::SUPER_ADMIN)) {
                return true;
            }

            return str_contains($ability, '.') && $user->hasPermission($ability) ? true : null;
        });
```

  This needs the imports `App\Models\User`, `App\Modules\Foundation\Models\Role`, `App\Modules\Foundation\Services\PermissionRegistrar` and `Illuminate\Support\Facades\Gate`.
- In `AppServiceProvider::configureMorphMap()`, add `'role' => Role::class,`.

- [x] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Foundation/PermissionsTest.php`
Expected: PASS.

- [x] **Step 8: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
for f in database/migrations/foundation/*roles_and_permissions*; do git add "$f"; done && git commit -m "Create roles, permissions and grant tables"
git add app/Modules/Foundation/Models/Permission.php && git commit -m "Add Permission model"
git add app/Modules/Foundation/Models/Role.php && git commit -m "Add Role model with permission sync"
git add database/factories/Foundation/RoleFactory.php && git commit -m "Add Role factory"
git add app/Modules/Foundation/Services/PermissionRegistrar.php && git commit -m "Add cached permission registrar"
git add app/Modules/Foundation/Concerns/HasRoles.php && git commit -m "Add HasRoles trait"
git add app/Models/User.php && git commit -m "Give users roles and permissions"
git add app/Modules/Foundation/FoundationServiceProvider.php && git commit -m "Resolve gate abilities through permissions"
git add app/Providers/AppServiceProvider.php && git commit -m "Add role morph alias"
git add tests/Pest.php && git commit -m "Add permission and role test helpers"
git add tests/Feature/Foundation/PermissionsTest.php && git commit -m "Test roles, grants, cache busting and gates"
```

---

### Task 7: Permission manifests and foundation seeders

**Files:**
- Create: `app/Modules/Foundation/permissions.php`
- Create: `app/Modules/Foundation/Services/PermissionManifest.php`
- Create: `database/seeders/Foundation/{PermissionSeeder,RoleSeeder,RolePermissionSeeder,AdminUserSeeder,FoundationSeeder}.php`
- Create: `config/foundation.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `.env.example`
- Create: `tests/Fixtures/permissions/sample.php`
- Test: `tests/Feature/Foundation/PermissionManifestTest.php`, `tests/Feature/Foundation/FoundationSeederTest.php`

**Interfaces:**
- Consumes: `Role::grantPermissions()`, `User::syncRoles()` (Task 6). The seeders from Tasks 4 and 5.
- Produces:
  - `PermissionManifest::discover(): PermissionManifest` and `new PermissionManifest(list<string> $paths)`.
  - `->permissions(): list<array{name, module, resource, action, sort_order}>`.
  - `->grants(): array<string, list<string>>` (role code → patterns).
  - `->expandGrants(list<string> $names): array<string, list<string>>`.
  - `RoleSeeder::ROLES`: the 10 system roles.
  - `FoundationSeeder`, which runs all foundation seeders in order. Task 9 adds the sequence seeder to it.

- [x] **Step 1: Write the failing tests**

`tests/Fixtures/permissions/sample.php`:

```php
<?php

return [
    'permissions' => [
        'crm' => ['leads' => ['view_own', 'view_all', 'create']],
        'notes' => ['' => ['create']],
    ],
    'grants' => [
        'sales_executive' => ['crm.leads.view_own', 'notes.*'],
        'viewer' => ['*.view_all'],
    ],
];
```

`tests/Feature/Foundation/PermissionManifestTest.php`:

```php
<?php

use App\Modules\Foundation\Services\PermissionManifest;

test('manifests flatten into permission rows', function () {
    $manifest = new PermissionManifest([base_path('tests/Fixtures/permissions/sample.php')]);

    expect(collect($manifest->permissions())->pluck('name')->all())
        ->toBe(['crm.leads.view_own', 'crm.leads.view_all', 'crm.leads.create', 'notes.create'])
        ->and($manifest->permissions()[3])->toMatchArray(['module' => 'notes', 'resource' => '', 'action' => 'create']);
});

test('grant patterns expand against permission names', function () {
    $manifest = new PermissionManifest([base_path('tests/Fixtures/permissions/sample.php')]);

    $grants = $manifest->expandGrants(['crm.leads.view_own', 'crm.leads.view_all', 'notes.create']);

    expect($grants['sales_executive'])->toBe(['crm.leads.view_own', 'notes.create'])
        ->and($grants['viewer'])->toBe(['crm.leads.view_all']);
});
```

`tests/Feature/Foundation/FoundationSeederTest.php`:

```php
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
```

- [x] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Foundation/PermissionManifestTest.php tests/Feature/Foundation/FoundationSeederTest.php`
Expected: FAIL (classes not found).

- [x] **Step 3: Manifest loader**

`app/Modules/Foundation/Services/PermissionManifest.php`:

```php
<?php

namespace App\Modules\Foundation\Services;

use Illuminate\Support\Str;

/**
 * Reads app/Modules/<Module>/permissions.php files. Each returns:
 *   ['permissions' => [module => [resource => [action, ...]]], 'grants' => [role_code => [pattern, ...]]]
 * An empty resource key ('') yields a two-part name such as `notes.create`.
 */
final class PermissionManifest
{
    /**
     * @param  list<string>  $paths
     */
    public function __construct(private array $paths) {}

    public static function discover(): self
    {
        return new self(glob(app_path('Modules/*/permissions.php')) ?: []);
    }

    /**
     * @return list<array{name: string, module: string, resource: string, action: string, sort_order: int}>
     */
    public function permissions(): array
    {
        $rows = [];

        foreach ($this->manifests() as $manifest) {
            foreach ($manifest['permissions'] as $module => $resources) {
                foreach ($resources as $resource => $actions) {
                    foreach ($actions as $action) {
                        $rows[] = [
                            'name' => implode('.', array_filter([$module, (string) $resource, $action], fn (string $part): bool => $part !== '')),
                            'module' => $module,
                            'resource' => (string) $resource,
                            'action' => $action,
                            'sort_order' => count($rows) + 1,
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<string, list<string>>
     */
    public function grants(): array
    {
        $grants = [];

        foreach ($this->manifests() as $manifest) {
            foreach ($manifest['grants'] ?? [] as $role => $patterns) {
                $grants[$role] = [...($grants[$role] ?? []), ...$patterns];
            }
        }

        return $grants;
    }

    /**
     * Resolve each role's grant patterns (wildcards allowed) against the given permission names.
     *
     * @param  list<string>  $names
     * @return array<string, list<string>>
     */
    public function expandGrants(array $names): array
    {
        return array_map(
            fn (array $patterns): array => array_values(array_filter($names, fn (string $name): bool => Str::is($patterns, $name))),
            $this->grants(),
        );
    }

    /**
     * @return list<array{permissions: array<string, array<string, list<string>>>, grants?: array<string, list<string>>}>
     */
    private function manifests(): array
    {
        return array_map(fn (string $path): array => require $path, $this->paths);
    }
}
```

- [x] **Step 4: Foundation manifest**

`app/Modules/Foundation/permissions.php`:

```php
<?php

/*
| Foundation & Administration permissions (docs/01 §2) and default role grants.
| super_admin needs no grants: Gate::before lets it through everything.
*/

$collaborate = ['attachments.upload', 'attachments.delete_own', 'notes.create', 'notes.delete_own'];

return [
    'permissions' => [
        'admin' => [
            'users' => ['view', 'create', 'update', 'deactivate', 'reset_password', 'impersonate'],
            'roles' => ['view', 'create', 'update', 'delete'],
            'settings' => ['view', 'update'],
            'company' => ['view', 'update'],
            'branches' => ['view', 'create', 'update'],
            'master_data' => ['view', 'create', 'update', 'deactivate'],
            'locations' => ['view', 'create', 'update'],
            'sequences' => ['view', 'update'],
            'audit' => ['view', 'export'],
            'login_history' => ['view'],
        ],
        'attachments' => ['' => ['upload', 'delete_own', 'delete_any']],
        'notes' => ['' => ['create', 'delete_own', 'delete_any']],
    ],
    'grants' => [
        'management' => ['admin.*.view', 'admin.settings.update', 'admin.company.update', 'admin.audit.export', 'attachments.*', 'notes.*'],
        'hr_admin' => [
            'admin.users.view', 'admin.users.create', 'admin.users.update', 'admin.users.deactivate', 'admin.users.reset_password',
            'admin.branches.*', 'admin.locations.*', 'admin.master_data.view', ...$collaborate,
        ],
        'sales_manager' => $collaborate,
        'sales_executive' => $collaborate,
        'project_manager' => $collaborate,
        'engineer' => $collaborate,
        'accountant' => $collaborate,
        'finance_manager' => $collaborate,
        'viewer' => ['*.view', '*.view_all'],
    ],
];
```

- [x] **Step 5: Config and seeders**

`config/foundation.php`:

```php
<?php

return [
    /*
    | The first super admin created by AdminUserSeeder (docs/01 §4). The password is
    | required and must be changed on first login.
    */
    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME', 'System Administrator'),
        'username' => env('INITIAL_ADMIN_USERNAME', 'admin'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],
];
```

`.env.example` — append:

```dotenv
INITIAL_ADMIN_USERNAME=admin
INITIAL_ADMIN_EMAIL=
INITIAL_ADMIN_PASSWORD=
```

Add `INITIAL_ADMIN_PASSWORD=change-me-now` to the local `.env` (not committed).

`database/seeders/Foundation/PermissionSeeder.php`:

```php
<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Services\PermissionManifest;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = PermissionManifest::discover()->permissions();

        foreach ($rows as $row) {
            Permission::query()->updateOrCreate(['name' => $row['name']], $row);
        }

        Permission::query()->whereNotIn('name', array_column($rows, 'name'))->delete();
    }
}
```

`database/seeders/Foundation/RoleSeeder.php`:

```php
<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * System roles from docs/01 §2.
     *
     * @var array<string, array{name: string, description: string}>
     */
    public const ROLES = [
        'super_admin' => ['name' => 'Super Admin', 'description' => 'Full access, bypasses all policies'],
        'management' => ['name' => 'Management', 'description' => 'MD / directors: read everything, approve, settings'],
        'sales_manager' => ['name' => 'Sales Manager', 'description' => 'Sales team manager'],
        'sales_executive' => ['name' => 'Sales Executive', 'description' => 'Sales / marketing officer'],
        'project_manager' => ['name' => 'Project Manager', 'description' => 'Project / operations manager'],
        'engineer' => ['name' => 'Engineer', 'description' => 'Designer, architect, site engineer, draftsman'],
        'accountant' => ['name' => 'Accountant', 'description' => 'Accounts officer'],
        'finance_manager' => ['name' => 'Finance Manager', 'description' => 'Approves and posts finance'],
        'hr_admin' => ['name' => 'HR & Admin', 'description' => 'HR & Admin'],
        'viewer' => ['name' => 'Viewer', 'description' => 'Read-only auditor'],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $code => $role) {
            Role::query()->firstOrCreate(['code' => $code], [...$role, 'is_system' => true]);
        }
    }
}
```

`database/seeders/Foundation/RolePermissionSeeder.php`:

```php
<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\PermissionManifest;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Adds the manifest default grants to each role. Grants an admin added in the UI are kept.
     */
    public function run(): void
    {
        $names = Permission::query()->pluck('name')->all();

        foreach (PermissionManifest::discover()->expandGrants($names) as $code => $granted) {
            Role::query()->where('code', $code)->firstOrFail()->grantPermissions($granted);
        }
    }
}
```

`database/seeders/Foundation/AdminUserSeeder.php`:

```php
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
            'must_change_password' => true,
            'branch_id' => Branch::query()->where('code', 'HO')->value('id'),
        ]);

        $user->syncRoles([Role::SUPER_ADMIN]);
    }
}
```

`database/seeders/Foundation/FoundationSeeder.php`:

```php
<?php

namespace Database\Seeders\Foundation;

use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            BranchSeeder::class,
            CompanyProfileSeeder::class,
            SettingSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
```

`database/seeders/DatabaseSeeder.php` — `run()` body becomes `$this->call(FoundationSeeder::class);`. Remove the `User` import, keep `WithoutModelEvents`, and import `Database\Seeders\Foundation\FoundationSeeder`.

- [x] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Foundation/PermissionManifestTest.php tests/Feature/Foundation/FoundationSeederTest.php`
Expected: PASS.

Then run `php artisan migrate:fresh --seed` against the dev MySQL database. Expected: it completes without errors.

- [x] **Step 7: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Foundation/Services/PermissionManifest.php && git commit -m "Add permission manifest loader with wildcard grants"
git add app/Modules/Foundation/permissions.php && git commit -m "Add Foundation permission manifest and default grants"
git add config/foundation.php && git commit -m "Add initial admin configuration"
git add .env.example && git commit -m "Document initial admin environment variables"
git add database/seeders/Foundation/PermissionSeeder.php && git commit -m "Seed permissions from module manifests"
git add database/seeders/Foundation/RoleSeeder.php && git commit -m "Seed system roles"
git add database/seeders/Foundation/RolePermissionSeeder.php && git commit -m "Seed default role grants"
git add database/seeders/Foundation/AdminUserSeeder.php && git commit -m "Seed initial super admin"
git add database/seeders/Foundation/FoundationSeeder.php && git commit -m "Add Foundation seeder"
git add database/seeders/DatabaseSeeder.php && git commit -m "Run Foundation seeder from DatabaseSeeder"
git add tests/Fixtures/permissions/sample.php && git commit -m "Add sample permission manifest fixture"
git add tests/Feature/Foundation/PermissionManifestTest.php && git commit -m "Test permission manifest flattening and grants"
git add tests/Feature/Foundation/FoundationSeederTest.php && git commit -m "Test foundation seeding is complete and idempotent"
```

---

### Task 8: Last-super-admin guard and data scope

**Files:**
- Create: `app/Modules/Foundation/Actions/EnsureNotLastSuperAdmin.php`
- Create: `app/Support/DataScope/HasDataScope.php`
- Create: `tests/Fixtures/ScopedRecord.php`
- Test: `tests/Feature/Foundation/LastSuperAdminTest.php`, `tests/Feature/Foundation/DataScopeTest.php`

**Interfaces:**
- Consumes: `hasRole()`, `hasPermission()`, `roles()` (Task 6).
- Produces:
  - `EnsureNotLastSuperAdmin::handle(User $user): void`, which throws a `ValidationException` keyed `user`. Sub-project 2 calls it before deactivating, deleting or demoting a user.
  - The `HasDataScope` trait: `scopeVisibleTo(Builder, User, string $resource)`. Models must implement `dataScopeOwnerColumns(): list<string>` and `dataScopeTeamUserIds(User): list<int>`.

- [x] **Step 1: Write the failing tests**

`tests/Feature/Foundation/LastSuperAdminTest.php`:

```php
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
```

`tests/Fixtures/ScopedRecord.php`:

```php
<?php

namespace Tests\Fixtures;

use App\Models\User;
use App\Support\DataScope\HasDataScope;
use Illuminate\Database\Eloquent\Model;

class ScopedRecord extends Model
{
    use HasDataScope;

    /** @var list<int> */
    public static array $teamUserIds = [];

    protected $guarded = [];

    public $timestamps = false;

    public function dataScopeOwnerColumns(): array
    {
        return ['owner_id', 'assignee_id'];
    }

    public function dataScopeTeamUserIds(User $user): array
    {
        return static::$teamUserIds;
    }
}
```

`tests/Feature/Foundation/DataScopeTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\ScopedRecord;

beforeEach(function () {
    Schema::create('scoped_records', function (Blueprint $table) {
        $table->id();
        $table->string('label');
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->unsignedBigInteger('assignee_id')->nullable();
    });

    createPermissions('crm.leads.view_own', 'crm.leads.view_team', 'crm.leads.view_all');

    $this->me = User::factory()->create();
    $this->teammate = User::factory()->create();
    $this->stranger = User::factory()->create();

    ScopedRecord::query()->create(['label' => 'mine', 'owner_id' => $this->me->id]);
    ScopedRecord::query()->create(['label' => 'assigned', 'owner_id' => $this->stranger->id, 'assignee_id' => $this->me->id]);
    ScopedRecord::query()->create(['label' => 'team', 'owner_id' => $this->teammate->id]);
    ScopedRecord::query()->create(['label' => 'other', 'owner_id' => $this->stranger->id]);

    ScopedRecord::$teamUserIds = [$this->teammate->id];
});

function visibleLabels(User $user): array
{
    return ScopedRecord::query()->visibleTo($user, 'crm.leads')->orderBy('label')->pluck('label')->all();
}

test('view_all sees everything', function () {
    $this->me->syncDirectPermissions(['crm.leads.view_all']);

    expect(visibleLabels($this->me))->toBe(['assigned', 'mine', 'other', 'team']);
});

test('view_team sees own and team records', function () {
    $this->me->syncDirectPermissions(['crm.leads.view_team']);

    expect(visibleLabels($this->me))->toBe(['assigned', 'mine', 'team']);
});

test('view_own sees records the user owns or is assigned', function () {
    $this->me->syncDirectPermissions(['crm.leads.view_own']);

    expect(visibleLabels($this->me))->toBe(['assigned', 'mine']);
});

test('no view permission sees nothing', function () {
    expect(visibleLabels($this->me))->toBe([]);
});

test('super admin sees everything', function () {
    ensureRole('super_admin');
    $this->me->assignRole('super_admin');

    expect(visibleLabels($this->me))->toHaveCount(4);
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Foundation/LastSuperAdminTest.php tests/Feature/Foundation/DataScopeTest.php`
Expected: FAIL (classes not found).

- [x] **Step 3: Implement**

`app/Modules/Foundation/Actions/EnsureNotLastSuperAdmin.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * FD-BR-02: at least one active super admin must always exist.
 * Call before deactivating, deleting or removing the super_admin role from a user.
 */
class EnsureNotLastSuperAdmin
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user): void
    {
        if (! $user->is_active || ! $user->hasRole(Role::SUPER_ADMIN)) {
            return;
        }

        $anotherExists = User::query()
            ->whereKeyNot($user->id)
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('code', Role::SUPER_ADMIN)->where('is_active', true))
            ->exists();

        if (! $anotherExists) {
            throw ValidationException::withMessages([
                'user' => __('This is the last active super admin. Assign another super admin first.'),
            ]);
        }
    }
}
```

`app/Support/DataScope/HasDataScope.php`:

```php
<?php

namespace App\Support\DataScope;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data-scope visibility for list queries (docs/00 §6, CM-BR-08):
 * {resource}.view_all → everything, .view_team → user + team, .view_own → user, none → nothing.
 */
trait HasDataScope
{
    /**
     * Columns holding the owner / assignee / creator user id.
     *
     * @return list<string>
     */
    abstract public function dataScopeOwnerColumns(): array;

    /**
     * User ids whose records the given user may see under view_team.
     *
     * @return list<int>
     */
    abstract public function dataScopeTeamUserIds(User $user): array;

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, string $resource): void
    {
        if ($user->hasRole(Role::SUPER_ADMIN) || $user->hasPermission("{$resource}.view_all")) {
            return;
        }

        $userIds = match (true) {
            $user->hasPermission("{$resource}.view_team") => array_values(array_unique([$user->id, ...$this->dataScopeTeamUserIds($user)])),
            $user->hasPermission("{$resource}.view_own") => [$user->id],
            default => [],
        };

        if ($userIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($userIds): void {
            foreach ($this->dataScopeOwnerColumns() as $column) {
                $query->orWhereIn($this->qualifyColumn($column), $userIds);
            }
        });
    }
}
```

- [x] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Foundation/LastSuperAdminTest.php tests/Feature/Foundation/DataScopeTest.php`
Expected: PASS.

- [x] **Step 5: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Foundation/Actions/EnsureNotLastSuperAdmin.php && git commit -m "Guard the last active super admin"
git add app/Support/DataScope/HasDataScope.php && git commit -m "Add HasDataScope visibility scope"
git add tests/Fixtures/ScopedRecord.php && git commit -m "Add scoped record test fixture"
git add tests/Feature/Foundation/LastSuperAdminTest.php && git commit -m "Test last super admin guard"
git add tests/Feature/Foundation/DataScopeTest.php && git commit -m "Test data scope visibility levels"
```

---

### Task 9: Number sequences

**Files:**
- Create: `database/migrations/foundation/..._create_number_sequences_tables.php`
- Create: `app/Modules/Foundation/Models/{NumberSequence,NumberSequenceFormat}.php`
- Create: `app/Support/NumberSequenceService.php`
- Create: `database/seeders/Foundation/NumberSequenceFormatSeeder.php`
- Modify: `database/seeders/Foundation/FoundationSeeder.php`
- Test: `tests/Feature/Foundation/NumberSequenceTest.php`

**Interfaces:**
- Consumes: `FiscalYear::for()` (Task 3) and `CompanyProfile::fiscalYearStartMonth()` (Task 4).
- Produces: `NumberSequenceService::next(string $documentType, array{date?: CarbonInterface|string, bl_prefix?: string, branch?: string} $context = []): string`.
  - Throws `LogicException` when called outside a transaction.
  - Throws `InvalidArgumentException` for an unknown type or missing scope context.
  - Format tokens: `{seq:N}`, `{yy}`, `{yyyy}`, `{bl_prefix}`, `{branch}`.
  - The scope key is built from `fy:{yy}`, `bl:{prefix}` and `br:{code}`, joined with `|`.

- [x] **Step 1: Write the failing test**

`tests/Feature/Foundation/NumberSequenceTest.php`:

```php
<?php

use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use App\Support\NumberSequenceService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    NumberSequenceFormat::query()->create(['document_type' => 'invoice', 'format' => 'INV-{yy}-{seq:5}', 'reset_policy' => 'fiscal_year']);
    NumberSequenceFormat::query()->create(['document_type' => 'lead', 'format' => 'L-{seq:6}', 'reset_policy' => 'never']);
    NumberSequenceFormat::query()->create(['document_type' => 'project', 'format' => '{bl_prefix}-{seq:4}', 'reset_policy' => 'never', 'scope_by' => 'business_line']);

    $this->travelTo('2026-10-05 10:00:00');
});

function nextNumber(string $type, array $context = []): string
{
    return DB::transaction(fn () => app(NumberSequenceService::class)->next($type, $context));
}

test('fiscal year sequences render the fy code and increment', function () {
    expect(nextNumber('invoice'))->toBe('INV-27-00001')
        ->and(nextNumber('invoice'))->toBe('INV-27-00002');
});

test('global sequences never reset', function () {
    expect(nextNumber('lead'))->toBe('L-000001');

    $this->travelTo('2027-08-01');

    expect(nextNumber('lead'))->toBe('L-000002');
});

test('fiscal year sequences restart in a new fiscal year', function () {
    $this->travelTo('2027-06-30');
    expect(nextNumber('invoice'))->toBe('INV-27-00001');

    $this->travelTo('2027-07-01');
    expect(nextNumber('invoice'))->toBe('INV-28-00001');
});

test('the context date decides the fiscal year', function () {
    expect(nextNumber('invoice', ['date' => '2026-05-01']))->toBe('INV-26-00001');
});

test('business line sequences count separately per prefix', function () {
    expect(nextNumber('project', ['bl_prefix' => 'SOC-BD']))->toBe('SOC-BD-0001')
        ->and(nextNumber('project', ['bl_prefix' => 'SOC-BD']))->toBe('SOC-BD-0002')
        ->and(nextNumber('project', ['bl_prefix' => 'SOC-INT']))->toBe('SOC-INT-0001');
});

test('migrated sequences continue from their next number', function () {
    NumberSequence::query()->create([
        'document_type' => 'project', 'scope_key' => 'bl:SOC-BD', 'format' => '{bl_prefix}-{seq:4}',
        'next_number' => 103, 'reset_policy' => 'never',
    ]);

    expect(nextNumber('project', ['bl_prefix' => 'SOC-BD']))->toBe('SOC-BD-0103');
});

test('numbers wider than the padding are not truncated', function () {
    NumberSequence::query()->create([
        'document_type' => 'lead', 'scope_key' => '', 'format' => 'L-{seq:6}',
        'next_number' => 1000000, 'reset_policy' => 'never',
    ]);

    expect(nextNumber('lead'))->toBe('L-1000000');
});

test('business line scope requires a prefix', function () {
    nextNumber('project');
})->throws(InvalidArgumentException::class);

test('unknown document types are rejected', function () {
    nextNumber('nonsense');
})->throws(InvalidArgumentException::class);
```

(The "outside a transaction" guard can't be tested here because `RefreshDatabase` wraps every test in a transaction. It is tested in Task 10.)

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Foundation/NumberSequenceTest.php`
Expected: FAIL (classes not found).

- [x] **Step 3: Migration and models**

`create_number_sequences_tables`:

```php
    public function up(): void
    {
        Schema::create('number_sequence_formats', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40)->unique();
            $table->string('format', 80);
            $table->string('reset_policy', 20)->default('never');
            $table->string('scope_by', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40);
            $table->string('scope_key', 60)->default('');
            $table->string('format', 80);
            $table->unsignedInteger('next_number')->default(1);
            $table->string('reset_policy', 20);
            $table->timestamps();

            $table->unique(['document_type', 'scope_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('number_sequence_formats');
    }
```

`app/Modules/Foundation/Models/NumberSequenceFormat.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Default number format per document type (docs/00 §5).
 *
 * @property int $id
 * @property string $document_type
 * @property string $format
 * @property string $reset_policy never|fiscal_year
 * @property string|null $scope_by business_line|branch
 */
#[Fillable(['document_type', 'format', 'reset_policy', 'scope_by'])]
class NumberSequenceFormat extends Model {}
```

`app/Modules/Foundation/Models/NumberSequence.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $document_type
 * @property string $scope_key
 * @property string $format
 * @property int $next_number
 * @property string $reset_policy
 */
#[Fillable(['document_type', 'scope_key', 'format', 'next_number', 'reset_policy'])]
class NumberSequence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }
}
```

- [x] **Step 4: Service**

`app/Support/NumberSequenceService.php`:

```php
<?php

namespace App\Support;

use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Issues document numbers (docs/01 §3.8). Must run inside the caller's DB transaction so
 * the row lock is held until the document is saved (CM-BR-04, FD-AC-05).
 */
class NumberSequenceService
{
    /**
     * @param  array{date?: CarbonInterface|string, bl_prefix?: string, branch?: string}  $context
     */
    public function next(string $documentType, array $context = []): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('NumberSequenceService::next() must be called inside a database transaction.');
        }

        $definition = NumberSequenceFormat::query()->where('document_type', $documentType)->first()
            ?? throw new InvalidArgumentException("No number format is defined for [{$documentType}].");

        $fiscalYear = FiscalYear::for(
            CarbonImmutable::parse($context['date'] ?? now()),
            CompanyProfile::fiscalYearStartMonth(),
        );

        $tokens = [
            'yy' => $fiscalYear->shortCode(),
            'yyyy' => $fiscalYear->longCode(),
            'bl_prefix' => $context['bl_prefix'] ?? '',
            'branch' => $context['branch'] ?? '',
        ];

        $sequence = $this->lockSequence($definition, $this->scopeKey($definition, $tokens));
        $number = $this->render($sequence->format, $sequence->next_number, $tokens);

        $sequence->increment('next_number');

        return $number;
    }

    /**
     * @param  array{yy: string, yyyy: string, bl_prefix: string, branch: string}  $tokens
     */
    private function scopeKey(NumberSequenceFormat $definition, array $tokens): string
    {
        $parts = [];

        if ($definition->reset_policy === 'fiscal_year') {
            $parts[] = 'fy:'.$tokens['yy'];
        }

        if ($definition->scope_by === 'business_line') {
            $parts[] = 'bl:'.($tokens['bl_prefix'] !== '' ? $tokens['bl_prefix'] : throw new InvalidArgumentException("[{$definition->document_type}] numbers need a bl_prefix."));
        }

        if ($definition->scope_by === 'branch') {
            $parts[] = 'br:'.($tokens['branch'] !== '' ? $tokens['branch'] : throw new InvalidArgumentException("[{$definition->document_type}] numbers need a branch."));
        }

        return implode('|', $parts);
    }

    /**
     * Lock the scope row, creating it first if needed. The non-locking existence check followed by
     * insertOrIgnore avoids the gap-lock deadlock that SELECT … FOR UPDATE on a missing row causes in MySQL.
     */
    private function lockSequence(NumberSequenceFormat $definition, string $scopeKey): NumberSequence
    {
        $query = fn () => NumberSequence::query()
            ->where('document_type', $definition->document_type)
            ->where('scope_key', $scopeKey);

        if (! $query()->exists()) {
            NumberSequence::query()->insertOrIgnore([
                'document_type' => $definition->document_type,
                'scope_key' => $scopeKey,
                'format' => $definition->format,
                'next_number' => 1,
                'reset_policy' => $definition->reset_policy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $query()->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array{yy: string, yyyy: string, bl_prefix: string, branch: string}  $tokens
     */
    private function render(string $format, int $sequence, array $tokens): string
    {
        return (string) preg_replace_callback(
            '/\{(seq:(\d+)|yyyy|yy|bl_prefix|branch)\}/',
            fn (array $match): string => str_starts_with($match[1], 'seq:')
                ? str_pad((string) $sequence, (int) $match[2], '0', STR_PAD_LEFT)
                : $tokens[$match[1]],
            $format,
        );
    }
}
```

- [x] **Step 5: Default formats seeder**

`database/seeders/Foundation/NumberSequenceFormatSeeder.php`:

```php
<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\NumberSequenceFormat;
use Illuminate\Database\Seeder;

class NumberSequenceFormatSeeder extends Seeder
{
    /**
     * Default formats from docs/00 §5. Existing rows (possibly edited by an admin) are kept.
     *
     * @var array<string, array{0: string, 1: string, 2?: string}>
     */
    private const FORMATS = [
        'lead' => ['L-{seq:6}', 'never'],
        'customer' => ['C-{seq:6}', 'never'],
        'project' => ['{bl_prefix}-{seq:4}', 'never', 'business_line'],
        'task' => ['T-{yy}-{seq:5}', 'fiscal_year'],
        'estimate' => ['EST-{yy}-{seq:4}', 'fiscal_year'],
        'site_inspection' => ['SI-{yy}-{seq:4}', 'fiscal_year'],
        'mb_entry' => ['MB-{yy}-{seq:5}', 'fiscal_year'],
        'customer_running_bill' => ['RB-{yy}-{seq:4}', 'fiscal_year'],
        'invoice' => ['INV-{yy}-{seq:5}', 'fiscal_year'],
        'receipt' => ['RCV-{yy}-{seq:5}', 'fiscal_year'],
        'credit_note' => ['CN-{yy}-{seq:4}', 'fiscal_year'],
        'work_order' => ['WO-{yy}-{seq:4}', 'fiscal_year'],
        'vendor_bill' => ['BILL-{yy}-{seq:5}', 'fiscal_year'],
        'payment_voucher' => ['PV-{yy}-{seq:5}', 'fiscal_year'],
        'expense' => ['EXP-{yy}-{seq:5}', 'fiscal_year'],
        'employee_advance' => ['ADV-{yy}-{seq:4}', 'fiscal_year'],
        'contra' => ['CT-{yy}-{seq:5}', 'fiscal_year'],
        'journal' => ['JV-{yy}-{seq:5}', 'fiscal_year'],
        'employee' => ['EMP-{seq:4}', 'never'],
        'vendor' => ['V-{seq:4}', 'never'],
    ];

    public function run(): void
    {
        foreach (self::FORMATS as $type => $definition) {
            NumberSequenceFormat::query()->firstOrCreate(['document_type' => $type], [
                'format' => $definition[0],
                'reset_policy' => $definition[1],
                'scope_by' => $definition[2] ?? null,
            ]);
        }
    }
}
```

In `FoundationSeeder`, add `NumberSequenceFormatSeeder::class` after `SettingSeeder::class`.

- [x] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Foundation/NumberSequenceTest.php tests/Feature/Foundation/FoundationSeederTest.php`
Expected: PASS.

- [x] **Step 7: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
for f in database/migrations/foundation/*number_sequences*; do git add "$f"; done && git commit -m "Create number sequence tables"
git add app/Modules/Foundation/Models/NumberSequenceFormat.php && git commit -m "Add NumberSequenceFormat model"
git add app/Modules/Foundation/Models/NumberSequence.php && git commit -m "Add NumberSequence model"
git add app/Support/NumberSequenceService.php && git commit -m "Add locked document number service"
git add database/seeders/Foundation/NumberSequenceFormatSeeder.php && git commit -m "Seed default document number formats"
git add database/seeders/Foundation/FoundationSeeder.php && git commit -m "Seed number formats with foundation data"
git add tests/Feature/Foundation/NumberSequenceTest.php && git commit -m "Test number sequence scopes, resets and padding"
```

---

### Task 10: Isolated test suite: transaction guard and MySQL concurrency

**Files:**
- Modify: `phpunit.xml` (add `Isolated` suite), `tests/Pest.php`
- Test: `tests/Isolated/NumberSequenceIsolationTest.php`

**Interfaces:**
- Consumes: `NumberSequenceService::next()` and `NumberSequenceFormat` (Task 9).
- Produces: the `tests/Isolated` suite, which uses `DatabaseMigrations` and has no wrapping transaction. It also adds the `mysql` group, which runs only when `DB_CONNECTION=mysql`.

- [x] **Step 1: Register the suite**

`phpunit.xml` — inside `<testsuites>` add:

```xml
        <testsuite name="Isolated">
            <directory>tests/Isolated</directory>
        </testsuite>
```

`tests/Pest.php` — after the Feature `pest()` block, add:

```php
pest()->extend(TestCase::class)
    ->use(Illuminate\Foundation\Testing\DatabaseMigrations::class)
    ->in('Isolated');
```

- [x] **Step 2: Write the tests**

`tests/Isolated/NumberSequenceIsolationTest.php`:

```php
<?php

use App\Modules\Foundation\Models\NumberSequenceFormat;
use App\Support\NumberSequenceService;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

beforeEach(fn () => NumberSequenceFormat::query()->create([
    'document_type' => 'invoice', 'format' => 'INV-{yy}-{seq:5}', 'reset_policy' => 'fiscal_year',
]));

test('numbers cannot be issued outside a transaction', function () {
    app(NumberSequenceService::class)->next('invoice');
})->throws(LogicException::class);

test('fifty parallel requests never receive the same number', function () {
    $code = 'echo Illuminate\Support\Facades\DB::transaction(fn () => app(App\Support\NumberSequenceService::class)->next("invoice"));';

    $results = Process::pool(function (Pool $pool) use ($code): void {
        foreach (range(1, 50) as $i) {
            $pool->path(base_path())->timeout(120)->command(['php', 'artisan', 'tinker', '--execute', $code]);
        }
    })->start()->wait();

    $numbers = collect($results)->map(fn ($result) => trim($result->output()));

    expect($numbers->filter(fn (string $n) => str_starts_with($n, 'INV-')))->toHaveCount(50)
        ->and($numbers->unique())->toHaveCount(50);
})->group('mysql')->skip(fn () => config('database.default') !== 'mysql', 'Requires MySQL (FD-AC-05).');
```

- [x] **Step 3: Run the SQLite part**

Run: `php artisan test --compact tests/Isolated`
Expected: the guard test PASSES and the concurrency test is SKIPPED.

- [x] **Step 4: Run the MySQL concurrency test**

Create the test database. Use the `mcp__lerd__db_create` tool with name `soc_erp_testing`, or run:

```bash
mysql -h lerd-mysql -uroot -plerd -e 'CREATE DATABASE IF NOT EXISTS soc_erp_testing'
```

Then run:

```bash
DB_CONNECTION=mysql DB_HOST=lerd-mysql DB_PORT=3306 DB_DATABASE=soc_erp_testing DB_USERNAME=root DB_PASSWORD=lerd php artisan test --compact --group=mysql
```

Expected: PASS, with 50 unique numbers. If the host name doesn't resolve from the CLI, use the host and port that `mcp__lerd__status` reports. If the test fails with duplicates or deadlocks, stop and use superpowers:systematic-debugging. Don't loosen the assertion.

- [x] **Step 5: Commit (per file)**

```bash
git add phpunit.xml && git commit -m "Add isolated test suite without wrapping transaction"
git add tests/Pest.php && git commit -m "Use DatabaseMigrations for isolated tests"
git add tests/Isolated/NumberSequenceIsolationTest.php && git commit -m "Test sequence transaction guard and MySQL concurrency"
```

---

### Task 11: Username-or-email login with history, lockout and audit

**Files:**
- Create: `database/migrations/foundation/..._create_login_histories_table.php`
- Create: `app/Modules/Foundation/Models/LoginHistory.php`
- Create: `app/Modules/Foundation/Actions/AuthenticateUser.php`
- Create: `app/Modules/Foundation/Listeners/RecordAuthenticationAudit.php`
- Modify: `config/fortify.php` (`username` → `login`), `app/Providers/FortifyServiceProvider.php`, `app/Modules/Foundation/FoundationServiceProvider.php`, `resources/views/pages/auth/login.blade.php`
- Modify: `tests/Feature/Auth/AuthenticationTest.php`
- Test: `tests/Feature/Auth/LoginSecurityTest.php`

**Interfaces:**
- Consumes: `AuditTrail::record(..., actor:)` (Task 2).
- Produces:
  - `AuthenticateUser::handle(string $login, string $password, ?string $ip, ?string $userAgent): ?User`. It throws a `ValidationException` keyed `login` for a locked-out or inactive account.
  - The login form field is named `login`.
  - `AuthenticateUser::LOCKOUT_ATTEMPTS = 10` and `LOCKOUT_MINUTES = 15`.

- [x] **Step 1: Update the starter test and write the failing tests**

In `tests/Feature/Auth/AuthenticationTest.php`:
- Replace every `'email' => $user->email,` in the POST payloads with `'login' => $user->username,`.
- Replace `assertSessionHasErrorsIn('email')` with `assertSessionHasErrors('login')`.

`tests/Feature/Auth/LoginSecurityTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\LoginHistory;

function attemptLogin(string $login, string $password = 'password')
{
    return test()->post(route('login.store'), ['login' => $login, 'password' => $password]);
}

test('users can log in with their username in any case and surrounding spaces', function () {
    User::factory()->create(['username' => 'rahim']);

    attemptLogin('  Rahim ')->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can log in with their email', function () {
    $user = User::factory()->create(['email' => 'rahim@soc.test']);

    attemptLogin('RAHIM@soc.test');

    $this->assertAuthenticatedAs($user);
});

test('inactive users are refused', function () {
    User::factory()->inactive()->create(['username' => 'karim']);

    attemptLogin('karim')->assertSessionHasErrors(['login' => 'This account is inactive.']);

    $this->assertGuest();
});

test('every attempt is written to login history', function () {
    $user = User::factory()->create(['username' => 'rahim']);

    attemptLogin('rahim', 'wrong');
    attemptLogin('nobody', 'wrong');
    attemptLogin('rahim');

    expect(LoginHistory::query()->orderBy('id')->get(['user_id', 'username_attempted', 'succeeded'])->toArray())->toBe([
        ['user_id' => $user->id, 'username_attempted' => 'rahim', 'succeeded' => false],
        ['user_id' => null, 'username_attempted' => 'nobody', 'succeeded' => false],
        ['user_id' => $user->id, 'username_attempted' => 'rahim', 'succeeded' => true],
    ]);
});

test('a successful login stamps last login and is audited', function () {
    $user = User::factory()->create(['username' => 'rahim']);

    attemptLogin('rahim');

    $user->refresh();

    expect($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->toBe('127.0.0.1')
        ->and(AuditLog::query()->where('event', 'login')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('logging out is audited', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'));

    expect(AuditLog::query()->where('event', 'logout')->where('auditable_id', $user->id)->exists())->toBeTrue();
});

test('five wrong passwords trigger the rate limit and are all recorded', function () {
    User::factory()->create(['username' => 'rahim']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin('rahim', 'wrong');
    }

    attemptLogin('rahim')->assertSessionHasErrors('login');

    $this->assertGuest();
    expect(LoginHistory::query()->where('succeeded', false)->count())->toBe(5);
});

test('ten failures within fifteen minutes lock the username for fifteen minutes', function () {
    User::factory()->create(['username' => 'rahim']);

    foreach (range(1, 10) as $attempt) {
        LoginHistory::query()->create(['username_attempted' => 'rahim', 'succeeded' => false, 'ip_address' => '10.0.0.'.$attempt]);
    }

    attemptLogin('RAHIM')->assertSessionHasErrors('login');
    $this->assertGuest();

    $this->travel(16)->minutes();

    attemptLogin('rahim');
    $this->assertAuthenticated();
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Auth`
Expected: FAIL (`login` field unknown, `LoginHistory` missing).

- [x] **Step 3: Migration and model**

`create_login_histories_table`:

```php
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username_attempted', 60);
            $table->boolean('succeeded');
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['username_attempted', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
```

`app/Modules/Foundation/Models/LoginHistory.php`:

```php
<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $username_attempted
 * @property bool $succeeded
 * @property string $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'username_attempted', 'succeeded', 'ip_address', 'user_agent'])]
class LoginHistory extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['succeeded' => 'boolean', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [x] **Step 4: Authentication action**

`app/Modules/Foundation/Actions/AuthenticateUser.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\LoginHistory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Username-or-email login (docs/01 §5.1) with login history and FD-BR-04 lockout.
 */
class AuthenticateUser
{
    public const LOCKOUT_ATTEMPTS = 10;

    public const LOCKOUT_MINUTES = 15;

    /**
     * @throws ValidationException
     */
    public function handle(string $login, string $password, ?string $ip, ?string $userAgent): ?User
    {
        $login = Str::lower(trim($login));

        if ($this->isLockedOut($login)) {
            throw ValidationException::withMessages([
                'login' => __('Too many failed attempts. Try again in :minutes minutes.', ['minutes' => self::LOCKOUT_MINUTES]),
            ]);
        }

        $user = User::query()
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(username) = ?', [$login])
                ->orWhereRaw('LOWER(email) = ?', [$login]))
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->record($login, $user, false, $ip, $userAgent);

            return null;
        }

        if (! $user->is_active) {
            $this->record($login, $user, false, $ip, $userAgent);

            throw ValidationException::withMessages(['login' => __('This account is inactive.')]);
        }

        $this->record($login, $user, true, $ip, $userAgent);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();

        return $user;
    }

    private function isLockedOut(string $login): bool
    {
        return LoginHistory::query()
            ->where('username_attempted', $login)
            ->where('succeeded', false)
            ->where('created_at', '>=', now()->subMinutes(self::LOCKOUT_MINUTES))
            ->count() >= self::LOCKOUT_ATTEMPTS;
    }

    private function record(string $login, ?User $user, bool $succeeded, ?string $ip, ?string $userAgent): void
    {
        LoginHistory::query()->create([
            'user_id' => $user?->id,
            'username_attempted' => Str::limit($login, 60, ''),
            'succeeded' => $succeeded,
            'ip_address' => $ip ?? '0.0.0.0',
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 252) : null,
        ]);
    }
}
```

- [x] **Step 5: Audit listener and wiring**

`app/Modules/Foundation/Listeners/RecordAuthenticationAudit.php`:

```php
<?php

namespace App\Modules\Foundation\Listeners;

use App\Models\User;
use App\Support\AuditTrail\AuditTrail;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordAuthenticationAudit
{
    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            AuditTrail::record($event->user, 'login', actor: $event->user);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            AuditTrail::record($event->user, 'logout', actor: $event->user);
        }
    }
}
```

`FoundationServiceProvider::boot()` add:

```php
        Event::listen(Login::class, [RecordAuthenticationAudit::class, 'handleLogin']);
        Event::listen(Logout::class, [RecordAuthenticationAudit::class, 'handleLogout']);
```

This needs the imports `Illuminate\Auth\Events\Login`, `Illuminate\Auth\Events\Logout`, `Illuminate\Support\Facades\Event` and `App\Modules\Foundation\Listeners\RecordAuthenticationAudit`.

`config/fortify.php`: `'username' => 'login',`. Leave `'email' => 'email'` as it is, for password reset.

`app/Providers/FortifyServiceProvider.php`, in `configureActions()`, add:

```php
        Fortify::authenticateUsing(fn (Request $request): ?User => app(AuthenticateUser::class)->handle(
            (string) $request->input('login'),
            (string) $request->input('password'),
            $request->ip(),
            $request->userAgent(),
        ));
```

This needs the imports `App\Models\User` and `App\Modules\Foundation\Actions\AuthenticateUser`; `Request` is already imported. The login rate limiter already keys on `Fortify::username()`, which is now `login`, so it needs no change.

- [x] **Step 6: Login view**

In `resources/views/pages/auth/login.blade.php`:
- Change the header description to `__('Enter your username or email and password below to log in')`.
- Replace the email field block with:

```blade
            <x-ui.field>
                <x-ui.field-label for="login">{{ __('Username or email') }}</x-ui.field-label>
                <x-ui.input id="login" name="login" type="text" :value="old('login')" required autofocus autocapitalize="none" autocomplete="username" :aria-invalid="$errors->has('login') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('login')" />
            </x-ui.field>
```

- Delete the `@if (Route::has('register')) … @endif` block.

- [x] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Auth`
Expected: PASS. If the two-factor starter test is skipped, that's expected because the feature is disabled.

- [x] **Step 8: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
for f in database/migrations/foundation/*login_histories*; do git add "$f"; done && git commit -m "Create login_histories table"
git add app/Modules/Foundation/Models/LoginHistory.php && git commit -m "Add LoginHistory model"
git add app/Modules/Foundation/Actions/AuthenticateUser.php && git commit -m "Authenticate by username or email with lockout"
git add app/Modules/Foundation/Listeners/RecordAuthenticationAudit.php && git commit -m "Audit login and logout"
git add app/Modules/Foundation/FoundationServiceProvider.php && git commit -m "Listen for authentication events"
git add config/fortify.php && git commit -m "Use login field for Fortify authentication"
git add app/Providers/FortifyServiceProvider.php && git commit -m "Route Fortify login through AuthenticateUser"
git add resources/views/pages/auth/login.blade.php && git commit -m "Ask for username or email on login page"
git add tests/Feature/Auth/AuthenticationTest.php && git commit -m "Post login field in authentication tests"
git add tests/Feature/Auth/LoginSecurityTest.php && git commit -m "Test login history, lockout and audit"
```

---

### Task 12: Session guards and the forced password change page

**Files:**
- Create: `app/Http/Middleware/{EnsureUserIsActive,EnforceSessionTimeout,EnsurePasswordChanged}.php`
- Create: `app/Modules/Foundation/Actions/ChangePassword.php`
- Create: `resources/views/pages/auth/⚡change-password.blade.php`
- Modify: `bootstrap/app.php`, `routes/web.php`, `routes/settings.php`, `app/Modules/Foundation/FoundationServiceProvider.php`
- Test: `tests/Feature/Auth/SessionGuardsTest.php`, `tests/Feature/Auth/ChangePasswordTest.php`

**Interfaces:**
- Consumes: `Settings::get()` (Task 5).
- Produces:
  - An `app` middleware group made of `auth`, `EnsureUserIsActive`, `EnforceSessionTimeout` and `EnsurePasswordChanged`. Every authenticated app route from now on uses `->middleware('app')`.
  - The `password.change` route at `/password/change`.
  - `ChangePassword::handle(User $user, string $newPassword): void`.

- [x] **Step 1: Write the failing tests**

`tests/Feature/Auth/SessionGuardsTest.php`:

```php
<?php

use App\Models\User;

test('users who must change their password are sent to the change page', function () {
    $this->actingAs(User::factory()->mustChangePassword()->create());

    $this->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->get(route('password.change'))->assertOk();
});

test('users who must change their password can still log out', function () {
    $this->actingAs(User::factory()->mustChangePassword()->create());

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();
});

test('deactivated users are logged out on their next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->update(['is_active' => false]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('idle sessions expire after the configured timeout', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();

    $this->travel(121)->minutes();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('activity within the timeout keeps the session alive', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
    $this->travel(100)->minutes();
    $this->get(route('dashboard'))->assertOk();
    $this->travel(100)->minutes();
    $this->get(route('dashboard'))->assertOk();
});
```

`tests/Feature/Auth/ChangePasswordTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('changing the password clears the forced change flag', function () {
    $user = User::factory()->mustChangePassword()->create();
    $this->actingAs($user);

    Livewire::test('pages::auth.change-password')
        ->set('current_password', 'password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('new-password-123', $user->password))->toBeTrue();
});

test('the current password must be correct', function () {
    $this->actingAs(User::factory()->mustChangePassword()->create());

    Livewire::test('pages::auth.change-password')
        ->set('current_password', 'wrong')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('save')
        ->assertHasErrors('current_password');
});

test('the new password must meet the minimum length and differ from the current one', function () {
    $this->actingAs(User::factory()->mustChangePassword()->create());

    Livewire::test('pages::auth.change-password')
        ->set('current_password', 'password')
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('save')
        ->assertHasErrors('password');

    Livewire::test('pages::auth.change-password')
        ->set('current_password', 'password')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasErrors('password');
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Auth/SessionGuardsTest.php tests/Feature/Auth/ChangePasswordTest.php`
Expected: FAIL (`password.change` route not defined).

- [x] **Step 3: Middleware**

`app/Http/Middleware/EnsureUserIsActive.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => __('This account is inactive.')]);
        }

        return $next($request);
    }
}
```

`app/Http/Middleware/EnforceSessionTimeout.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Support\Facades\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * FD-BR-12: log out after general.session_timeout_minutes of inactivity.
 */
class EnforceSessionTimeout
{
    public const SESSION_KEY = 'last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return $next($request);
        }

        $timeoutSeconds = (int) Settings::get('general.session_timeout_minutes', 120) * 60;
        $lastActivity = $request->session()->get(self::SESSION_KEY);

        if (is_int($lastActivity) && now()->getTimestamp() - $lastActivity > $timeoutSeconds) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', __('Your session expired. Please log in again.'));
        }

        $request->session()->put(self::SESSION_KEY, now()->getTimestamp());

        return $next($request);
    }
}
```

`app/Http/Middleware/EnsurePasswordChanged.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('password.change', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
```

`bootstrap/app.php` — `withMiddleware`:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('app', [
            'auth',
            EnsureUserIsActive::class,
            EnforceSessionTimeout::class,
            EnsurePasswordChanged::class,
        ]);
    })
```

This needs imports for the three middleware classes. Note that a custom group does not include `web`. Routes in `routes/web.php` already get `web` from `withRouting`.

`FoundationServiceProvider::boot()` add:

```php
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class, EnforceSessionTimeout::class]);
```

This needs the imports `Livewire\Livewire` and the two middleware classes.

- [x] **Step 4: Action, page and routes**

`app/Modules/Foundation/Actions/ChangePassword.php`:

```php
<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;

class ChangePassword
{
    public function handle(User $user, string $newPassword): void
    {
        $user->forceFill([
            'password' => $newPassword,
            'must_change_password' => false,
        ])->save();
    }
}
```

`resources/views/pages/auth/⚡change-password.blade.php`:

```blade
<?php

use App\Modules\Foundation\Actions\ChangePassword;
use App\Support\Facades\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Change password')] #[Layout('layouts::auth')] class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Save the new password and continue to the app.
     */
    public function save(ChangePassword $changePassword): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min((int) Settings::get('general.password_min_length', 8))],
        ]);

        $changePassword->handle(Auth::user(), $this->password);

        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Choose a new password')" :description="__('You need to set a new password before you continue.')" />

    <form wire:submit="save" class="flex flex-col gap-6">
        <x-ui.field>
            <x-ui.field-label for="current_password">{{ __('Current password') }}</x-ui.field-label>
            <x-ui.input id="current_password" wire:model="current_password" type="password" required autocomplete="current-password" :aria-invalid="$errors->has('current_password') ? 'true' : null" />
            <x-ui.field-error :messages="$errors->get('current_password')" />
        </x-ui.field>

        <x-ui.field>
            <x-ui.field-label for="password">{{ __('New password') }}</x-ui.field-label>
            <x-ui.input id="password" wire:model="password" type="password" required autocomplete="new-password" :aria-invalid="$errors->has('password') ? 'true' : null" />
            <x-ui.field-error :messages="$errors->get('password')" />
        </x-ui.field>

        <x-ui.field>
            <x-ui.field-label for="password_confirmation">{{ __('Confirm new password') }}</x-ui.field-label>
            <x-ui.input id="password_confirmation" wire:model="password_confirmation" type="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.button type="submit" class="h-11 w-full" data-test="change-password-button">
            {{ __('Save password') }}
        </x-ui.button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <x-ui.button type="submit" variant="link">{{ __('Log out') }}</x-ui.button>
    </form>
</div>
```

`routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('app')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('password/change', 'pages::auth.change-password')->name('password.change');
});

require __DIR__.'/settings.php';
```

`routes/settings.php`: change both `Route::middleware(['auth'])` groups to `Route::middleware('app')`.

- [x] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Auth tests/Feature/Settings tests/Feature/DashboardTest.php`
Expected: PASS.

- [x] **Step 6: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/EnsureUserIsActive.php && git commit -m "Log out deactivated users"
git add app/Http/Middleware/EnforceSessionTimeout.php && git commit -m "Expire idle sessions from settings"
git add app/Http/Middleware/EnsurePasswordChanged.php && git commit -m "Force password change before using the app"
git add bootstrap/app.php && git commit -m "Add app middleware group with session guards"
git add app/Modules/Foundation/FoundationServiceProvider.php && git commit -m "Apply session guards to Livewire requests"
git add app/Modules/Foundation/Actions/ChangePassword.php && git commit -m "Add ChangePassword action"
git add "resources/views/pages/auth/⚡change-password.blade.php" && git commit -m "Add forced change password page"
git add routes/web.php && git commit -m "Guard app routes and add change password route"
git add routes/settings.php && git commit -m "Guard settings routes with app middleware"
git add tests/Feature/Auth/SessionGuardsTest.php && git commit -m "Test session guards"
git add tests/Feature/Auth/ChangePasswordTest.php && git commit -m "Test forced password change"
```

---

### Task 13: Navigation registry

**Files:**
- Create: `config/navigation.php`
- Create: `app/Modules/Foundation/Services/Navigation.php`
- Modify: `app/Modules/Foundation/FoundationServiceProvider.php`
- Test: `tests/Feature/Foundation/NavigationTest.php`

**Interfaces:**
- Consumes: `User::can()` through the Gate (Task 6).
- Produces:
  - `Navigation::for(User $user): list<array{key: string, label: string, icon: string, items: list<NavItem>}>`.
  - `Navigation::primaryMobile(User $user): list<NavItem>`, which returns at most 3 items.
  - `NavItem` is `array{label: string, route: string, icon: string, url: string, active: bool}`.
  - Config shape: `groups: list<array{key, label, icon, items: list<array{label, route, icon, permission?: ?string, mobile_primary?: bool}>}>`.

- [x] **Step 1: Write the failing test**

`tests/Feature/Foundation/NavigationTest.php`:

```php
<?php

use App\Models\User;
use App\Modules\Foundation\Services\Navigation;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('test/leads', fn () => 'ok')->name('crm.leads.index');
    Route::get('test/customers', fn () => 'ok')->name('crm.customers.index');
    Route::get('test/users', fn () => 'ok')->name('admin.users.index');
    Route::getRoutes()->refreshNameLookups();

    createPermissions('crm.leads.view_own', 'admin.users.view');

    $this->navigation = new Navigation([
        ['key' => 'crm', 'label' => 'CRM', 'icon' => 'users', 'items' => [
            ['label' => 'Leads', 'route' => 'crm.leads.index', 'icon' => 'target', 'permission' => 'crm.leads.view_own', 'mobile_primary' => true],
            ['label' => 'Customers', 'route' => 'crm.customers.index', 'icon' => 'building', 'permission' => 'crm.customers.view_own'],
            ['label' => 'Pipeline', 'route' => 'crm.pipeline.index', 'icon' => 'kanban', 'permission' => null],
        ]],
        ['key' => 'admin', 'label' => 'Admin', 'icon' => 'settings', 'items' => [
            ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'user-cog', 'permission' => 'admin.users.view'],
        ]],
    ]);
});

test('items the user cannot access, items without routes and empty groups are dropped', function () {
    $user = User::factory()->create();
    $user->syncDirectPermissions(['crm.leads.view_own']);

    $groups = $this->navigation->for($user);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['key'])->toBe('crm')
        ->and(collect($groups[0]['items'])->pluck('label')->all())->toBe(['Leads']);
});

test('primary mobile destinations come from permitted flagged items', function () {
    $user = User::factory()->create();
    $user->syncDirectPermissions(['crm.leads.view_own', 'admin.users.view']);

    expect(collect($this->navigation->primaryMobile($user))->pluck('label')->all())->toBe(['Leads']);
});

test('items are marked active for their route family', function () {
    $user = User::factory()->create();
    $user->syncDirectPermissions(['crm.leads.view_own']);

    $this->actingAs($user)->get('test/leads');

    expect($this->navigation->for($user)[0]['items'][0]['active'])->toBeTrue();
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Foundation/NavigationTest.php`
Expected: FAIL (class not found).

- [x] **Step 3: Implement**

`config/navigation.php`:

```php
<?php

/*
| Sidebar (desktop) and bottom-nav / More sheet (mobile) registry (docs/00 §7.1, §7.6).
| Items whose route does not exist yet are skipped, so modules add entries as they ship.
| Item: label, route, icon (lucide name), permission (null = any signed-in user), mobile_primary.
*/

return [
    'groups' => [
        ['key' => 'crm', 'label' => 'CRM', 'icon' => 'handshake', 'items' => []],
        ['key' => 'projects', 'label' => 'Projects', 'icon' => 'folder-kanban', 'items' => []],
        ['key' => 'estimation', 'label' => 'Estimation & Site', 'icon' => 'ruler', 'items' => []],
        ['key' => 'sales', 'label' => 'Sales', 'icon' => 'receipt', 'items' => []],
        ['key' => 'purchases', 'label' => 'Purchases', 'icon' => 'shopping-cart', 'items' => []],
        ['key' => 'accounting', 'label' => 'Accounting', 'icon' => 'landmark', 'items' => []],
        ['key' => 'hrm', 'label' => 'HRM', 'icon' => 'id-card', 'items' => []],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-column', 'items' => []],
        ['key' => 'admin', 'label' => 'Admin', 'icon' => 'shield', 'items' => []],
    ],
];
```

`app/Modules/Foundation/Services/Navigation.php`:

```php
<?php

namespace App\Modules\Foundation\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Filters the navigation registry for a user.
 *
 * @phpstan-type NavItem array{label: string, route: string, icon: string, url: string, active: bool}
 * @phpstan-type NavGroup array{key: string, label: string, icon: string, items: list<NavItem>}
 */
final class Navigation
{
    public const MOBILE_PRIMARY_LIMIT = 3;

    /**
     * @param  list<array{key: string, label: string, icon: string, items: list<array{label: string, route: string, icon: string, permission?: string|null, mobile_primary?: bool}>}>  $groups
     */
    public function __construct(private array $groups) {}

    /**
     * @return list<NavGroup>
     */
    public function for(User $user): array
    {
        $groups = [];

        foreach ($this->groups as $group) {
            $items = array_map(
                fn (array $item): array => $this->present($item),
                array_values(array_filter($group['items'], fn (array $item): bool => $this->isVisible($item, $user))),
            );

            if ($items !== []) {
                $groups[] = ['key' => $group['key'], 'label' => $group['label'], 'icon' => $group['icon'], 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @return list<NavItem>
     */
    public function primaryMobile(User $user): array
    {
        $items = [];

        foreach ($this->groups as $group) {
            foreach ($group['items'] as $item) {
                if (($item['mobile_primary'] ?? false) && $this->isVisible($item, $user)) {
                    $items[] = $this->present($item);
                }
            }
        }

        return array_slice($items, 0, self::MOBILE_PRIMARY_LIMIT);
    }

    /**
     * @param  array{label: string, route: string, icon: string, permission?: string|null, mobile_primary?: bool}  $item
     */
    private function isVisible(array $item, User $user): bool
    {
        if (! Route::has($item['route'])) {
            return false;
        }

        $permission = $item['permission'] ?? null;

        return $permission === null || $user->can($permission);
    }

    /**
     * @param  array{label: string, route: string, icon: string, permission?: string|null, mobile_primary?: bool}  $item
     * @return NavItem
     */
    private function present(array $item): array
    {
        return [
            'label' => $item['label'],
            'route' => $item['route'],
            'icon' => $item['icon'],
            'url' => route($item['route']),
            'active' => request()->routeIs(Str::beforeLast($item['route'], '.').'.*'),
        ];
    }
}
```

`FoundationServiceProvider::register()` add:

```php
        $this->app->singleton(Navigation::class, fn (): Navigation => new Navigation(config('navigation.groups', [])));
```

This needs the import `App\Modules\Foundation\Services\Navigation`.

- [x] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Foundation/NavigationTest.php`
Expected: PASS.

- [x] **Step 5: Pint and commit (per file)**

```bash
vendor/bin/pint --dirty --format agent
git add config/navigation.php && git commit -m "Add module navigation registry"
git add app/Modules/Foundation/Services/Navigation.php && git commit -m "Filter navigation by permission and route"
git add app/Modules/Foundation/FoundationServiceProvider.php && git commit -m "Bind navigation service"
git add tests/Feature/Foundation/NavigationTest.php && git commit -m "Test navigation filtering"
```

---

### Task 14: App shell (desktop sidebar plus native mobile shell)

**Files:**
- Modify: `resources/views/partials/head.blade.php`
- Modify: `resources/views/layouts/app.blade.php`, `resources/views/layouts/app/sidebar.blade.php`
- Create: `resources/views/components/shell/sidebar-nav.blade.php`, `resources/views/components/shell/mobile-top-bar.blade.php`, `resources/views/components/shell/mobile-bottom-nav.blade.php`
- Modify: `resources/views/components/desktop-user-menu.blade.php` (email may be null)
- Modify: `resources/js/app.js` (top progress on navigate)
- Create: `config/livewire.php` (published; disable built-in navigate progress bar)
- Test: `tests/Feature/Foundation/AppShellTest.php`

**Interfaces:**
- Consumes: `Navigation::for()` and `Navigation::primaryMobile()` (Task 13).
- Produces: the `<x-layouts::app>` layout, which accepts these props and slots:
  - `title`, and an optional `back` URL
  - `actions` (mobile top-bar icons, at most 2)
  - `breadcrumbs` (desktop only)
  - `quickCreate` (desktop `+`)

- [x] **Step 1: Write the failing test**

`tests/Feature/Foundation/AppShellTest.php`:

```php
<?php

use App\Models\User;

test('the dashboard renders the mobile shell with home, profile and more', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="mobile-bottom-nav"', false)
        ->assertSee('data-test="mobile-top-bar"', false)
        ->assertSee(__('Home'))
        ->assertSee(__('Profile'))
        ->assertSee(__('More'));
});

test('the viewport allows drawing under the safe areas', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertSee('viewport-fit=cover', false);
});

test('users without an email see their username in the menus', function () {
    $this->actingAs(User::factory()->create(['email' => null, 'username' => 'rahim.bd']));

    $this->get(route('dashboard'))->assertSee('rahim.bd');
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Foundation/AppShellTest.php`
Expected: FAIL.

- [x] **Step 3: Head, progress bar, Livewire config**

`resources/views/partials/head.blade.php`: change the viewport meta to

```html
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
```

Publish the Livewire config: `php artisan livewire:publish --config --no-interaction`. In `config/livewire.php`, set `'navigate' => ['show_progress_bar' => false, ...]`, keeping the other keys that are already there.

`resources/js/app.js` — append:

```js
// Drive the BlatUI top progress bar from wire:navigate transitions.
document.addEventListener("livewire:navigate", () => {
    window.dispatchEvent(new CustomEvent("top-progress:start"));
});
document.addEventListener("livewire:navigated", () => {
    window.dispatchEvent(new CustomEvent("top-progress:done"));
});
```

- [x] **Step 4: Shell components**

`resources/views/components/shell/sidebar-nav.blade.php`:

```blade
@php($groups = app(\App\Modules\Foundation\Services\Navigation::class)->for(auth()->user()))

<x-ui.sidebar-group>
    <x-ui.sidebar-group-content>
        <x-ui.sidebar-menu>
            <x-ui.sidebar-menu-item>
                <x-ui.sidebar-menu-button :href="route('dashboard')" :is-active="request()->routeIs('dashboard')" :tooltip="__('Home')" wire:navigate>
                    <x-lucide-house />
                    <span>{{ __('Home') }}</span>
                </x-ui.sidebar-menu-button>
            </x-ui.sidebar-menu-item>
        </x-ui.sidebar-menu>
    </x-ui.sidebar-group-content>
</x-ui.sidebar-group>

@foreach ($groups as $group)
    <x-ui.sidebar-group>
        <x-ui.sidebar-group-label>{{ __($group['label']) }}</x-ui.sidebar-group-label>
        <x-ui.sidebar-group-content>
            <x-ui.sidebar-menu>
                @foreach ($group['items'] as $item)
                    <x-ui.sidebar-menu-item>
                        <x-ui.sidebar-menu-button :href="$item['url']" :is-active="$item['active']" :tooltip="__($item['label'])" wire:navigate>
                            <x-dynamic-component :component="'lucide-'.$item['icon']" />
                            <span>{{ __($item['label']) }}</span>
                        </x-ui.sidebar-menu-button>
                    </x-ui.sidebar-menu-item>
                @endforeach
            </x-ui.sidebar-menu>
        </x-ui.sidebar-group-content>
    </x-ui.sidebar-group>
@endforeach
```

`resources/views/components/shell/mobile-top-bar.blade.php`:

```blade
@props(['title' => null, 'back' => null, 'actions' => null])

<header data-test="mobile-top-bar" class="fixed inset-x-0 top-0 z-40 border-b bg-background pt-[env(safe-area-inset-top)] md:hidden">
    <div class="flex h-14 items-center gap-1 px-1">
        @if ($back)
            <x-ui.button variant="ghost" size="icon" class="size-11" :href="$back" wire:navigate :aria-label="__('Back')">
                <x-lucide-arrow-left class="size-5" />
            </x-ui.button>
        @else
            <a href="{{ route('dashboard') }}" wire:navigate class="flex size-11 items-center justify-center rounded-md active:bg-accent" aria-label="{{ __('Home') }}">
                <span class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <x-app-logo-icon class="size-5 fill-current" />
                </span>
            </a>
        @endif

        <h1 class="min-w-0 flex-1 truncate px-1 text-base font-semibold">{{ $title }}</h1>

        @if ($actions)
            <div class="flex items-center gap-2">{{ $actions }}</div>
        @endif
    </div>
</header>
```

`resources/views/components/shell/mobile-bottom-nav.blade.php`:

```blade
@php
    $navigation = app(\App\Modules\Foundation\Services\Navigation::class);
    $user = auth()->user();
    $primary = $navigation->primaryMobile($user);
    $groups = $navigation->for($user);
@endphp

<div data-test="mobile-bottom-nav" class="fixed inset-x-0 bottom-0 z-40 border-t bg-background pb-[env(safe-area-inset-bottom)] md:hidden">
    <x-ui.bottom-navigation class="border-t-0">
        <x-ui.bottom-navigation-item icon="house" :label="__('Home')" :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate class="active:bg-accent" />

        @foreach ($primary as $item)
            <x-ui.bottom-navigation-item :icon="$item['icon']" :label="__($item['label'])" :href="$item['url']" :active="$item['active']" wire:navigate class="active:bg-accent" />
        @endforeach

        @if (count($primary) < \App\Modules\Foundation\Services\Navigation::MOBILE_PRIMARY_LIMIT)
            <x-ui.bottom-navigation-item icon="circle-user" :label="__('Profile')" :href="route('profile.edit')" :active="request()->routeIs('profile.*', 'security.*')" wire:navigate class="active:bg-accent" />
        @endif

        <x-ui.drawer class="flex flex-1">
            <x-ui.drawer-trigger class="flex flex-1">
                <x-ui.bottom-navigation-item icon="ellipsis" :label="__('More')" :href="null" class="active:bg-accent" />
            </x-ui.drawer-trigger>

            <x-ui.drawer-content>
                <x-ui.drawer-header>
                    <x-ui.drawer-title>{{ __('More') }}</x-ui.drawer-title>
                    <x-ui.drawer-description>{{ $user->name }} · {{ $user->email ?? $user->username }}</x-ui.drawer-description>
                </x-ui.drawer-header>

                <div class="flex flex-col gap-4 overflow-y-auto px-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                    @foreach ($groups as $group)
                        <x-ui.item-group>
                            <p class="px-1 text-sm font-medium text-muted-foreground">{{ __($group['label']) }}</p>
                            @foreach ($group['items'] as $item)
                                <x-ui.item size="sm" :href="$item['url']" wire:navigate class="min-h-11 active:bg-accent">
                                    <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-5" />
                                    <span class="flex-1 text-sm">{{ __($item['label']) }}</span>
                                    <x-lucide-chevron-right class="size-4 text-muted-foreground" />
                                </x-ui.item>
                            @endforeach
                        </x-ui.item-group>
                    @endforeach

                    <x-ui.item-group>
                        <x-ui.item size="sm" :href="route('profile.edit')" wire:navigate class="min-h-11 active:bg-accent">
                            <x-lucide-settings class="size-5" />
                            <span class="flex-1 text-sm">{{ __('Settings') }}</span>
                            <x-lucide-chevron-right class="size-4 text-muted-foreground" />
                        </x-ui.item>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ui.button type="submit" variant="ghost" class="h-11 w-full justify-start gap-4 px-4 text-sm font-normal">
                                <x-lucide-log-out class="size-5" />
                                {{ __('Log out') }}
                            </x-ui.button>
                        </form>
                    </x-ui.item-group>
                </div>
            </x-ui.drawer-content>
        </x-ui.drawer>
    </x-ui.bottom-navigation>
</div>
```

- [x] **Step 5: Layouts and user menu**

`resources/views/layouts/app.blade.php`:

```blade
<x-layouts::app.sidebar :title="$title ?? null" :back="$back ?? null">
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
    @isset($breadcrumbs)
        <x-slot:breadcrumbs>{{ $breadcrumbs }}</x-slot:breadcrumbs>
    @endisset
    @isset($quickCreate)
        <x-slot:quick-create>{{ $quickCreate }}</x-slot:quick-create>
    @endisset

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        {{ $slot }}
    </div>
</x-layouts::app.sidebar>
```

`resources/views/layouts/app/sidebar.blade.php`:

```blade
@props(['title' => null, 'back' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-background text-foreground antialiased">
        <x-ui.top-progress />

        <x-ui.sidebar-provider>
            <x-ui.sidebar collapsible="icon">
                <x-ui.sidebar-header>
                    <x-ui.sidebar-menu>
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button size="lg" :href="route('dashboard')" wire:navigate>
                                <x-app-logo />
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    </x-ui.sidebar-menu>
                </x-ui.sidebar-header>

                <x-ui.sidebar-content>
                    <x-shell.sidebar-nav />
                </x-ui.sidebar-content>

                <x-ui.sidebar-footer>
                    <x-desktop-user-menu />
                </x-ui.sidebar-footer>

                <x-ui.sidebar-rail />
            </x-ui.sidebar>

            <x-ui.sidebar-inset class="pt-[calc(3.5rem+env(safe-area-inset-top))] pb-[calc(4rem+env(safe-area-inset-bottom))] md:pt-0 md:pb-0">
                <header class="hidden h-14 shrink-0 items-center gap-2 border-b px-4 md:flex">
                    <x-ui.sidebar-trigger class="-ms-1" />
                    <x-ui.separator orientation="vertical" class="me-2 h-4!" />

                    @isset($breadcrumbs)
                        {{ $breadcrumbs }}
                    @else
                        <span class="text-sm font-medium">{{ $title }}</span>
                    @endisset

                    <div class="ms-auto flex items-center gap-1">
                        <x-ui.button variant="ghost" size="icon" disabled :aria-label="__('Search')">
                            <x-lucide-search />
                        </x-ui.button>
                        @isset($quickCreate)
                            {{ $quickCreate }}
                        @endisset
                        <x-ui.button variant="ghost" size="icon" disabled :aria-label="__('Notifications')">
                            <x-lucide-bell />
                        </x-ui.button>
                    </div>
                </header>

                <x-shell.mobile-top-bar :title="$title" :back="$back" :actions="$actions ?? null" />

                {{ $slot }}
            </x-ui.sidebar-inset>
        </x-ui.sidebar-provider>

        <x-shell.mobile-bottom-nav />

        @persist('toast')
            <x-ui.sonner />
        @endpersist
        <x-ui.sonner-flash />
    </body>
</html>
```

`resources/views/components/desktop-user-menu.blade.php`: replace both `{{ auth()->user()->email }}` occurrences with `{{ auth()->user()->email ?? auth()->user()->username }}`.

- [x] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Foundation/AppShellTest.php tests/Feature/DashboardTest.php tests/Feature/Settings`
Expected: PASS.

If a lucide icon name doesn't exist (for example `house` or `chart-column` in this version of `mallardduck/blade-lucide-icons`), the render throws. Check with `ls vendor/mallardduck/blade-lucide-icons/resources/svg | grep -E '^(house|chart-column|folder-kanban|handshake|id-card|landmark|ruler|ellipsis|circle-user)\.svg'` and swap in the closest existing name.

- [ ] **Step 7: Build and verify visually at 390×844 and desktop** (skipped: browser checks only when the user asks)

Run: `npm run build`. Then seed a user:

```bash
php artisan migrate:fresh --seed
```

Log in as `admin` using the local `INITIAL_ADMIN_PASSWORD`. Use the `run` skill (or the chrome-browser / built-in-browser skill) to open `https://soc-erp.test` at a **390×844** viewport. Check each item and take screenshots:
- The login page fits with no horizontal scroll.
- Login redirects to **Change password**. The form is single-column, buttons are at least 44px tall, and nothing is hidden under the notch area.
- After saving, the dashboard shows a fixed top bar with logo and title, and a fixed bottom nav with Home · Profile · More. There's no sidebar or hamburger button.
- **More** opens a bottom sheet with Settings and Log out. The rows are at least 44px tall.
- The top progress bar shows during `wire:navigate` to Profile.

Then check at **1280×800**: the sidebar shows Home, and the desktop header shows the title plus the search and bell icon buttons. There's no bottom nav.

Fix any issue before committing. Record a screenshot for each viewport in the task report.

- [x] **Step 8: Commit (per file)**

```bash
git add resources/views/partials/head.blade.php && git commit -m "Allow drawing under safe areas with viewport-fit=cover"
git add config/livewire.php && git commit -m "Publish Livewire config and hide built-in progress bar"
git add resources/js/app.js && git commit -m "Drive top progress bar from wire:navigate"
git add resources/views/components/shell/sidebar-nav.blade.php && git commit -m "Render sidebar from navigation registry"
git add resources/views/components/shell/mobile-top-bar.blade.php && git commit -m "Add mobile top app bar"
git add resources/views/components/shell/mobile-bottom-nav.blade.php && git commit -m "Add mobile bottom navigation with More sheet"
git add resources/views/layouts/app.blade.php && git commit -m "Forward shell slots through app layout"
git add resources/views/layouts/app/sidebar.blade.php && git commit -m "Build desktop and mobile app shell"
git add resources/views/components/desktop-user-menu.blade.php && git commit -m "Show username when user has no email"
git add tests/Feature/Foundation/AppShellTest.php && git commit -m "Test app shell rendering"
```

---

### Task 15: Final checks

- [x] **Step 1: Format and static analysis**

Run: `vendor/bin/pint --format agent`. Expected: no changes. If anything changes, commit it per file.
Run: `vendor/bin/phpstan analyse --memory-limit=1G`. Expected: no errors. Fix any reported errors, commit per file, and re-run.

- [x] **Step 2: Full SQLite suite**

Run: `php artisan test --compact`
Expected: everything passes. The MySQL concurrency test is skipped.

- [x] **Step 3: Fresh seed**

Run: `php artisan migrate:fresh --seed` on the dev database. Expected: it succeeds. Running `php artisan db:seed` again adds no duplicate rows.

- [x] **Step 4: Ask the user to run the full suite**

Ask the user to run `php artisan test --compact` themselves, and the MySQL group command from Task 10 Step 4.
