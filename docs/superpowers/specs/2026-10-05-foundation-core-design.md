# Foundation Core — Design

**Phase:** 1, sub-project 1 of 4 (Foundation core → Admin screens → Shared services → Catalog)
**Source specs:** `docs/00-index-and-conventions.md`, `docs/01-foundation-admin.md`
**Date:** 05 Oct 2026

## 1. Goal

Lay the base every module stands on: module folder structure, reworked users and login, custom roles and permissions with data scope, audit trail, settings, lookups registry, number sequences, company/branch/currency master data, and the desktop + mobile app shell. No admin CRUD screens yet — those are sub-project 2.

## 2. Decisions

| # | Decision |
|---|---|
| D1 | Public registration and email verification are disabled. Users are created by admins only; email is optional. Register / verify-email pages, routes and their tests are removed; `User` no longer implements `MustVerifyEmail`. Forgot-password stays (works for users with an email). |
| D2 | `User` stays at `App\Models\User` (Fortify, factories and starter tests rely on it). All other foundation code lives in `app/Modules/Foundation` and `app/Support`. |
| D3 | The base `users` migration is edited in place (nothing deployed). New foundation migrations live in `database/migrations/foundation/`, loaded via `loadMigrationsFrom`. Seeders live in `database/seeders/foundation/`. |
| D4 | Columns pointing at later-phase tables (`employee_id`, `manager_employee_id`) are nullable `BIGINT UNSIGNED` without a FK constraint; the constraint is added in the phase that creates the target table. |
| D5 | Each module defines its permissions and default role grants in a manifest file `app/Modules/<Module>/permissions.php`. Seeders read all manifests and sync idempotently (add new, remove stale). |

## 3. Data model

All per `docs/01` §3 unless noted.

