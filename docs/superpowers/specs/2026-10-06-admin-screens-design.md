# Admin Screens — Design

**Phase:** 1, sub-project 2 of 4 (Foundation core → **Admin screens** → Shared services → Catalog)
**Source specs:** `docs/00-index-and-conventions.md`, `docs/01-foundation-admin.md`
**Builds on:** `docs/superpowers/specs/2026-10-05-foundation-core-design.md`
**Date:** 06 Oct 2026
**Status:** Done, 2026-10-06. Plan: `docs/superpowers/plans/2026-10-06-admin-screens.md`.

## 1. Goal

Build the administration screens of doc 01 §5 on top of the foundation core: users, roles with the permission matrix, master data (including branches and currencies), the locations tree, company profile, settings, number sequences, audit log, login history, the self-service profile with 2FA and notification preferences, and impersonation. Every screen is permission-gated and meets the desktop standards (doc 00 §7.1–7.3) and the native mobile rules (doc 00 §7.6).

Scope change from the original split: the locations tree and notification preferences move into this sub-project. Sub-project 3 keeps attachments, notes and notification delivery.

## 2. Decisions

| # | Decision |
|---|---|
| D1 | **Lean lists.** Search (300 ms debounce), filters kept in the URL, sort, 25/50/100 per page on desktop; infinite scroll and a filter bottom sheet on mobile. Excel export only on Audit log, Login history and Users. No saved views, column chooser or PDF export yet. |
| D2 | **Branches and Currencies** are edited through the generic Master Data screen. Branches also has its own nav item that links to `admin/master-data/branches`, gated by `admin.branches.view`. `admin.branches.deactivate` is added to the manifest so every lookup has view/create/update/deactivate. |
| D3 | **Master data rows are edited in a sheet** (right side on desktop, bottom on mobile), not inline in the grid. Reorder uses `wire:sort` with a drag handle. Every registered lookup names its Eloquent `model` so changes are audited. `extra_fields` becomes typed: `['address' => ['type' => 'textarea', 'label' => 'Address'], ...]` with types `text`, `textarea`, `number`, `bool`. |
| D4 | **Deleting a lookup row** (FD-BR-06) is attempted inside a transaction under the FK constraints; an integrity violation (SQLSTATE 23000) becomes "In use — deactivate instead". System rows (FD-BR-05) cannot be deleted, deactivated or have `code` changed. Delete requires the table's `deactivate` permission. |
| D5 | **Single-flag columns.** Setting `is_head_office` on a branch or `is_base` on a currency clears the flag on all other rows in the same transaction. |
| D6 | **No employee or sales-team fields** on the user form; HRM (09) and CRM (03) add them. |
| D7 | **2FA is re-enabled.** Fortify `Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])` plus a challenge page. `general.require_2fa_roles` is seeded **empty** and its type changes to a new `roles` type (role checklist in Settings, stored as a JSON array of role codes). `EnsureTwoFactorEnabled` middleware forces setup when the user holds a listed role and has no confirmed 2FA. `User::twoFactorQrCodeUrl()` is overridden to label the QR with `username` (fixes the `Fortify::username()` = `login` bug). |
| D8 | **Impersonation.** Super admins only; cannot target themselves, another super admin or an inactive user. The impersonator id is stored in the session and the switch uses `Auth::login($target)`. While impersonating: the login listener skips last-login stamping, login history and the `login` audit; `EnsurePasswordChanged` and `EnsureTwoFactorEnabled` are skipped; admin user and role screens are blocked; a persistent banner offers "Return to <name>". Audit events `impersonation_started` / `impersonation_ended` are recorded on the target user with both ids in `new_values`. |
| D9 | **Deferred fixes from sub-project 1.** `PasswordValidationRules` reads `general.password_min_length` (forced change, forgot-password reset, admin create/edit, profile). `User` mutators lowercase `username` and `email`. |
| D10 | **No admin password reset and no session purge on deactivation.** Users who forget use forgot-password, or an admin sets a new password in the user form (which sets `must_change_password`). Deactivation only sets `is_active = 0`; `EnsureUserIsActive` logs the user out on the next request. `admin.users.reset_password` stays in the manifest, unused. |

## 3. Data model

