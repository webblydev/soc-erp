# HRM — Design

**Phase:** pulled forward ahead of Projects (docs/09 header: "core employee master in 1–3"). One sub-project covering docs/09 except payroll.
**Source specs:** `docs/00-index-and-conventions.md`, `docs/09-hrm.md`, `docs/01-foundation-admin.md` (users ↔ employees)
**Builds on:** `docs/superpowers/specs/2026-10-06-admin-screens-design.md`, `docs/superpowers/specs/2026-10-06-shared-services-design.md`, `docs/superpowers/specs/2026-10-06-crm-design.md`
**Date:** 06 Oct 2026
**Status:** Done, 2026-10-06, on branch `hrm`. Plan: `docs/superpowers/plans/2026-10-06-hrm.md`. Built without review stops at the user's request; corrections to follow.

## 1. Goal

Keep one employee master that Projects (04), Estimation (05), Accounting (08) and the user accounts (01) can point at: employees with personal, job and emergency details, departments and designations, documents with expiry, employment history, exit and rejoin, and the link between an employee and a user login. Every screen meets the desktop standards (doc 00 §7.1–7.3) and the native mobile rules (doc 00 §7.6).

Projects, tasks and advances do not exist yet. HRM builds everything that does not need them and leaves hooks (H7, H12).

## 2. Decisions