- **`users`** (base migration rewritten): `name`, `username` (unique), `email` (nullable, unique), `phone` (nullable, normalised), `password`, `employee_id` (nullable, unique, no FK — D4), `branch_id` (nullable FK branches), `avatar_path`, `is_active` (default 1), `must_change_password` (default 1), `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `last_login_at`, `last_login_ip`, `remember_token`, timestamps, `created_by`, `updated_by`, `deleted_at`. `password_reset_tokens` and `sessions` unchanged. Because `branches` is created later, `branch_id` FK is added in the branches migration.
- **`roles`, `permissions`, `role_permissions`, `user_roles`, `user_permissions`** — exactly §3.1.1.
- **`login_histories`** — §3.2.
- **`audit_logs`** — §3.11.
- **`settings`** — §3.5, with `UNIQUE(group, key)`.
- **`currencies`** — [LOOKUP] + `symbol`, `decimal_places`, `is_base`.
- **`branches`** — [LOOKUP] + `address`, `phone`, `manager_employee_id` (D4), `is_head_office`.
- **`company_profile`** — §3.3, single row, `base_currency_id` FK currencies.
- **`number_sequences`** — §3.8, `UNIQUE(document_type, scope_key)`.
- **`number_sequence_formats`** — `id`, `document_type` (unique), `format`, `reset_policy` (`never` | `fiscal_year`), `scope_by` (nullable: `business_line` | `branch`), timestamps.

Not in this sub-project: locations, location_levels, attachments, document_types, notes, notification_preferences.

## 4. Shared services (`app/Support`)

### 4.1 Money
Thin wrapper over `brick/money` (BDT default):
- `Money::of(string|int|float $amount): BrickMoney` with `RoundingMode::HALF_UP` to 2 dp (CM-BR-07).
- `Money::format(BrickMoney|string $amount, bool $withSymbol = true): string` → `৳ 12,34,567.00` (BD/Indian grouping; negatives as `-৳ 1,000.00`).

### 4.2 FiscalYear
- `FiscalYear::for(CarbonInterface $date): FiscalYear` using `company_profile.fiscal_year_start_month` (default 7).
- Exposes `startYear`, `endYear`, `shortCode()` (`27` for FY 2026-27), `longCode()` (`2027`), `label()` (`2026-27`).

### 4.3 NumberSequenceService
`next(string $documentType, array $context = []): string`
1. Throws `LogicException` when `DB::transactionLevel() === 0`.
2. Loads the format row for the document type (throws if unknown).
3. Resolves `scope_key`: `fiscal_year` → `fy:{yy}` (from `$context['date']` or today); `scope_by=business_line` → `bl:{prefix}` from `$context['bl_prefix']`; `scope_by=branch` → `br:{code}`; combined with `|` when both apply; `''` otherwise.
4. `lockForUpdate()` the `number_sequences` row; create it (`next_number = 1`) if missing, handling the duplicate-key race by re-selecting.
5. Renders tokens `{seq:N}` (zero-padded), `{yy}`, `{yyyy}`, `{bl_prefix}`, `{branch}`; increments `next_number`; returns the number.

Default formats seeded from `docs/00` §5.

### 4.4 Audit trail
- `App\Support\AuditTrail\Auditable` trait registers `AuditObserver` and declares `auditExclude()` (defaults to the model's hidden attributes + timestamps).
- Observer writes `created` (new values), `updated` (dirty old/new only; skipped when nothing audited changed), `deleted`, `restored`.
- `AuditTrail::record(Model $model, string $event, ?array $old = null, ?array $new = null): AuditLog` for workflow events (`approved`, `posted`, `login`, …).
- Captures `user_id`, `url`, `ip_address`, `user_agent` from the current request when present.
- `auditable_type` stores the morph alias.
- `User`, `Role`, `Branch`, `Currency`, `CompanyProfile`, `Setting` are auditable. Role permission changes and user role/grant changes are recorded as `updated` on the role/user with `permissions` / `roles` keys in old/new values.

### 4.5 Lookups
- `config/lookups.php`: keyed by table → `label`, `module`, `model` (optional), `extra_fields`, `permission`. Seeded with `branches` and `currencies`.
- `Lookup::options(string $table, int|array|null $include = null): Collection<int, object>` → active rows ordered by `sort_order, name`, plus the `$include` id(s) even when inactive (CM-BR-03).
- `LookupRegistry` reads the config for the master-data screen (built in sub-project 2).

### 4.6 Settings
- `SettingsRepository` with `get(string $dotKey, mixed $default = null)`, `set(string $dotKey, mixed $value)`, `all()`; values cast by `type`.
- `Cache::rememberForever('settings')`, flushed on `set`.
- `Settings` facade. Seeded with the `docs/01` §3.5 keys.

### 4.7 Morph map
`Relation::enforceMorphMap()` in `AppServiceProvider` with `user`, `role`, `branch`, `currency`, `company`, `setting`. Each later module adds its own aliases.

## 5. Authentication

- **Login field:** "Username or email". `Fortify::authenticateUsing` matches `LOWER(username)` or `email`, verifies the password, rejects inactive users with a clear message.
- **Login history:** every attempt writes `login_histories` (`user_id` null for unknown usernames).
- **Rate limit:** the existing Fortify limiter stays at 5 / minute / IP+username (FD-AC-02).
- **Lockout (FD-BR-04):** once a failure brings the username to 10 failed attempts within 15 minutes, login is refused for 15 minutes from that failure. The message shows the minutes left. Counted from `login_histories`, using the same 60-character form of the login that is stored there.
- **On success:** stamp `last_login_at` / `last_login_ip`, record an `AuditTrail` `login` event, and record a `logout` event on logout.
- **Middleware** (added to the authenticated web group):
  - `EnsureUserIsActive`: logs out and redirects to login when `is_active = 0`.
  - `EnforceSessionTimeout`: logs out after `general.session_timeout_minutes` of inactivity, tracked in the session (FD-BR-12).
  - `EnsurePasswordChanged`: when `must_change_password = 1`, only the forced change-password page and logout are reachable.
- **Forced change-password page:** a Livewire page at `/password/change` showing current password, new password and confirm. Length comes from `general.password_min_length`. On save it clears the flag.

2FA enforcement for `require_2fa_roles` belongs to sub-project 2.

## 6. Permissions

- **`PermissionRegistrar`** (`app/Modules/Foundation/Services`):
  - `for(User $user): array<string>` returns permission names from the user's active roles plus direct grants.
  - Cached under `permissions.user.{id}` until `forget($user)`. `forgetRole($role)` clears the cache for every user holding that role.
- **`HasRoles` trait on `User`:**
  - `roles()` and `directPermissions()` relations.
  - `hasRole(string $code)`, `hasPermission(string $name)`, `assignRole()`, `syncRoles()`, `syncDirectPermissions()`. The mutators clear the cache and record an audit entry.
- **`Role::syncPermissions(array $names)`:** clears the cache for every user holding the role and records an audit entry.
- **Gate:** `Gate::before`:
  - returns `true` for users with the `super_admin` role
  - otherwise, for ability strings containing a dot, returns `true` if `hasPermission()` passes and `null` if not, so policies still run
- **Manifests:** `app/Modules/Foundation/permissions.php` returns:

  ```php
  [
      'module' => 'admin',
      'resources' => ['users' => ['view', 'create', ...], ...],
      'grants' => ['management' => ['admin.settings.view', ...], ...],
  ]
  ```

  Wildcards such as `admin.*` and `*.view` are allowed in `grants`. The foundation manifest also covers `attachments.*` and `notes.*`, which have a two-part name with an empty resource.
- **Seeders:**
  - `PermissionSeeder` globs every `app/Modules/*/permissions.php` and upserts by name, removing stale permissions.
  - `RoleSeeder` creates the 10 system roles.
  - `RolePermissionSeeder` syncs the grants from the manifests.
- **FD-BR-02:** the `EnsureNotLastSuperAdmin` Action throws a `ValidationException` when deactivating, deleting or removing `super_admin` from the last active super admin. Sub-project 2's screens call it.
- **`HasDataScope` trait:**
  - `scopeVisibleTo(Builder $query, User $user, string $resource)` checks `{resource}.view_all`, then `.view_team`, then `.view_own`.
  - With `view_all` there is no filter. With `view_team`, `whereIn` runs on the owner columns using `dataScopeTeamUserIds($user)`. With `view_own`, the owner columns must equal the user id (OR across columns). With none of them, `whereRaw('1 = 0')`.
  - Models implement `dataScopeOwnerColumns(): array` and `dataScopeTeamUserIds(User $user): array`.

## 7. App shell

- **`config/navigation.php`:** ordered groups with label, icon and items. Each item has a label, route, icon, permission and `mobile_primary` (bool).
- **`Navigation` service:** `for(User $user)` returns permitted groups and items, skipping items whose route does not exist and dropping empty groups. `primaryMobile(User $user)` returns at most 3 module destinations.
- **Desktop (`md`+):** the existing BlatUI sidebar renders the groups. The top bar has the sidebar trigger, an optional `breadcrumbs` slot, a search icon button (disabled placeholder), a quick-create `+` (rendered only when the `quickCreate` slot is given) and a notifications bell (placeholder).
- **Mobile (< `md`):**
  - The sidebar is hidden.
  - **Top app bar** (fixed, safe-area top padding): a back button when the page passes `back`, otherwise the app logo; the title; and an `actions` slot for at most 2 icon buttons.
  - **Bottom navigation** (fixed `x-ui.bottom-navigation`, safe-area bottom padding): Home, up to 3 `primaryMobile` items, then More. While no module routes exist, Profile fills one slot.
  - **More** opens an `x-ui.drawer` bottom sheet listing every permitted group and item, plus Profile and Log out.
  - Content gets bottom padding so it clears the nav.
- **`partials/head`:** viewport `width=device-width, initial-scale=1, viewport-fit=cover`.
- **Loading:** `x-ui.top-progress` for `wire:navigate`.
- **Verification:** the dashboard and change-password pages are checked at 390×844 and at desktop width.

## 8. Testing

Pest feature tests (SQLite in-memory as configured), at least one per rule:

| Area | Cases |
|---|---|
| Login | username login; email login; case-insensitive username; inactive refused; history rows on success/failure/unknown user; 5/min limiter; 10-in-15-min lockout; last-login stamping; login/logout audit rows; registration routes 404 |
| Middleware | forced password change redirect + clearing; idle timeout logout; deactivated user logged out |
| Permissions | role grant; direct grant; inactive role ignored; super_admin bypass; cache busted on role/grant/role-permission change; `can:` middleware + `@can`; last-super-admin guard |
| Data scope | view_all / view_team / view_own / none on a test-only model + table |
| Sequences | token rendering; global, FY and business-line scopes; FY rollover resets; outside-transaction error; unknown type error |
| Sequences (concurrency) | FD-AC-05: 50 parallel `next()` calls from separate processes against MySQL return unique numbers. In group `mysql`, skipped unless `DB_CONNECTION=mysql`. |
| Audit | created / updated (dirty only) / deleted rows; hidden fields excluded; morph alias stored; `AuditTrail::record` |
| Lookups | active-only, sorted; inactive included id |
| Settings | typed get; set flushes cache |
| Money / FiscalYear | BD grouping, negatives, half-up rounding; FY codes around the July boundary |
| Seeders | idempotent re-run; every manifest permission created; wildcard grants expanded |
| Navigation | items filtered by permission; missing routes skipped; empty groups dropped |

Starter tests for registration and email verification are removed (D1). Other starter tests are updated for the username field.

## 9. Out of scope

Admin CRUD screens, profile/2FA rework and 2FA enforcement, impersonation (sub-project 2). Locations, attachments, notes, notifications (sub-project 3). Catalog (sub-project 4). Global search and quick-create content (CRM onward).
