# SOC ERP v2 — 01 · Foundation & Administration

**Build phase:** 1 · **Depends on:** — · **Used by:** every module
**Replaces (legacy):** Settings › Create User, Team Entry, Area Entry, Department/Post Entry (moved to HRM), Type Entry, Company Profile; login

---

## 1. Purpose & Scope

Provides everything the other modules stand on:

- Authentication, user accounts, password policy, optional 2FA
- Roles, permissions, data scope
- Company profile and branches
- System settings (key/value)
- Generic master-data management screen for all lookup tables
- Locations (division → district → thana/upazila → area)
- Number sequences
- Attachments (polymorphic)
- Notes (polymorphic quick notes)
- Audit log and login history
- Notifications (in-app + email; SMS/WhatsApp gateway optional)

Out of scope: payroll, HR records (09), business lookups owned by modules (each module owns its lookups but they are edited through the generic Master Data screen).

---

## 2. Actors & Permissions

| Role (seeded) | Description |
|---|---|
| `super_admin` | Full access, bypasses all policies (Gate::before) |
| `management` | MD / directors: read everything, approve, settings |
| `sales_manager` | Sales team manager |
| `sales_executive` | Sales / marketing officer |
| `project_manager` | Project / operations manager |
| `engineer` | Designer, architect, site engineer, draftsman |
| `accountant` | Accounts officer |
| `finance_manager` | Approves and posts finance |
| `hr_admin` | HR & Admin |
| `viewer` | Read-only auditor |

Permissions (this module):

```text
admin.users.view | create | update | deactivate | reset_password | impersonate
admin.roles.view | create | update | delete
admin.settings.view | update
admin.company.view | update
admin.branches.view | create | update
admin.master_data.view | create | update | deactivate
admin.locations.view | create | update
admin.sequences.view | update
admin.audit.view | export
admin.login_history.view
attachments.upload | delete_own | delete_any
notes.create | delete_own | delete_any
```

Default role grants for every module's permissions are maintained in `database/seeders/RolePermissionSeeder.php` from the matrix in each module's §2.

---

## 3. Data Model

### 3.1 `users`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| name | VARCHAR(120) | no | |
| username | VARCHAR(60) | no | UNIQUE; login id (legacy `user_name`) |
| email | VARCHAR(150) | yes | UNIQUE when not null |
| phone | VARCHAR(30) | yes | normalised |
| password | VARCHAR(255) | no | bcrypt/argon |
| employee_id | BIGINT UNSIGNED | yes | FK employees, UNIQUE |
| branch_id | BIGINT UNSIGNED | yes | FK branches (default branch) |
| avatar_path | VARCHAR(255) | yes | |
| is_active | TINYINT(1) | no | default 1 |
| must_change_password | TINYINT(1) | no | default 1 for new users |
| two_factor_secret | TEXT | yes | encrypted |
| two_factor_recovery_codes | TEXT | yes | encrypted |
| two_factor_confirmed_at | TIMESTAMP | yes | |
| last_login_at | TIMESTAMP | yes | |
| last_login_ip | VARCHAR(45) | yes | |
| remember_token | VARCHAR(100) | yes | |
| [AUDIT] [SOFT] | | | |

Indexes: `username` UNIQUE, `email` UNIQUE, `employee_id` UNIQUE, `is_active`.

### 3.1.1 Roles & permissions (custom)

No third-party permission package; see 00 §6.1 for runtime behaviour.

**`roles`**

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| code | VARCHAR(40) | no | UNIQUE; e.g. `super_admin`, `accountant`; stable, used by code |
| name | VARCHAR(120) | no | display label |
| description | VARCHAR(255) | yes | |
| is_system | TINYINT(1) | no | default 0; seeded roles; cannot be deleted or have `code` changed |
| is_active | TINYINT(1) | no | default 1 |
| [AUDIT] | | | |

**`permissions`** (seeded only, never edited in UI)

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| name | VARCHAR(120) | no | UNIQUE; full ability string `{module}.{resource}.{action}` |
| module | VARCHAR(40) | no | e.g. `crm` — groups rows in the matrix |
| resource | VARCHAR(60) | no | e.g. `leads` — matrix row |
| action | VARCHAR(40) | no | e.g. `view_own` — matrix column |
| label | VARCHAR(150) | yes | |
| sort_order | SMALLINT | no | default 0 |

Index (module, resource).

**`role_permissions`** — `id, role_id FK roles ON DELETE CASCADE, permission_id FK permissions ON DELETE CASCADE, created_at`. UNIQUE (role_id, permission_id).

**`user_roles`** — `id, user_id FK users ON DELETE CASCADE, role_id FK roles, created_by, created_at`. UNIQUE (user_id, role_id).