New migrations in `database/migrations/foundation/`:

- **`location_levels`** — [LOOKUP]. Seeded: `division`, `district`, `thana` (Thana / Upazila), `area` (Area / Mouza / Sector), all `is_system = 1`, sort 1–4. Registered in `config/lookups.php`.
- **`locations`** — per doc 01 §3.7: [STD], `parent_id` (nullable FK locations, restrict), `location_level_id` (FK location_levels), `name`, `name_bn`, `full_path` (VARCHAR 500), `is_active`; indexes `parent_id`, `full_path` (prefix 191 on MySQL), UNIQUE(`parent_id`, `name`). Auditable, morph alias `location`.
- **`notification_preferences`** — `id`, `user_id` (FK users, cascade), `notification_key` VARCHAR(80), `channel` VARCHAR(20) (`database` | `mail` | `sms`), `is_enabled`, timestamps; UNIQUE(`user_id`, `notification_key`, `channel`).

Seeds (`database/seeders/Foundation/`):

- `LocationLevelSeeder`, `LocationSeeder`: 8 divisions, 64 districts, and the thanas/upazilas of Dhaka and Gazipur districts, from `database/seeders/Foundation/data/locations.php`. Idempotent (upsert by parent + name). **The place list must be reviewed by the user before go-live.**
- `SettingSeeder`: `general.require_2fa_roles` becomes type `roles`, default `[]`.
- Permission manifest: add `admin.branches.deactivate`; `hr_admin` already gets `admin.branches.*`.

Registries:

- `config/lookups.php`: every entry gains `model` and typed `extra_fields`; `location_levels` added (permission `admin.locations`).
- `config/notifications.php` (new): `keys` → `user.created`, `user.password_reset`, `security.login_new_ip`, each with `label` and allowed `channels`. Used by the profile preferences grid; delivery is sub-project 3.
- Morph map: add `location`, `location_level`.

## 4. Architecture

### 4.1 Placement
- Routes: `routes/modules/foundation.php`, required from `routes/web.php`. All under `app` middleware, prefix `admin/`, name prefix `admin.`, each gated with `can:admin.<resource>.view`. Profile routes are not under `admin/`.
- Livewire (class-based): `app/Modules/Foundation/Livewire/Admin/...` and `.../Livewire/Profile/...`; views in `resources/views/livewire/admin/...` and `resources/views/livewire/profile/...`.
- Actions (`app/Modules/Foundation/Actions/`): `CreateUser`, `UpdateUser`, `SetUserActive`, `SaveRole`, `DeleteRole`, `SaveLookup`, `DeleteLookup`, `ReorderLookup`, `SaveLocation`, `SetLocationActive`, `UpdateCompanyProfile`, `UpdateSettings`, `UpdateSequenceFormat`, `IncreaseSequenceNumber`, `StartImpersonation`, `StopImpersonation`, `SaveNotificationPreferences`. Each enforces its business rules and throws `ValidationException`; each writing Action runs in `DB::transaction()`.
- Components: `authorize()` → validate → call Action → toast. No business logic in components.

### 4.2 Shared list and form pieces (reused by later modules)
- **`App\Support\Listing\WithListing`** trait: `#[Url] search`, `#[Url] filters` (array), `sort`, `direction`, `perPage` (25/50/100), `limit` (mobile, +25 per `loadMore()`), `sortBy(string $key)`, resets page on search/filter/perPage change. The component implements `listingQuery(): Builder` and `searchColumns(): array`; the trait exposes `paginatedRows()` (desktop) and `limitedRows()` (mobile) from the same query, with only whitelisted sort keys.
- **`x-shell.list`** Blade component. Desktop: toolbar (search, `filters` slot in a popover, optional export button, optional create button) above `x-ui.server-table`. Mobile: search field under the top bar, filter icon opening an `x-ui.drawer` with the same `filters` slot, an `item` slot rendered as `x-ui.item` rows with chevron inside `x-ui.infinite-scroll`, and a FAB above the bottom nav for create.
- **`x-shell.form-page`**: desktop card with actions in the footer; mobile single column with a sticky bottom action bar padded by `env(safe-area-inset-bottom)`.
- **`x-shell.sheet`**: `x-ui.sheet` with `side="right"` on desktop and `side="bottom"` below `md`. Used for row actions, master-data rows, location edits and confirmations.