| # | Decision |
|---|---|
| H1 | **Scope** (user's choice): all of docs/09 except payroll (9b: salary components, structures, salary sheets). `gross_salary`, bank and wallet fields stay on the employee, restricted (H2). Education and experience rows are included. The optional birthday notification is left out. |
| H2 | **Access.** Permissions per docs/09 §2 (§4.1). Everyone with `hrm.employees.view_basic` sees the directory fields: photo, code, name, department, designation, type, status, branch, manager, phone, official email, joining date. `view_full` adds personal fields (parents, gender, date of birth, marital status, blood group, NID, personal email, addresses, emergency contact, reference, TIN, notes, education and experience). `view_salary` adds gross salary, bank name and account, mobile wallet, and the salary columns of employment events. An employee always sees their own full profile and own salary (via the linked user), read-only. There is no row-level data scope: the directory lists everyone. The PM "own team" rule needs project teams, so 04 adds it; until then PMs see the basic view (HR-AC-04 holds). Documents need `hrm.documents.view` **and** full visibility of that employee, because the viewer role's `*.view` grant would otherwise expose NID scans. |
| H3 | **Employee code** (HR-BR-01): optional on create. Blank → next `employee` sequence (`EMP-{seq:4}`, already seeded). A typed code is upper-cased, `^[A-Z0-9-]+$`, max 40, unique including soft-deleted rows. The code cannot change after create. Routes use `{employee:employee_code}`. |
| H4 | **Names and phones.** `full_name` is stored as `trim(first_name + ' ' + last_name)` on every save. `phone` (required) and `emergency_contact_phone` go through `App\Support\Phone` (HR-BR-02, CRM R3 rules). NID is unique among employees when present. |
| H5 | **Employment events** (HR-BR-05, HR-AC-02). `RecordEmploymentEvent` is the only way to change `department_id`, `designation_id` or `gross_salary` on an existing employee: it writes the event with from/to values and applies the new values in one transaction. The employee form keeps these fields; when any of them changed on save, the form asks for an event (type, effective date, note) in a dialog / bottom sheet and passes it to `UpdateEmployee`, which refuses the change without one. Changing salary needs `update_salary`. Creating an employee writes a `JOINED` event at the joining date. From the profile, users with `hrm.history.manage` can record a standalone event; a `CONFIRMED` event also sets `confirmation_date` to its effective date. Effective date must be on or after the joining date. Events are not edited or deleted (audit trail); a wrong one is corrected with a new event. `approved_by` is the acting user. |
| H6 | **Statuses.** `employee_statuses` get `is_active_employment` (ACTIVE, ON_LEAVE true; SUSPENDED, RESIGNED, TERMINATED, RETIRED false) and a new `is_exit` flag (RESIGNED, TERMINATED, RETIRED). Both flags are seeder-set on system rows and not editable on Master Data (like CRM R4); statuses added later are non-exit, active-employment statuses. The form's status select hides exit statuses: only the exit wizard sets them (H7). `Employee::scopeAssignable()` = status `is_active_employment` (HR-BR-06), ready for 04 / 08. `employment_event_types` gains `RETIRED` so every exit status has an event type. |
| H7 | **Exit and rejoin** (§4.4, HR-AC-03). The exit wizard asks for exit status* (exit statuses), exit date* (≥ joining date, HR-BR-04), reason* and note, then lists the results of the registered **exit checks**. `App\Modules\Hrm\Services\ExitChecks` collects classes implementing `EmployeeExitCheck` (each returns items with a label, a link and `blocking` true/false). HRM ships one check: the linked user, which will be deactivated (not blocking). 04 adds open tasks and PM roles, 08 adds open advances with the finance_manager override (HR-BR-07). Blocking items stop the exit. `ExitEmployee` sets status, `exit_date`, `exit_reason_id`, writes the RESIGNED / TERMINATED / RETIRED event, fires `EmployeeDeactivated` and sends `hrm.exit_checklist` to users holding `hrm.employees.deactivate` (other than the actor). A Foundation listener deactivates the linked user through `SetUserActive` (docs/01 §5.3, sessions logged out). **Rejoin** (`hrm.employees.deactivate`): effective date* and note → status ACTIVE, exit fields cleared, `REJOINED` event. The linked user is not reactivated automatically. |
| H8 | **Users ↔ employees** (FD-BR-03, user's choice to rewire). `users.employee_id` gets an FK to `employees` (RESTRICT). The user form gets an **Employee** select listing employees with no linked user (plus the current one); choosing one fills empty name, email (official email) and phone fields. `CreateUser` / `UpdateUser` validate the employee exists and is not linked to another user. The employee profile shows the linked user, and for users with `admin.users.update` a **Link user** sheet (existing users with no employee) and **Unlink**; with `admin.users.create`, a **Create user** button opens `admin/users/create?employee={code}` prefilled. `LinkUser` / `UnlinkUser` actions in Hrm. After creating an employee, the profile shows a "No login yet" prompt with those buttons (the `EmployeeCreated` "user creation prompt"). |
| H9 | **Managers** (HR-BR-03). `manager_id` → employees, not self, no cycle (walk up the chain). The manager select lists assignable employees plus the current value. |
| H10 | **Masters on Master Data** (§4.5). `config/lookups.php` gets the ten HR tables under `module => 'hrm'` and `permission => 'hrm.masters'`. docs/09 names one `hrm.masters.manage` permission; Master Data needs view / create / update / deactivate, so `hrm.masters` gets those four (as CRM did with `crm.master_data`). Extra fields: `departments.parent_id` (lookup, self) and `head_employee_id` (employee), `designations.grade` (text) and `department_id` (lookup), `employee_document_types.has_expiry` (bool). A new Master Data field type **`employee`** renders `x-employee-select` and validates an existing, assignable employee (or the value the row already holds). A new entry flag **`tree`** (`'tree' => 'parent_id'`) stops a row being its own parent or ancestor. |
| H11 | **Rewired manager columns** (user's choice). `branches.manager_employee_id` and `business_lines.manager_employee_id` get FKs to `employees` (RESTRICT) and an `employee` field on Master Data ("Manager"). |
| H12 | **CRM employee referrers** (user's choice). `referrer_type = employee` now stores an **employee** id. A data migration copies the user's name into `referrer_name` (when empty) and clears `referrer_id` on existing employee-referrer leads, because no employee rows exist to map to. The lead form's employee picker lists assignable employees; `ValidatesLeadInput` checks `employees`. The lead page links the referrer to the employee profile when the viewer has `view_basic`. |
| H13 | **Documents** (§3.3, §4.6, HR-AC-05). An employee document has a type, number, issue / expiry dates, notes and one file. The file is an `Attachment` on the employee (via `UploadAttachment`, title = type name) and `employee_documents.attachment_id` points at it (`nullOnDelete`). Expiry date is required when the type `has_expiry`. Status: expired (expiry < today), expiring (≤ 30 days), valid. Managed from the profile's Documents tab with `hrm.documents.manage`. The expiry screen lists active-employment employees' documents expiring in 30 / 60 / 90 days or expired. |
| H14 | **Jobs** (daily, `routes/console.php`). `NotifyExpiringDocuments`: documents of active-employment employees with expiry within 30 days and `expiry_notified_at` null → `hrm.document_expiring` to users with `hrm.documents.manage` and the employee's linked user; sets `expiry_notified_at` (cleared when the expiry date changes). `NotifyProbationEnding`: active employees of type PROBATION whose `confirmation_date` is within 15 days and `probation_notified_at` null → `hrm.probation_ending` to users with `hrm.employees.update` and the manager's linked user; sets `probation_notified_at` (cleared when `confirmation_date` changes). |
| H15 | **Notifications** (`PreferenceNotification`, `config/notifications.php`): `hrm.document_expiring` (database, mail), `hrm.probation_ending` (database, mail), `hrm.exit_checklist` (database). |
| H16 | **Photo** stored on the `public` disk under `employee-photos/` (png/jpg, max 1 MB), like user avatars; the old file is deleted after a successful replace. |
| H17 | **Events.** `EmployeeCreated(employee)` and `EmployeeDeactivated(employee)` in `app/Modules/Hrm/Events`. Listener `DeactivateLinkedUser` lives in Foundation (docs/01 §8). |
| H18 | **Deferred.** Payroll (9b) and `hrm.salary.*` permissions, birthday notification, PM own-team visibility, profile tabs Projects / Tasks (04) and Advances (08) and their exit checks, reports (doc 10), the legacy import and HR-AC-01 (doc 11), docs/09 open questions 1–3 (seeded designations follow §3.1 and can be edited). FULLTEXT on `full_name` is MySQL only; search uses `LIKE`. |

## 3. Data model

Migrations in `database/migrations/hrm/` (loaded by `HrmServiceProvider`), models in `app/Modules/Hrm/Models`. All models are Auditable. FKs are `ON DELETE RESTRICT` except where noted. Morph aliases: `department`, `designation`, `employee_type`, `employee_status`, `gender`, `marital_status`, `blood_group`, `employee_document_type`, `employment_event_type`, `exit_reason`, `employee`, `employee_document`, `employment_event`, `employee_education`, `employee_experience`.

- **Lookups** ([LOOKUP]): the ten tables of docs/09 §3.1 with extras: `departments.parent_id` (FK departments, nullable) and `head_employee_id` (FK employees, nullable, added after `employees`); `designations.grade` VARCHAR(20) nullable and `department_id` (FK departments, nullable); `employee_statuses.is_active_employment` and `is_exit` (bool); `employee_document_types.has_expiry` (bool).
- **`employees`**: per docs/09 §3.2, plus `probation_notified_at` TIMESTAMP nullable (H14). `employee_code` unique, `nid_number` unique (nullable). Indexes per §3.2 (FULLTEXT MySQL only). `manager_id` FK employees. `[AUDIT] [SOFT]`.
- **`employee_documents`**: per §3.3, plus `expiry_notified_at` TIMESTAMP nullable. `employee_id` cascade on delete; `attachment_id` FK attachments `nullOnDelete`.
- **`employment_events`**: per §3.4. `from_salary` / `to_salary` DECIMAL(18,2) nullable; `approved_by` FK users nullable. Index (`employee_id`, `effective_date`).
- **`employee_education`**: `[STD]`, `employee_id` (cascade), `institution` VARCHAR(200), `degree` VARCHAR(150), `from_year` / `to_year` SMALLINT nullable, `result` VARCHAR(60) nullable.
- **`employee_experience`**: `[STD]`, `employee_id` (cascade), `company` VARCHAR(200), `position` VARCHAR(150), `from_date` / `to_date` DATE nullable, `notes` VARCHAR(255) nullable.
- **Foreign keys added to existing columns** (one migration in `database/migrations/hrm/`): `users.employee_id`, `branches.manager_employee_id`, `business_lines.manager_employee_id` → employees.
- **CRM referrer data migration** (H12) in `database/migrations/hrm/`.

`Employee` implements `Collaborative` and uses `HasAttachments` / `HasNotes`; `isViewableBy` = `can('view', $employee)` (basic). Relations: department, designation, type, status, branch, manager, reports (direct reports), user (hasOne via `users.employee_id`), documents, events, education, experience, gender, maritalStatus, bloodGroup, exitReason.

### 3.1 Seeds (`database/seeders/Hrm/`, idempotent upsert by `code`)

- All ten lookups with the rows of docs/09 §3.1. Departments use the legacy codes (DESIGN … LOGISTIC). Designations get codes derived from the names (`MD`, `DIRECTOR`, `GM`, `MANAGER`, `ARCHITECT`, `STRUCTURAL_ENGINEER`, `PROJECT_ENGINEER`, `SITE_ENGINEER`, `DRAFTSMAN`, `SURVEYOR`, `ACCOUNTS_OFFICER`, `HR_ADMIN_OFFICER`, `MARKETING_OFFICER`, `CR_OFFICER`, `OFFICE_ASSISTANT`). Employee statuses get their flags (H6) and are system rows; ACTIVE is the default. Employment event types are all system rows, plus `RETIRED`. Document types: PASSPORT, IEB_IAB, DRIVING_LICENCE have expiry. Blood groups use codes `A_POS`, `A_NEG`, … with names `A+`, `A−`, ….
- Permissions and grants (§4.1).
- No employees. Factories exist for tests and the UAT demo seeder.
- Number sequence `employee` already exists.

## 4. Architecture

### 4.1 Permissions (`app/Modules/Hrm/permissions.php`)

```text
hrm.employees.view_basic | view_full | create | update | deactivate | export | view_salary | update_salary
hrm.documents.view | manage
hrm.history.manage
hrm.masters.view | create | update | deactivate
```

| Role | Grants |
|---|---|
| super_admin | everything (Gate::before) |
| management, hr_admin | `hrm.*` |
| finance_manager, accountant | `employees.view_basic`, `employees.view_salary` |
| project_manager, engineer, sales_manager, sales_executive | `employees.view_basic` |
| viewer | already `*.view` / `*.view_all` (Foundation); add `employees.view_basic` |

`view_basic` is the gate for the HRM nav, directory and profile. The employee's own linked user is treated as `view_full` + `view_salary` for that one employee (H2) via `EmployeePolicy::viewFull` / `viewSalary`.

### 4.2 Placement

- `app/Modules/Hrm/` with `HrmServiceProvider` (migrations, morph map, Livewire location, policy, exit check registry), registered in `bootstrap/providers.php`.
- Routes `routes/modules/hrm.php`, prefix `hrm/`, name prefix `hrm.`, `app` middleware, `can:` per route:
  - `hrm/employees` (directory), `hrm/employees/create`, `hrm/employees/{employee:employee_code}`, `.../edit`, `.../exit`
  - `hrm/org-chart`, `hrm/documents/expiring`
- Livewire (class-based), views in `resources/views/livewire/hrm/...`: `Employees\Index`, `Employees\Form`, `Employees\Show`, `Employees\Exit`, `Employees\Documents` (profile tab, sheet form), `Employees\RecordEvent` (sheet, also used by the form), `Employees\LinkUser` (sheet), `OrgChart`, `Documents\Expiring`.
- Actions (`app/Modules/Hrm/Actions`): `CreateEmployee`, `UpdateEmployee`, `RecordEmploymentEvent`, `ExitEmployee`, `RejoinEmployee`, `SaveEmployeeDocument`, `DeleteEmployeeDocument`, `LinkUser`, `UnlinkUser`. Each authorizes against the actor, validates (throws `ValidationException`) and writes in `DB::transaction()`. Shared validation in `Concerns\ValidatesEmployeeInput`.
- Services: `ExitChecks` + `Contracts\EmployeeExitCheck` + `ExitChecks\LinkedUserCheck` (H7); `EmployeeFields` (which fields are basic / full / salary, used by forms, profile and export, H2).
- Jobs: `NotifyExpiringDocuments`, `NotifyProbationEnding`.
- Events: `EmployeeCreated`, `EmployeeDeactivated`. Foundation listener `DeactivateLinkedUser`.
- Notifications: `DocumentExpiring`, `ProbationEnding`, `ExitChecklist`.
- Blade component `x-employee-select` (searchable `x-ui.combobox` / native select on mobile) used by Hrm, Master Data and CRM.

### 4.3 Master Data

`config/lookups.php` gains the ten HRM tables (H10) and the `employee` field on branches and business lines (H11). `LookupRegistry`'s field type list gains `employee`; `SaveLookup` validates it; the Master Data view renders it; `tree` entry flag added to `SaveLookup`.

### 4.4 Navigation

The existing empty **HRM** group gets:

- Employees → `hrm.employees.index` (permission `hrm.employees.view_basic`)
- Org chart → `hrm.org-chart`
- Expiring documents → `hrm.documents.expiring` (permission `hrm.documents.manage`)
- HR setup (tree): Departments, Designations, Employee types, Employee statuses, Genders, Marital statuses, Blood groups, Document types, Employment event types, Exit reasons → master data, gated by `hrm.masters.view`

Employees is not a mobile primary item: the bottom bar is full with CRM (limit 3).

## 5. Screens

All screens follow doc 00 §7.6 on mobile: fixed top bar with back and title, list rows instead of tables, bottom sheets for filters, row actions and pickers, sticky action bars on forms, a FAB for create, 44 px tap targets, `wire:navigate` everywhere.

### 5.1 Employee directory — `hrm/employees`

- Desktop list: photo, code, name, designation, department, phone, official email, status badge. Toggle to a card grid. Filters: department, designation, type, status (default "active employment"; "All" and each status available), branch, manager. Search: code, name, phone, official email. Export (`hrm.employees.export`) of the filtered list; columns follow the user's field visibility (H2).
- Mobile: rows with photo, name, designation · department, status badge, chevron; infinite scroll and pull-to-refresh; filters in a bottom sheet; FAB → new employee (with `create`).

### 5.2 Employee form — `hrm/employees/create`, `hrm/employees/{employee}/edit`

Full-page form (`x-shell.form-page`) with sections, which become a segmented control on mobile: **Personal** (first*, last, father, mother, gender, date of birth, marital status, blood group, NID, photo) · **Contact & emergency** (phone* `tel`, personal email, official email, present / permanent address with "Same as present", emergency name, relation, phone) · **Job** (code — create only, blank = auto; department*, designation*, type*, status* (non-exit), branch, manager, joining date*, confirmation date, reference) · **Bank & salary** (only with `view_salary`, editable with `update_salary`: gross salary `inputmode=decimal`, bank name, account no., mobile wallet, TIN) · **Education & experience** (repeaters). Personal and education sections need `view_full` to show (the form needs `create` / `update`, which management and hr_admin pair with `view_full`). Saving an existing employee with a changed department, designation or salary opens the event sheet (H5). Sticky Save bar on mobile.

### 5.3 Employee profile — `hrm/employees/{employee}`

- **Header:** photo, name, code, designation, department, status badge, linked user (or "No login yet" prompt, H8). Actions (with permission): Edit, Record event, Exit / Rejoin, Link user / Create user / Unlink.
- **Summary cards:** joining date and tenure, type, manager (link), branch, phone (tap to call).
- **Tabs** (segmented control): Overview (basic fields; full fields when allowed; direct reports) · Documents (H13) · Employment history (events newest first; salary columns only with salary visibility) · Education & experience (full) · Salary (salary visibility) · Notes (`foundation.notes`, full) · History (`foundation.history`, full).
- Mobile: summary cards stack, actions in a bottom sheet behind one top-bar button.

### 5.4 Exit — `hrm/employees/{employee}/exit`

Two steps: **Details** (exit status*, exit date*, reason*, note) → **Checks & confirm** (exit check items grouped as blocking / info, with links; Confirm disabled while any item blocks). Rejoin is a bottom sheet / modal on the profile.

### 5.5 Org chart — `hrm/org-chart`

Tree of active-employment employees by `manager_id` (roots: no manager or a manager outside active employment). Each node: photo, name, designation, report count; tapping opens the profile. Filter by department keeps members of that department and the chain above them. Desktop: indented collapsible tree with connectors; mobile: collapsible list rows (`x-ui.collapsible`), 44 px rows.

### 5.6 Expiring documents — `hrm/documents/expiring`

Segmented filter: Expired · 30 days · 60 days · 90 days (default 30). Columns: employee (link), document type, number, expiry date, days left (red when expired). Mobile: rows with the same fields and a badge.

### 5.7 User form change — `admin/users/create`, `admin/users/{user}/edit`

Adds the **Employee** select (H8) above Name; the `?employee=CODE` query string preselects it on create.

## 6. Error handling

- Action rule failures throw `ValidationException`, shown inline or as a toast in sheets.
- 403 comes from route `can:` middleware, `EmployeePolicy` and `authorize()` in every Livewire action. Restricted fields are neither rendered nor accepted: actions ignore restricted input keys and refuse salary changes without `update_salary`.
- Refused with a message: changing department / designation / salary without an event, setting an exit status on the form, manager cycles, a department parent cycle, linking a user or employee that is already linked, exiting with a blocking check, an exit date before the joining date, a document without its required expiry date.
- Jobs are idempotent through `expiry_notified_at` and `probation_notified_at`.

## 7. Testing

Pest feature tests under `tests/Feature/Hrm/` (in-memory SQLite).

| Area | Cases |
|---|---|
| Access | each route 403 / 200 by permission; nav items; field visibility basic / full / salary / self (HR-AC-04); viewer cannot open documents; export columns follow visibility |
| Seeders | idempotent; lookup rows and flags; permissions and grants |
| Employees | create with auto and manual code; code immutable; full name; phone normalising; NID unique; joining date rule (HR-BR-04); manager cycle (HR-BR-03); JOINED event; `EmployeeCreated`; restricted input ignored; salary needs `update_salary` |
| Events | department / designation / salary change refused without an event; event applied with from/to values (HR-AC-02); CONFIRMED sets confirmation date; effective date rule; salary columns hidden without visibility |
| Exit / rejoin | exit sets status, date, reason, event; blocking check stops exit; linked user deactivated and sessions removed (HR-AC-03 user part); `exit_checklist` sent; exit statuses refused on the form; rejoin restores ACTIVE with REJOINED event; `assignable()` excludes exited / suspended |
| Users link | user form employee select, prefill, uniqueness both ways (FD-BR-03); Link / Unlink / Create user from profile; FK |
| Documents | expiry required by type; file stored as attachment; expiring list buckets (HR-AC-05); notification once, reset on expiry change |
| Probation | notified once within 15 days; reset on confirmation date change |
| Master data | HRM tables gated by `hrm.masters`; status flags not editable; `employee` field type on branches / business lines / departments; department parent cycle refused |
| CRM | employee referrer validates against employees; data migration clears old user ids and keeps the name |
| Org chart | tree roots and nesting; department filter keeps the chain |

No browser tests. When the build is done, the screens are listed for the user to check at 390×844 and at desktop width.

## 8. Out of scope

Everything in H18, plus attendance and leave (docs/09 open question 2) and SMS delivery.

## 9. Implementation deviations

Where the build differs from the sections above, the build is authoritative.

- H2 / §3: `Employee::isViewableBy` needs **full** visibility (or being the employee), not `view_basic`. The shared attachments download, notes and history panels trust it, and document files can hold NID scans.
- H5: events recorded from the employee form use the same sheet as the profile; the event types offered exclude JOINED, RESIGNED, TERMINATED, RETIRED and REJOINED (`EmploymentEventType::RESERVED`), which only their own Actions write.
- H6 / H7: the form cannot change the status of a former employee; only Rejoin brings them back. Editing other fields of a former employee still works.
- H7: `ExitEmployee` switches off the linked login inside its own transaction through `SetUserActive`, so a refusal there (own account, last super admin) rolls the exit back. The Foundation `DeactivateLinkedUser` listener covers `EmployeeDeactivated` from any other source. The exit wizard preselects Resigned and today.
- H8: the user form changes `users.employee_id` only when the caller sends `employee_id`, so other callers of `UpdateUser` cannot unlink by accident.
- H10: `SaveLookup` (Foundation) uses the HRM `AssignableEmployee` rule for the `employee` field type. `business_lines.manager_employee_id` was added to the BusinessLine fillable list.
- H12: `Lead::referrerUser` became `Lead::referrerEmployee`.
- H13: deleting an employee document soft-deletes its attachment under `hrm.documents.manage`, without the general `attachments.delete_*` permissions.
- §5.3: the employment history tab key is `events`; direct reports list current employees only.
- §5.5: anyone caught in a manager loop in old data is shown as a root instead of disappearing.
- §5.1: the export column list is `Employees\Index::exportColumns()`.