**`user_permissions`** (direct extra grants) — `id, user_id FK users ON DELETE CASCADE, permission_id FK permissions ON DELETE CASCADE, created_by, created_at`. UNIQUE (user_id, permission_id).

Role and grant changes are audited (CM-BR-01) on the `role` / `user` auditable.

### 3.2 `login_histories`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT | no | |
| user_id | BIGINT | yes | null for failed unknown username |
| username_attempted | VARCHAR(60) | no | |
| succeeded | TINYINT(1) | no | |
| ip_address | VARCHAR(45) | no | |
| user_agent | VARCHAR(255) | yes | |
| created_at | TIMESTAMP | no | index |

### 3.3 `company_profile` (single row)

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| name | VARCHAR(150) | no | "SOC Consultant & Development Ltd" |
| short_name | VARCHAR(40) | yes | "SOC" |
| logo_path | VARCHAR(255) | yes | |
| address | TEXT | yes | |
| phone | VARCHAR(60) | yes | |
| email | VARCHAR(150) | yes | |
| website | VARCHAR(150) | yes | |
| tin | VARCHAR(30) | yes | |
| bin | VARCHAR(30) | yes | VAT BIN |
| trade_license_no | VARCHAR(60) | yes | |
| base_currency_id | BIGINT | no | FK currencies (BDT) |
| fiscal_year_start_month | TINYINT | no | default 7 (July) |
| print_footer | VARCHAR(255) | yes | |
| [AUDIT] | | | |

### 3.4 `branches` [LOOKUP] + columns

| Column | Type | Null | Notes |
|---|---|---|---|
| address | TEXT | yes | |
| phone | VARCHAR(60) | yes | |
| manager_employee_id | BIGINT | yes | FK employees |
| is_head_office | TINYINT(1) | no | |

Seed: `HO` Head Office (is_head_office = 1).

### 3.5 `settings`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT | no | |
| group | VARCHAR(40) | no | `general`, `crm`, `projects`, `sales`, `purchases`, `accounting`, `notifications` |
| key | VARCHAR(80) | no | UNIQUE(group,key) |
| value | JSON | yes | |
| type | VARCHAR(20) | no | `string`,`int`,`bool`,`decimal`,`json`,`fk:<table>` |
| label | VARCHAR(150) | no | |
| help | VARCHAR(255) | yes | |
| updated_by / updated_at | | | |

Cached (`Cache::rememberForever('settings')`, flushed on update).

Initial keys (each module adds its own — listed in their specs):

| group.key | Type | Default |
|---|---|---|
| general.date_format | string | `d-M-Y` |
| general.timezone | string | `Asia/Dhaka` |
| general.money_grouping | string | `bd` (12,34,567.00) |
| general.session_timeout_minutes | int | 120 |
| general.password_min_length | int | 8 |
| general.require_2fa_roles | json | `["finance_manager","super_admin"]` |
| notifications.email_enabled | bool | true |
| notifications.sms_enabled | bool | false |
| notifications.daily_digest_time | string | `09:00` |

### 3.6 `currencies` [LOOKUP] + columns

`symbol VARCHAR(5)`, `decimal_places TINYINT default 2`, `is_base TINYINT`. Seed: BDT (৳, base), USD.

### 3.7 `locations`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| parent_id | BIGINT | yes | FK locations |
| location_level_id | BIGINT | no | FK location_levels |
| name | VARCHAR(120) | no | |
| name_bn | VARCHAR(120) | yes | Bangla name |
| full_path | VARCHAR(500) | no | denormalised "Dhaka › Dhaka › Uttara › Uttar Khan"; rebuilt on save |
| is_active | TINYINT(1) | no | |

`location_levels` [LOOKUP]: `division`, `district`, `thana` (thana/upazila), `area` (area/mouza/sector).

Indexes: `parent_id`, `full_path` (prefix 191), UNIQUE(parent_id, name).

Seed: 8 divisions + 64 districts + Dhaka & Gazipur thanas; legacy 50 areas mapped in migration (11).

### 3.8 `number_sequences`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| document_type | VARCHAR(40) | no | e.g. `invoice`, `project` |
| scope_key | VARCHAR(60) | no | `''` global, `fy:27`, `bl:SOC-BD` |
| format | VARCHAR(80) | no | e.g. `INV-{yy}-{seq:5}` |
| next_number | INT UNSIGNED | no | default 1 |
| reset_policy | VARCHAR(20) | no | `never`, `fiscal_year` |
| UNIQUE(document_type, scope_key) | | | |

`number_sequence_formats` — default format per document_type (editable by admin).

Service:

```php
NumberSequenceService::next(string $documentType, array $context = []): string
// 1. resolve scope_key from reset_policy + context (fiscal year, business line)
// 2. SELECT ... FOR UPDATE on the row (create if missing)
// 3. render format tokens: {seq:N} {yy} {yyyy} {bl_prefix} {branch}
// 4. increment, return
// Must be called inside the caller's DB transaction.
```

