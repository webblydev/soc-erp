# Catalog — Design

**Phase:** 1, sub-project 4 of 4 (Foundation core → Admin screens → Shared services → **Catalog**)
**Source specs:** `docs/00-index-and-conventions.md`, `docs/02-catalog.md`
**Builds on:** `docs/superpowers/specs/2026-10-06-admin-screens-design.md`, `docs/superpowers/specs/2026-10-06-shared-services-design.md`
**Date:** 06 Oct 2026
**Status:** Draft, awaiting review.

## 1. Goal

Define what SOC sells and measures: business lines, service categories and services, units, work items and materials (docs/02). Lookup-style tables use the existing Master Data screen. Services, work items and materials get their own list and form screens. Work items and materials also get an Excel import with a preview step. Every screen meets the desktop standards (doc 00 §7.1–7.3) and the native mobile rules (doc 00 §7.6).

## 2. Decisions

| # | Decision |
|---|---|
| C1 | **Columns that point at future tables** (`revenue_account_id`, `expense_account_id`, `vat_rate_id` → 08; `manager_employee_id` → 09; `default_task_template_id` → 04) are nullable `BIGINT UNSIGNED` with no FK constraint (Foundation D4). They are not shown on any form. The module that creates the target table adds the constraint, the form fields and the rule that goes with them: CT-BR-02 (revenue account must be an active posting Revenue account) arrives with 08. |
| C2 | **Business lines and units use the generic Master Data screen.** They are registered in `config/lookups.php` with extra fields, and each has its own nav item linking to `admin/master-data/{table}`, the same way Branches does. The project count and revenue account select come with 04 and 08. |
| C3 | **Master Data registry additions.** (a) A `lookup` extra-field type that renders `<x-lookup-select>` over another registered table and stores its id (for `units.unit_kind_id`). (b) An optional `rules` key on an extra field, with extra Laravel rules appended to the type's rules. `SaveLookup` adds the `unique:{table},{column}` ignore rule when `unique => true`. (c) An optional `uppercase => true` flag that upper-cases the value before validation. |
| C4 | **Codes are case-insensitive (CT-BR-05).** Service, work item and material codes are trimmed and stored upper-case, and uniqueness is checked on that value. Allowed characters are `A-Z 0-9 . _ -`, max 40. They serve as route keys (`{service:code}` etc.), which is why `/` and spaces are refused. |
| C5 | **`pricing_basis` becomes `pricing_basis_id`**, an FK to a `pricing_bases` lookup (system rows `fixed`, `per_unit`, `percent_of_cost`). This follows the project's lookup-FK pattern instead of the VARCHAR code in docs/02 §3.3. Code checks the row's `code`. |
| C6 | **`measurement_formula`** stays a VARCHAR(20) holding a value of the PHP backed enum `App\Modules\Catalog\Enums\MeasurementFormula` (`nos_l_w_h`, `nos_l_w`, `nos_l`, `nos`, `manual`). It drives quantity calculations in 05, so it is code behaviour, not editable master data. Each case has a label and its list of input dimensions. |
| C7 | **Full-page forms** for services, work items and materials (`x-shell.form-page`), so the edit page can carry the `foundation.history` audit panel (doc 00 §10). Nothing is deleted. Rows are deactivated with the Active switch (CM-BR-02). Services keep `deleted_at` per docs/02 §3.3 for later use, but no screen soft-deletes them. |
| C8 | **Category lookups have their own permission resource.** `service_categories`, `work_item_categories`, `material_categories`, `unit_kinds` and `pricing_bases` are registered under `catalog.master_data` (view/create/update/deactivate). Business lines and units gain `deactivate` so the Master Data screen can manage them: `catalog.business_lines.*` and `catalog.units.*`. Each list screen has a "Categories" header action linking to its category table. |
| C9 | **Internal business lines** (`is_internal`) are excluded from the service form's business line select. CT-BR-04 for leads and invoices belongs to 03 and 06. |
| C10 | **Shared import flow** (`App\Support\Imports`). Steps: download the template, upload `.xlsx` or `.csv` (≤ 5 MB, ≤ 2,000 data rows), see a preview grid, confirm, see the result. Every row is validated with the same rules as the form. Category and unit cells match an active row by code or name, case-insensitively. The preview marks each row as **New**, **Update** (code exists) or **Error** with its messages. Confirm re-reads the stored upload and validates again, so the preview can never go stale. It then upserts the valid rows by code in one transaction and skips the invalid ones. The result shows created / updated / failed counts, and the failed rows can be downloaded as an `.xlsx` with an `errors` column. |
| C11 | **Import permission.** The import screens need `catalog.{work_items|materials}.import`. That permission covers both creating and updating through import. |
| C12 | **Deferred rules.** CT-BR-01 "prefix cannot change once a project uses it" is added by 04, which owns `projects`. CT-BR-06 (rates are copied onto lines) is a rule for 05 and 06. The revenue account resolution order belongs to 06 and 08. CT-AC-01, 03 and 04 are verified in those modules. |
| C13 | **Accountant / finance manager** get view only for now. "Update service revenue accounts" arrives with the revenue account field in 08, as a field-level `catalog.services.update_accounts` permission. |