### 4.3 Exports
`App\Support\Exports\QueryExport`: generic Maatwebsite export (`FromQuery`, `WithHeadings`, `WithMapping`) built from a query and a column map (`heading => callable|attribute`). Each export records an `exported` audit event on the exported resource type via `AuditTrail::record`.

### 4.4 Navigation
Admin group in `config/navigation.php`: Users, Roles, Master data, Branches, Locations, Company, Settings, Number sequences, Audit log, Login history — each gated by its `.view` permission (Branches by `admin.branches.view`, Master data by `admin.master_data.view`). Profile stays in the desktop user menu and the mobile More sheet.

### 4.5 Middleware
- `EnsureTwoFactorEnabled` (added to the `app` group after `EnsurePasswordChanged`): redirects to `two-factor.setup` when required; allows that page, the Fortify 2FA endpoints and logout.
- `HandleImpersonation`: shares `impersonator` for the banner and returns 403 on `admin.users.*` and `admin.roles.*` routes while impersonating.
- `EnsurePasswordChanged` and `EnsureTwoFactorEnabled` pass through when the session has `impersonator_id`.

## 5. Screens

All screens follow doc 00 §7.6 on mobile: fixed top bar with back/title, bottom nav, list rows instead of tables, bottom sheets for overlays, sticky action bars on forms, 44 px tap targets, `wire:navigate` everywhere.

### 5.1 Users — `admin/users`, `admin/users/create`, `admin/users/{user:username}/edit`
- List columns: Name, Username, Email, Phone, Roles (badges), Branch, Active, Last login. Filters: role, branch, active. Excel export (`admin.users.view`).
- Mobile row: name, `@username`, role badges, active dot, chevron. Row actions (sheet on mobile, dropdown on desktop): Edit, Activate/Deactivate, Impersonate (super admin only).
- Form: Name* (≤120), Username* (3–60, `alpha_dash`, unique case-insensitively), Email (email, unique), Phone (`type=tel`, BD mobile `01[3-9]\d{8}`, `+880`/`880` prefixes normalised to `01…`), Branch (active branches), Roles* (≥1, active roles), Extra permissions (checklist grouped by module/resource), Password* on create / optional on edit (min length setting, confirmed; when set by an admin, `must_change_password = 1`), Active.
- Rules: changing the username of a user with `last_login_at` set shows a warning and is audited (FD-BR-01); FD-BR-02 via `EnsureNotLastSuperAdmin` on deactivate and on removing `super_admin`; only a super admin may grant or remove `super_admin`.

### 5.2 Roles — `admin/roles`, `admin/roles/create`, `admin/roles/{role:code}/edit`
- List: Name, Code, Users count, Active, System.
- Form: Name*, Code* (`snake_case`, unique; locked on system roles), Description, Active, permission matrix. Desktop matrix: rows = resources grouped by module, columns = the module's action union, blank cells where an action does not exist, row and column "all" toggles. Mobile: accordion per module, each resource listing its actions as switches.
- Rules: `super_admin` is read-only; system roles cannot be deleted; a role held by any user cannot be deleted. Saving calls `Role::syncPermissions()` (cache clear + audit).

### 5.3 Master data — `admin/master-data`, `admin/master-data/{table}`
- Desktop: registered tables grouped by module on the left, the selected table on the right. Mobile: list of tables; tapping one opens the table screen.
- Table view: Code, Name, Sort, Colour badge, Active, System, extra fields. Drag handle reorder (`ReorderLookup` rewrites `sort_order`). Add/edit in `x-shell.sheet`: code (locked for system rows), name, description, colour (Tailwind token select), active, extra fields by type.
- Permissions per table from the registry prefix: `{prefix}.view/create/update/deactivate`. Unknown table → 404.
- Rules: D4, D5, FD-BR-05, FD-BR-06.