### 3.9 `attachments`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| attachable_type | VARCHAR(40) | no | morph alias |
| attachable_id | BIGINT | no | |
| document_type_id | BIGINT | yes | FK document_types |
| title | VARCHAR(200) | yes | |
| disk | VARCHAR(20) | no | `private` |
| path | VARCHAR(500) | no | |
| original_name | VARCHAR(255) | no | |
| mime_type | VARCHAR(100) | no | |
| size_bytes | BIGINT | no | |
| version | SMALLINT | no | default 1 |
| replaces_attachment_id | BIGINT | yes | previous version |
| uploaded_by | BIGINT | no | FK users |
| [SOFT] | | | |

Index (attachable_type, attachable_id).

`document_types` [LOOKUP] + `allowed_mimes JSON`, `max_size_mb INT`. Seed: Drawing, Approval Letter, Deed / Agreement, Invoice, Bill, Money Receipt, Site Photo, Soil Report, ID Copy, Land Document, Estimate, Other.

### 3.10 `notes`

`id, notable_type, notable_id, body TEXT, is_pinned, created_by, created_at, updated_at, deleted_at`.

### 3.11 `audit_logs`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT | no | |
| user_id | BIGINT | yes | |
| event | VARCHAR(30) | no | created, updated, deleted, restored, status_changed, approved, posted, reversed, cancelled, exported, printed, login, logout |
| auditable_type | VARCHAR(40) | no | |
| auditable_id | BIGINT | no | |
| old_values | JSON | yes | |
| new_values | JSON | yes | |
| url | VARCHAR(500) | yes | |
| ip_address | VARCHAR(45) | yes | |
| user_agent | VARCHAR(255) | yes | |
| created_at | TIMESTAMP | no | |

Indexes: (auditable_type, auditable_id), (user_id, created_at), (event).
Retention: never purged for finance types; others archived after 3 years.

### 3.12 Notifications

Laravel `notifications` table (database channel) + `notification_preferences`:

`id, user_id, notification_key VARCHAR(80), channel (database/mail/sms), is_enabled`.

---

## 4. Seed Data

- Roles (§2) with permission matrices from every module.
- 1 `super_admin` user (password from `.env` `INITIAL_ADMIN_PASSWORD`, must change on first login).
- Company profile row, Head Office branch, BDT currency.
- Location seed (divisions, districts, Dhaka/Gazipur thanas).
- Document types, default number sequence formats (00 §5).

---

## 5. Screens

### 5.1 Login
Fields: Username or email*, Password*, Remember me. Rate limit 5 attempts / minute / IP+username; lockout message. If `must_change_password` → forced change screen. If role in `require_2fa_roles` and 2FA not set → forced setup.

### 5.2 Users — list
Columns: Name, Username, Email, Phone, Employee, Roles, Branch, Active, Last login.
Filters: role, branch, active. Actions: New, Edit, Deactivate/Activate, Reset password, Impersonate (super_admin only, audited).

### 5.3 Users — form
| Field | Rule |
|---|---|
| Employee | optional select (unlinked employees only); selecting fills name/email/phone |
| Name* | max 120 |
| Username* | 3–60, `alpha_dash`, unique |
| Email | email, unique |
| Phone | BD mobile format |
| Branch | select |
| Roles* | multi-select ≥ 1 |
| Extra permissions | optional multi-select (direct permissions) |
| Sales team | read-only here; managed in CRM › Sales Teams |
| Password* (create) | min length setting, confirmed |
| Active | toggle |

### 5.4 Roles & permissions
Role list (name, users count). Role form: name, description, permission matrix grid (rows = resources grouped by module; columns = actions; checkbox per cell; "select row/column"). `super_admin` role not editable.

### 5.5 Company profile
Single form with all §3.3 fields + logo upload (PNG/JPG ≤ 1 MB) + print preview.

### 5.6 Branches — list/form (standard lookup UI + extra fields).

### 5.7 Settings
Tabbed by `group`; each key rendered by `type` (text, number, toggle, select for fk). Save per tab.

### 5.8 Master data (generic)
Left: list of all registered lookup tables grouped by module (registry in `config/lookups.php`: table, label, module, extra fields, permission). Right: grid with Code, Name, Sort, Colour, Active, System + extra flag columns; inline add/edit; drag to reorder (`sort_order`). System rows: code and delete locked.

### 5.9 Locations
Tree view (expand division → district → thana → area), add child, rename, deactivate, search by name.

### 5.10 Number sequences
List: document type, scope, format, next number, reset policy. Edit format/next number (next number can only be increased; warning shown).