## 3. Data model

Migrations in `database/migrations/catalog/` (loaded by `CatalogServiceProvider`), models in `app/Modules/Catalog/Models`. All models are Auditable. Morph aliases: `business_line`, `service_category`, `service`, `pricing_basis`, `unit_kind`, `unit`, `work_item_category`, `work_item`, `material_category`, `material`.

- **`business_lines`**: [LOOKUP] + `project_prefix` VARCHAR(30) UNIQUE, `revenue_account_id` (C1), `manager_employee_id` (C1), `is_internal` TINYINT(1) default 0.
- **`service_categories`**, **`work_item_categories`**, **`material_categories`**, **`unit_kinds`**, **`pricing_bases`**: [LOOKUP].
- **`units`**: [LOOKUP] + `symbol` VARCHAR(15), `unit_kind_id` FK unit_kinds.
- **`services`**: [STD] + `code` VARCHAR(40) UNIQUE, `name` VARCHAR(150), `service_category_id` FK, `business_line_id` FK nullable, `default_unit_id` FK units nullable, `default_rate` DECIMAL(18,4) nullable, `pricing_basis_id` FK (C5), `revenue_account_id` (C1), `vat_rate_id` (C1), `requires_approval_tracking` default 0, `default_task_template_id` (C1), `description` TEXT nullable, `is_active` default 1, [AUDIT], [SOFT]. Indexes on the FKs and `is_active`.
- **`work_items`**: [STD] + `code` VARCHAR(40) UNIQUE, `name` VARCHAR(255), `work_item_category_id` FK, `unit_id` FK, `measurement_formula` VARCHAR(20) (C6), `standard_rate` DECIMAL(18,4) nullable, `specification` TEXT nullable, `is_active`, [AUDIT].
- **`materials`**: [STD] + `code` VARCHAR(40) UNIQUE, `name` VARCHAR(200), `material_category_id` FK, `unit_id` FK, `standard_rate` DECIMAL(18,4) nullable, `expense_account_id` (C1), `is_active`, [AUDIT].

All FKs are `ON DELETE RESTRICT`, so deleting an in-use lookup row in Master Data gives "In use — deactivate instead" (admin D4).

### 3.1 Seeds (`database/seeders/Catalog/`, idempotent upsert by `code`)

- **Business lines:** the 15 rows of docs/02 §3.1. `MGT` and `MS` have `is_internal = 1`. AMZ, EBC, CON-PWE and TSE are named by their code until SOC confirms what they mean (docs/02 open question 1). None are system rows.
- **Service categories, work item categories, material categories:** as listed in docs/02 §3.2, §3.5 and §3.7. Codes are upper snake case (`DOORS_WINDOWS`, `STONE_AGGREGATE`, …).
- **Unit kinds** (system): `length`, `area`, `volume`, `weight`, `count`, `time`, `lump`.
- **Pricing bases** (system): `fixed`, `per_unit`, `percent_of_cost`.
- **Units:** the 19 rows of docs/02 §3.4, each with its symbol and kind (rft, rm → length; sft, sqm, katha, decimal → area; cft, cum, ltr → volume; kg, ton, bag → weight; nos, floor, participant → count; month, day → time; job, ls → lump).
- **Services:** the 14 legacy sellable services. Proposed mapping, **to be confirmed by SOC**:

| code | name | category | business line | flags |
|---|---|---|---|---|
| BD | Building Design Work | DESIGN | BD | |
| BDRA | Building Design & RAJUK Approval Work | APPROVAL | BDRA | requires approval tracking |
| INT-DESIGN | Interior Design Work | DESIGN | INT | |
| INT-WORK | Interior Design & Work | WORKS | INT | |
| INT-EXT | Interior & Exterior Work | WORKS | INT | |
| RENOVATION | Building Renovation Work | WORKS | CON | |
| ENGG | Engineering Service | ENGG | CON | |
| STRUCT | Structural Design & Supervision | ENGG | CON | |
| BLE | Bank Loan Estimate | ESTIMATE | CON-BLE | |
| UPS | Union Parishad Sheet | ESTIMATE | CON-UPS | |
| DSW | Digital Survey Work | SURVEY | DSW | |
| SOIL | Soil Test Work | SURVEY | CON | |
| ESTIMATE | Estimating Work | ESTIMATE | CON | |
| CETP | CETP & ATP Program | TRAINING | CETP | per unit, unit `participant` (open question 2) |

  All other services use `fixed` pricing with no default rate.