### 5.4 Locations — `admin/locations`
- `x-ui.tree` of divisions; children load when a node expands. Search by name (≥2 chars) shows flat results with `full_path`.
- Node actions (sheet): Add child (level = parent's level + 1; areas cannot have children), Edit (name, `name_bn`), Activate/Deactivate. Top-level "Add division".
- `SaveLocation` rebuilds `full_path` (`›`-separated) for the node and all descendants when the name or parent changes (FD-BR-11, FD-AC-09). It supports a parent change for later use; the UI does not expose moving.
- Uniqueness: name unique under the same parent.

### 5.5 Company profile — `admin/company`
- One form with all doc 01 §3.3 fields, base currency select, fiscal year start month select, logo upload (PNG/JPG ≤ 1 MB, `public` disk, replaces the old file).
- Print preview card renders `x-print.letterhead` (logo, name, address, phone, email, website, TIN/BIN), the component later prints use (FD-AC-08).
- Without `admin.company.update` the form is read-only.

### 5.6 Settings — `admin/settings`
- Tabs per `group` (`x-ui.tabs` desktop, `x-ui.segmented-control` mobile); Save per tab.
- Rendering by type: `string` → input; `int` → number input; `decimal` → `inputmode="decimal"` input; `bool` → switch; `json` → textarea validated as JSON; `fk:<table>` → select from `Lookup::options`; `roles` → checklist of active roles. Help text under each field.
- `UpdateSettings` writes through `SettingsRepository::set` (cache flushed, audited).

### 5.7 Number sequences — `admin/sequences`
- One row per `number_sequence_formats` document type (format, reset policy, scope by), expandable to its `number_sequences` counters (scope key, next number, sample next value).
- Edit format: must contain `{seq:N}`; only `{seq:N}`, `{yy}`, `{yyyy}`, `{bl_prefix}`, `{branch}` allowed; live sample.
- Increase next number: confirm sheet with warning; `IncreaseSequenceNumber` locks the row and refuses values ≤ current (FD-BR-07).

### 5.8 Audit log — `admin/audit`
- Filters: user, record type (morph aliases), record id, event, date range. Rows: When, User, Event badge, Record (`alias #id`). Expanding a row shows a field table old → new (sheet on mobile).
- Excel export gated by `admin.audit.export`.

### 5.9 Login history — `admin/login-history`
- Filters: user, success, date range. Columns: When, Username attempted, User, Result badge, IP, User agent (shortened). Excel export.

### 5.10 Profile — `profile` (replaces `settings/profile` and `settings/security`; `settings/*` redirects to `profile`)
- Tabs desktop / segmented control mobile:
  - **Details:** name, phone, avatar (PNG/JPG ≤ 1 MB, `public` disk).
  - **Password:** current, new, confirm (min length setting).
  - **Two-factor:** enable (password confirmation) → QR + manual key → confirm with a code → recovery codes (show, regenerate); disable is refused while the user holds a role in `require_2fa_roles`.
  - **Notifications:** toggle grid of `config/notifications.php` keys × their channels; missing rows default to enabled.
- `two-factor/setup`: forced setup page reusing the Two-factor panel in a minimal layout; `two-factor-challenge` view for login.

### 5.11 Impersonation
- Start from a user row action (super admin, D8 rules) → redirect to dashboard as the target with a fixed banner (top on desktop, under the top bar on mobile): "Signed in as <name> — Return to <impersonator>". Stop restores the impersonator and returns to the users list.

## 6. Error handling
- Action rule failures throw `ValidationException`; components show field errors inline and row-action failures as destructive toasts.
- 403 from `can:` route middleware and from `authorize()` in every Livewire action; items the user lacks permission for are hidden in nav and action sheets.
- Lookup delete in use → "In use — deactivate instead" (D4).
- Last super admin → the existing `EnsureNotLastSuperAdmin` message (FD-AC-03).
- Sequence decrease → refused with a message; re-checked under `lockForUpdate`.

## 7. Testing

Pest feature tests under `tests/Feature/Foundation/Admin/` (in-memory SQLite), one file per screen or Action group.

| Area | Cases |
|---|---|
| Access | each admin route: 403 without the permission, 200 with it; nav shows only permitted items |
| Users | create/update validation (case-insensitive unique username, phone format and normalisation, ≥1 role); lowercasing; only super admin grants/removes super_admin; deactivating or demoting the last super admin refused (FD-AC-03); username change after first login audited; admin-set password sets `must_change_password`; export |
| FD-AC-01 | admin creates an `accountant` → that user is forced to change password → nav has no admin items |
| Roles | matrix save syncs permissions and clears cache; system role code locked; super_admin read-only; delete refused while held |
| Master data | CRUD via registry; system row locks (FD-BR-05); delete in use refused (FD-BR-06); reorder; single head office / base currency; per-table permission; unknown table 404 |
| Locations | add child with automatic level; area cannot have children; rename rebuilds descendants' `full_path` (FD-AC-09); unique name per parent; search |
| Company / Settings | update + audit; logo validation; typed settings saved and cache flushed; `roles` type; JSON validation |
| Sequences | format token validation; next number only increases (FD-BR-07) |
| Audit / Login history | filters; change rendering; export permission and `exported` audit row |
| Profile | details update; password uses the min-length setting; 2FA enable/confirm/disable; disable refused for required role; notification preferences saved |
| 2FA enforcement | required role without 2FA → redirected to setup; challenge on login; QR label uses username |
| Impersonation (FD-BR-10) | super admin only; refuses self, super admin, inactive target; audit rows with both ids; no login-history row; banner; stop restores; password/2FA middleware skipped; admin user/role routes 403 while impersonating |
| Seeders | levels and locations idempotent; `require_2fa_roles` empty with type `roles`; `admin.branches.deactivate` exists |

No browser tests. On completion, the screens are listed for the user to check at 390×844 and desktop width.

## 8. Out of scope
Admin password reset and session purge on deactivation (D10); user-created / password-reset emails and all notification delivery; attachments and notes (sub-project 3); employee and sales-team fields; saved views, column chooser and PDF exports; moving locations in the UI; Catalog (sub-project 4).

## 9. Implementation deviations
Where the build differs from the sections above, the build is authoritative.

- **§4.3:** the `exported` audit row is recorded on the acting user (event `exported`, with `export` and `rows` in `new_values`), not on the exported resource type. Export cells starting with `=`, `+`, `-`, `@`, tab or CR are prefixed with `'` as a formula-injection guard.
- **§5.1:** only a super admin may update or deactivate a super-admin account. Non-super admins may only grant direct permissions they hold themselves. Users cannot deactivate themselves.
- **§5.2:** the mobile permission matrix uses checkbox rows, because `x-ui.switch` cannot bind to arrays.
- **§5.3:** delete confirmation is `wire:confirm` inside the edit sheet. Master-data routes have no `can:` middleware; the per-table permission is enforced in `mount` and in each action.
- **§5.7:** number formats must keep the tokens their scope needs (`{yy}` or `{yyyy}` for `fiscal_year`, `{bl_prefix}`, `{branch}`) and contain exactly one `{seq:N}`.
- **§5.8:** audit entries open in a sheet on both desktop and mobile.
- **§5.10:** turning 2FA off and generating new recovery codes require the current password. Fortify's own disable route also refuses users whose role requires 2FA. The 2FA challenge is throttled.
- **§5.10:** turning 2FA on also requires the current password.
- **§5.11:** starting or stopping impersonation clears `auth.password_confirmed_at`. While impersonating, Fortify's 2FA management routes (all `two-factor.*` except the login challenge) and the password-confirmation routes return 403, and the profile hides the Password and Two-factor tabs. Logging out while impersonating is recorded as `impersonation_ended`, not `logout`.
- **§5.8:** audit rows written while impersonating also store `impersonator_id`; the audit log shows "via <impersonator>".
- **§5.1/§5.2:** a non-super admin cannot change their own roles or direct permissions, can only assign roles whose permissions they all hold, cannot edit an account with more effective permissions than their own, and can only add permissions they hold to a role.
- **§5.6:** `general.session_timeout_minutes` must be 5–1440 and `general.password_min_length` 8–128.
- **§5.7:** format and next-number changes are audited (`number_sequence_format`, `number_sequence`); issuing document numbers is not. The `{seq:N}` pad width is clamped to 1–9 when rendering.
- **Profile:** forms use the sticky mobile action bar, positioned above the bottom nav. The old `/settings/*` URLs redirect to `/profile`.
- **Permissions:** `admin.branches.deactivate` and `admin.locations.deactivate` were added.