### 5.11 Audit log
Filters: user, model type, record id/number, event, date range. Columns: When, User, Event, Record, Changes (expandable diff old → new). Export Excel.

### 5.12 Login history
Filters: user, success, date range.

### 5.13 Profile (self)
Change name/phone/avatar, password, 2FA setup, notification preferences.

### 5.14 Shared components
- `<x-attachments :model>` — upload (drag-drop, multiple), list with type, version, uploader, date; download via signed URL; delete (own within 24 h or `delete_any`).
- `<x-notes :model>` — add/pin/delete notes.
- `<x-history :model>` — audit timeline.
- `<x-lookup-select table="lead_sources">` — active options, sorted.

---

## 6. Workflows

### 6.1 User lifecycle
```text
Created (must_change_password) → Active ⇄ Inactive
```
Deactivation: logs out all sessions (`sessions` table delete), keeps ownership of records. Triggered automatically by `EmployeeDeactivated`.

### 6.2 Password reset
Admin reset → generates temp password shown once (or emailed) → `must_change_password = 1`. Self-service "forgot password" by email if email set.

---

## 7. Business Rules

| ID | Rule |
|---|---|
| FD-BR-01 | Username unique case-insensitively; cannot be changed after first login (admin may with audit). |
| FD-BR-02 | At least one active `super_admin` must exist; last one cannot be deactivated or demoted. |
| FD-BR-03 | A user can link to at most one employee and vice-versa. |
| FD-BR-04 | Failed logins ≥ 10 within 15 min for a username lock it for 15 min. |
| FD-BR-05 | Lookup rows with `is_system = 1` cannot be deleted, deactivated or have code changed. |
| FD-BR-06 | Lookup rows referenced by any record cannot be deleted (deactivate instead). |
| FD-BR-07 | Number sequence `next_number` can only increase. |
| FD-BR-08 | Attachment max size = document type `max_size_mb` (default 20 MB); allowed: pdf, jpg, jpeg, png, webp, dwg, dxf, xlsx, docx, zip. |
| FD-BR-09 | Files are stored outside web root; served only through authorised signed routes that check the parent record policy. |
| FD-BR-10 | Impersonation is logged with both user ids and shows a persistent banner. |
| FD-BR-11 | Location `full_path` rebuilds for all descendants when a parent is renamed or moved. |
| FD-BR-12 | Session idle timeout = `general.session_timeout_minutes`. |

---

## 8. Integration

- Listens: `EmployeeDeactivated` → deactivate linked user.
- Provides: `NumberSequenceService`, `AuditTrail`, `Lookup::options($table)`, `Settings::get('group.key')`, attachments/notes components.

---

## 9. Notifications

| Key | Trigger | To | Channel |
|---|---|---|---|
| `user.created` | user created with email | new user | mail |
| `user.password_reset` | admin reset | user | mail |
| `security.login_new_ip` | login from new IP (finance roles) | user | mail |

---

## 10. Reports
Audit log export; login history export; users & roles listing.

---

## 11. Components (indicative)

```text
Livewire: Admin\Users\Index, Admin\Users\Form, Admin\Roles\Index, Admin\Roles\Form,
          Admin\Company, Admin\Settings, Admin\MasterData, Admin\Locations,
          Admin\Sequences, Admin\AuditLog, Admin\LoginHistory, Profile\Edit
Actions:  CreateUser, UpdateUser, DeactivateUser, ResetPassword, SaveRole,
          SaveLookup, SaveLocation
Services: NumberSequenceService, SettingsRepository, LookupRegistry, AttachmentService,
          PermissionRegistrar
Traits:   HasRoles (User), HasDataScope, Auditable
```

---

## 12. Acceptance Criteria

| ID | Criterion |
|---|---|
| FD-AC-01 | Admin creates a user with role `accountant`; the user logs in, is forced to change password, and sees only Accounting menus. |
| FD-AC-02 | Five wrong passwords → rate-limit message; login history shows failures. |
| FD-AC-03 | Deactivating the last super admin is refused with a clear message. |
| FD-AC-04 | Adding a new lead source in Master Data makes it immediately available in the Lead form; deactivating hides it from new leads but existing leads still show it. |
| FD-AC-05 | Two users creating invoices simultaneously never receive the same number (concurrency test with 50 parallel requests). |
| FD-AC-06 | Editing a customer's phone shows an audit entry with old and new phone. |
| FD-AC-07 | An attachment URL copied to a user without access to the parent record returns 403. |
| FD-AC-08 | Company logo and TIN/BIN appear on invoice print. |
| FD-AC-09 | Renaming "Uttara" location updates `full_path` of all child areas. |

---

## 13. Open Questions

1. Does SOC have more than one branch/office?
2. Is email available for all staff (needed for password reset and notifications)? If not, use SMS gateway — which provider?
3. Which roles must use 2FA?