- **Work items and materials:** none seeded. They arrive by import (open question 3). Factories exist for tests and for the UAT demo seeder.
- **Permissions:** `app/Modules/Catalog/permissions.php` (§4.1).
- **Number sequences:** none. The project format `{bl_prefix}-{seq:4}` already exists. 04 passes `business_line.project_prefix` in as `bl_prefix`.

## 4. Architecture

### 4.1 Permissions (`app/Modules/Catalog/permissions.php`)

```text
catalog.business_lines.view | create | update | deactivate
catalog.units.view | create | update | deactivate
catalog.master_data.view | create | update | deactivate
catalog.services.view | create | update
catalog.work_items.view | create | update | import
catalog.materials.view | create | update | import
```

| Role | Grants |
|---|---|
| super_admin | everything (Gate::before) |
| management | `catalog.*` |
| project_manager | `catalog.*.view`; `catalog.work_items.*`, `catalog.materials.*` |
| accountant, finance_manager | `catalog.*.view` (C13) |
| sales_manager, sales_executive, engineer | `catalog.*.view` |
| viewer | already `*.view` |

### 4.2 Placement

- `app/Modules/Catalog/` with `CatalogServiceProvider` (migrations, morph map, Livewire registration), registered in `bootstrap/providers.php`.
- Routes: `routes/modules/catalog.php`, required from `routes/web.php`. Prefix `catalog/`, name prefix `catalog.`, `app` middleware, `can:` per route.
- Livewire (class-based): `Catalog\Livewire\Services\{Index,Form}`, `WorkItems\{Index,Form,Import}`, `Materials\{Index,Form,Import}`. Views in `resources/views/livewire/catalog/...`.
- Actions (`app/Modules/Catalog/Actions`): `SaveService`, `SaveWorkItem`, `SaveMaterial`, `ImportWorkItems`, `ImportMaterials`. Each validates (throws `ValidationException`) and writes in `DB::transaction()`. Each Save Action exposes its rules for the import to reuse.
- Shared import (`app/Support/Imports`):
  - `ImportDefinition` interface: `columns()` (heading → label), `exampleRow()`, `normalise(array $row)`, `validate(array $row): array` (errors), `findExisting(string $code)`, `save(array $row)`.
  - `ImportPreview` (reads the file into rows and runs validation).
  - `WithImport` Livewire trait (upload, preview, confirm, result state).
  - `TemplateExport` and `FailedRowsExport`.
  - The `x-shell.import` Blade component (desktop: steps above a preview `x-ui.server-table`; mobile: steps plus `x-ui.item` rows with a status badge, tap to open the row's errors in a bottom sheet, and a sticky Confirm bar).
  - Uploads go to the `private` disk under `imports/{uuid}` and are deleted after confirm, or after 24 h by the scheduler.

### 4.3 Master Data changes (C3)

- `config/lookups.php` gains the seven catalog tables with `module => 'catalog'`, so they appear as a "Catalog" group on the Master Data screen.
- `business_lines` extra fields:
  - `project_prefix`: text, required, uppercase, `unique`, rules `max:30`, `regex:/^[A-Z0-9&-]+$/`.
  - `is_internal`: bool.
- `units` extra fields:
  - `symbol`: text, required, `max:15`.
  - `unit_kind_id`: `lookup`, table `unit_kinds`, required.
- `LookupRegistry` PHPDoc shape, the Master Data sheet view and `SaveLookup` handle `lookup`, `rules`, `unique` and `uppercase`. Existing tables are unchanged.

### 4.4 Navigation

A new **Catalog** group in `config/navigation.php` between CRM and Projects (icon `package`):

- Business lines → `admin.master-data.show` with `business_lines`, gated by `catalog.business_lines.view`
- Services → `catalog.services.view`
- Units → master data `units`, gated by `catalog.units.view`
- Work items → `catalog.work_items.view`
- Materials → `catalog.materials.view`

On mobile, Catalog appears in the More sheet. It is not a bottom-nav destination.

## 5. Screens

All screens follow doc 00 §7.6 on mobile: fixed top bar with back and title, list rows instead of tables, bottom sheets for filters and actions, sticky action bars on forms, 44 px tap targets, `wire:navigate` everywhere, and searchable selects (`x-lookup-select`) for FKs.

### 5.1 Services — `catalog/services`, `catalog/services/create`, `catalog/services/{service:code}/edit`

- **List:** Code, Name, Category, Business line, Pricing basis, Default rate (right-aligned, 4 dp trimmed to 2 when exact), Active. Filters: category, business line, active (default active). Search: code, name. Header actions: Categories (master data `service_categories`), New service.
- **Mobile row:** name, code, category badge, rate, active dot, chevron.
- **Form:**
  - Code* (C4)
  - Name* (≤150)
  - Category* (active service categories)
  - Business line (active, not internal, C9)
  - Default unit
  - Pricing basis* (default `fixed`)
  - Default rate (`inputmode="decimal"`, ≥ 0, CT-BR-03)
  - Requires approval tracking (switch, with help text "Creates an approval checklist when added to a project")
  - Description (textarea, "Shown on quotations and invoices")
  - Active
- **Edit page:** the `foundation.history` panel.

### 5.2 Work items — `catalog/work-items`, `…/create`, `…/{workItem:code}/edit`, `…/import`

- **List:** Code, Name, Category, Unit, Formula, Standard rate, Active. Filters: category, unit, active. Search: code, name. Header actions: Categories, Import, New work item.
- **Mobile row:** name, code, unit symbol, rate, chevron.
- **Form:**
  - Code*
  - Name* (≤255)
  - Category*
  - Unit*
  - Measurement formula* (enum select, with a hint listing the dimensions it uses, e.g. "Nos × L × W × H")
  - Standard rate (≥ 0)
  - Specification (textarea)
  - Active
- **Import template columns:** `code, name, category, unit, formula, rate`. `formula` accepts the enum value or its label. `rate` may be blank.

### 5.3 Materials — `catalog/materials`, `…/create`, `…/{material:code}/edit`, `…/import`

- **List:** Code, Name, Category, Unit, Standard rate, Active. Filters: category, unit, active. Header actions: Categories, Import, New material.
- **Form:**
  - Code*
  - Name* (≤200)
  - Category*
  - Unit*
  - Standard rate (≥ 0)
  - Active
- **Import template columns:** `code, name, category, unit, rate`.

### 5.4 Import screen (both)

1. **Upload:**
   - "Download template" button.
   - File input (`.xlsx`, `.csv`).
   - Notes on the matching rules.
2. **Preview:**
   - Summary chips: New n · Update n · Error n.
   - Grid: Row #, the columns, Status badge, Errors.
   - An "Errors only" toggle.
   - Confirm imports the New and Update rows. It is disabled when there are none.
3. **Result:**
   - Created / updated / failed counts.
   - "Download failed rows" when any rows failed.
   - Back to list.

An import never deactivates rows that are missing from the file. A blank cell in an existing row leaves that field unchanged, except `rate`, which is cleared only when the cell holds `-`.

### 5.5 Master Data

Business lines, units and the five category tables use the existing screen (admin §5.3), including the new `lookup` field for unit kind and the prefix validation.

## 6. Error handling

- Action rule failures throw `ValidationException`. They show inline on forms and per row in the import preview.
- 403 comes from route `can:` middleware and from `authorize()` in every Livewire action. Nav items and header actions are hidden when the user lacks the permission.
- An unreadable file, missing required headings or more than 2,000 rows → one upload error, with no preview.
- Deleting an in-use category, unit or business line in Master Data → "In use — deactivate instead".

## 7. Testing

Pest feature tests under `tests/Feature/Catalog/` (in-memory SQLite).

| Area | Cases |
|---|---|
| Access | each catalog route returns 403 without the permission and 200 with it; nav shows only permitted items; project_manager can edit work items but not services; sales can only view |
| Seeders | idempotent; 15 business lines with internal flags; units linked to kinds; services mapped to categories and lines; permissions and grants synced |
| Master data | business line prefix: required, upper-cased, charset (CT-BR-01), unique; unit `lookup` field saved and validated against active unit kinds; catalog lookups gated by their `catalog.*` prefix; in-use unit delete refused |
| Services | create and update; code upper-cased and unique case-insensitively (CT-BR-05 pattern); negative rate refused (CT-BR-03); internal business line refused (C9); inactive category refused for new records but kept on existing ones (CM-BR-03); audit rows written; filters and search |
| Work items / materials | create and update; code case-insensitive unique (CT-BR-05); formula must be an enum value; rate ≥ 0 |
| Import | template download; preview classifies new / update / error; category and unit matched by code or name; CT-AC-02 (200 rows with 3 bad → 197 created and 3 listed, failed-rows export holds the 3); blank cells keep existing values; confirm re-validates; heading and row-limit errors; `import` permission required; upload removed after confirm |
| Registry | `lookup` / `rules` / `unique` / `uppercase` extra fields do not change existing tables' behaviour |

No browser tests. When the build is done, the screens are listed for the user to check at 390×844 and at desktop width.

## 8. Out of scope

- Revenue, expense and VAT account fields, manager employee and task template (C1).
- Project count on business lines.
- CT-BR-01 lock once a project uses a prefix, CT-BR-04, CT-BR-06 and revenue account resolution (C12).
- Excel and PDF export of catalog lists.
- PWD schedule rate data (open question 3).
- Deleting services, work items or materials.
