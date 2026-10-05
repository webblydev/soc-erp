# SOC ERP v2 — Module Specifications
## 00 · Index and Shared Conventions

**Client:** SOC Consultant & Development Ltd
**System:** CRM + ERP v2 (replacement for `soft.socbdltd.com`)
**Parent document:** `soc-crm-erp-plan-v2.md`
**Version:** 1.0 · 05 Oct 2026

---

## 1. Spec Set

| # | File | Module | Build phase |
|---|---|---|---|
| 00 | `00-index-and-conventions.md` | Shared conventions (this file) | — |
| 01 | `01-foundation-admin.md` | Auth, users, roles, permissions, settings, master data, locations, number sequences, attachments, audit, notifications | 1 |
| 02 | `02-catalog.md` | Business lines, service categories, services, units, work items, materials | 1 |
| 03 | `03-crm.md` | Leads, pipeline, activities & reminders, sales teams, customers, conversion | 2 |
| 04 | `04-projects.md` | Projects, contracts, payment schedules, project team, tasks, approvals, documents | 3 |
| 05 | `05-estimation-site.md` | BOQ / work estimates, material estimates, budgets, measurement book, site inspections | 4 |
| 06 | `06-sales-receivables.md` | Running bills, invoices, receipts, allocations, credit notes, customer statements | 6 |
| 07 | `07-purchases-payables.md` | Vendors, work orders, vendor running bills, vendor bills, vendor payments, debit notes | 6 |
| 08 | `08-accounting.md` | CoA, fiscal periods, journal engine, posting rules, bank & cash, contra, expenses, advances, tax, opening balances, period close | 5–6 |
| 09 | `09-hrm.md` | Employees, departments, designations, documents, assignments, (optional) salary | 9 |
| 10 | `10-reports-dashboard.md` | Dashboard widgets and all reports with exact columns, filters and formulas | 8 |
| 11 | `11-data-migration.md` | Legacy → v2 mapping, clean-up rules, reconciliation, cut-over runbook | 7 |

Each module spec follows the same layout:

1. Purpose & scope
2. Actors and permissions
3. Data model (tables, columns, types, keys, indexes)
4. Seed / master data
5. Screens (list, form, detail) with fields and behaviour
6. Workflows & state machines
7. Business rules & validations (numbered `XX-BR-nn`)
8. Integration with other modules (events in / out)
9. Notifications
10. Reports owned by the module
11. API / Livewire components (indicative)
12. Acceptance criteria (numbered `XX-AC-nn`)
13. Open questions

---

## 2. Technology Baseline

| Concern | Decision |
|---|---|
| Framework | Laravel (current supported major), PHP 8.3+ |
| UI | Livewire 3 + Alpine.js + Tailwind CSS; server-rendered; BLAT UI Library |
| DB | MySQL 8.0 (InnoDB, `utf8mb4_unicode_ci`) |
| Auth | Laravel session auth (Fortify)|
| Permissions | `spatie/laravel-permission` Custom User Role + Permission Setup with data visibility access |
| Audit | `owen-it/laravel-auditing` or custom observer writing to `audit_logs` |
| Files | Laravel filesystem, `local` private disk (S3-compatible later); downloads via signed routes |
| PDF | `carlos-meneses/laravel-mpdf` |
| Excel | `maatwebsite/excel` for exports/imports |
| Queue | Database queue driver (Redis later) for notifications, exports, report builds |
| Scheduler | Laravel scheduler (reminders, overdue checks, daily backups) |
| Tests | Pest; feature tests per business rule and per posting rule |

---

## 3. Code Structure

```text
app/
  Modules/
    Foundation/   (Models, Livewire, Policies, Services, Actions, Events, Listeners)
    Catalog/
    Crm/
    Projects/
    Estimation/
    Sales/
    Purchases/
    Accounting/
    Hrm/
    Reports/
  Support/        (Money, NumberSequenceService, AuditTrail, Lookups, Exports)
database/
  migrations/<module>/...
  seeders/<module>/...
routes/
  web.php → includes routes/modules/*.php
```

Rules:

- Business logic lives in **Action** classes (`ConvertLead`, `ApproveInvoice`, `PostJournal`), never in Livewire components or controllers.
- Cross-module communication by **domain events** (`InvoiceApproved`, `ReceiptPosted`, `LeadWon`) and service interfaces; modules never write each other's tables directly except through Actions.
- Every Action that changes money runs inside `DB::transaction()`.

---

## 4. Database Conventions

### 4.1 Naming

- Tables: plural snake_case (`lead_sources`). Pivot: alphabetical singular (`lead_service` is **not** used; use explicit tables with ids such as `lead_services`).
- PK: `id BIGINT UNSIGNED AUTO_INCREMENT`.
- FK: `<singular>_id`, `FOREIGN KEY … ON DELETE RESTRICT` unless stated.
- Booleans: `is_*` / `has_*`, `TINYINT(1)` default 0.
- Dates: `*_date DATE`; timestamps `*_at TIMESTAMP NULL`.

### 4.2 Standard column blocks

Referenced in table definitions as **[STD]**, **[AUDIT]**, **[SOFT]**, **[LOOKUP]**.

**[STD]**
| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED | no | PK |
| created_at | TIMESTAMP | yes | |
| updated_at | TIMESTAMP | yes | |

**[AUDIT]** (all transactional tables)
| Column | Type | Null | Notes |
|---|---|---|---|
| created_by | BIGINT UNSIGNED | yes | FK users |
| updated_by | BIGINT UNSIGNED | yes | FK users |

**[SOFT]** — `deleted_at TIMESTAMP NULL` (master and CRM data only; never on posted financial documents).

**[LOOKUP]** — every no-enum master table:
| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED | no | PK |
| code | VARCHAR(40) | no | UNIQUE; stable, used by code |
| name | VARCHAR(120) | no | display label |
| description | VARCHAR(255) | yes | |
| sort_order | SMALLINT | no | default 0 |
| color | VARCHAR(20) | yes | badge colour (Tailwind token) |
| is_active | TINYINT(1) | no | default 1 |
| is_system | TINYINT(1) | no | default 0; system rows cannot be deleted or have `code` changed |
| created_at / updated_at | TIMESTAMP | yes | |

Lookup tables may add flag columns (e.g. `is_won`, `is_closed`) — listed per module.

### 4.3 Data types

| Data | Type |
|---|---|
| Money | `DECIMAL(18,2)` |
| Quantity / measurement | `DECIMAL(18,4)` |
| Rate / unit price | `DECIMAL(18,4)` |
| Percent | `DECIMAL(7,4)` (e.g. 12.5000) |
| Phone | `VARCHAR(30)` stored normalised to `+8801XXXXXXXXX` |
| Document numbers | `VARCHAR(40)` UNIQUE |
| Long text | `TEXT` |
| JSON | `JSON` (audit values, settings only) |

### 4.4 Statuses

Statuses are lookup tables, never enums. Code checks behaviour flags (`is_closed`, `is_won`, `is_posted`) or the stable `code`, never `id` or `name`.

Where a **document** has a strict life-cycle (invoice, bill, journal), the allowed transitions are defined in a `*_status_transitions` table **or** in the Action class guard. This spec uses Action guards (simpler) unless stated.

### 4.5 Polymorphic references

Used only for: `attachments`, `crm_activities` (subject), `audit_logs`, `notes`, `payment_allocations`, `journal_entries.source`. Morph map aliases (e.g. `lead`, `customer`, `project`, `invoice`) are registered in `AppServiceProvider` — never store class names.

---

## 5. Numbering

All document numbers come from `NumberSequenceService::next($documentType, $context)` (spec in 01 §3.8). Formats are configurable; defaults:

| Document | Default format | Example |
|---|---|---|
| Lead | `L-{seq:6}` | L-000124 |
| Customer | `C-{seq:6}` | C-000057 |
| Project | `{bl_prefix}-{seq:4}` (sequence per business line) | SOC-BD-0103 |
| Task | `T-{yy}-{seq:5}` | T-26-00509 |
| Estimate | `EST-{yy}-{seq:4}` | EST-26-0012 |
| Site inspection | `SI-{yy}-{seq:4}` | SI-26-0045 |
| MB entry | `MB-{yy}-{seq:5}` | |
| Customer running bill | `RB-{yy}-{seq:4}` | |
| Invoice | `INV-{yy}-{seq:5}` | INV-26-00031 |
| Receipt | `RCV-{yy}-{seq:5}` | |
| Credit note | `CN-{yy}-{seq:4}` | |
| Work order | `WO-{yy}-{seq:4}` | |
| Vendor bill | `BILL-{yy}-{seq:5}` | |
| Payment voucher | `PV-{yy}-{seq:5}` | |
| Expense | `EXP-{yy}-{seq:5}` | |
| Employee advance | `ADV-{yy}-{seq:4}` | |
| Contra | `CT-{yy}-{seq:5}` | |
| Journal | `JV-{yy}-{seq:5}` | |
| Employee | `EMP-{seq:4}` | |
| Vendor | `V-{seq:4}` | |

`{yy}` = fiscal year short code (Bangladesh FY July–June: FY 2026-27 → `27`; configurable in Settings).

---

## 6. Permissions Convention

Format: `{module}.{resource}.{action}`

Standard actions: `view`, `create`, `update`, `delete`, `export`, `print`.
Workflow actions: `submit`, `approve`, `post`, `cancel`, `reverse`, `assign`, `convert`, `lock`.

Data scope permissions (checked in policies and list queries):

| Suffix | Meaning |
|---|---|
| `.view_own` | Records where user is owner / assignee / creator |
| `.view_team` | Records owned by members of user's sales team or user's projects |
| `.view_all` | All records |

Example set for leads: `crm.leads.view_own`, `crm.leads.view_team`, `crm.leads.view_all`, `crm.leads.create`, `crm.leads.update`, `crm.leads.delete`, `crm.leads.assign`, `crm.leads.convert`, `crm.leads.export`.

Each module spec lists its full permission set in §2.

---

## 7. UI Conventions

### 7.1 Layout

- Left sidebar grouped by module (CRM · Projects · Estimation & Site · Sales · Purchases · Accounting · HRM · Reports · Admin); items hidden if user lacks `view` permission.
- Top bar: global search (lead/customer/project/invoice number, phone), quick-create (+), notifications bell, user menu.
- Breadcrumbs on every page.
- All components will be using blat UI

### 7.2 List pages (standard)

- Search box (debounced 300 ms) across number, name, phone.
- Filter panel (module-specific) + **saved views** per user.
- Column chooser, sort on every column, pagination 25/50/100.
- Bulk actions where listed (assign, export, change status).
- Export: Excel and PDF of current filter.
- Status shown as coloured badge from lookup `color`.

### 7.3 Form pages (standard)

- Required fields marked `*`; validation inline on blur and on save.
- Searchable selects (Tom Select style) for any FK with > 15 options; "+ New" inline create for master data when user has permission.
- Line-item grids (invoice items, BOQ lines) editable inline with keyboard (Tab / Enter adds row), running totals in footer.
- Money fields right-aligned, formatted `৳ 12,34,567.00` (Indian/BD grouping).
- Dates displayed `dd-MMM-yyyy` (05-Oct-2026); stored ISO.
- Unsaved-changes guard on navigation.

### 7.4 Detail pages (standard)

- Header: number, title, status badge, primary actions (Edit, Approve, Post, Print, More ▾).
- Tabs: Overview · module-specific tabs · Activities · Documents · History (audit).
- Right rail: key figures (e.g. billed / collected / due).

### 7.5 Print layouts

A4 portrait, company letterhead from Company Profile (logo, name, address, phone, email, TIN/BIN), document title, number, date, party block, line table, totals, amount in words (English, BDT: "Taka … Only"), signatures row (Prepared by / Checked by / Approved by / Received by).

---

## 8. Common Business Rules (apply to every module)

| ID | Rule |
|---|---|
| CM-BR-01 | Every create/update/delete/status change writes an `audit_logs` row with old/new values. |
| CM-BR-02 | Records referenced by another record cannot be hard-deleted; master data is deactivated (`is_active = 0`) instead. |
| CM-BR-03 | Inactive master records are hidden from new-entry dropdowns but still display on existing records. |
| CM-BR-04 | Document numbers are assigned on first save (draft) and never reused, even if the draft is deleted. |
| CM-BR-05 | Approved / posted financial documents are read-only; corrections via cancel (reversal) or credit/debit notes. |
| CM-BR-06 | Every financial document stores the `project_id` when it relates to a project. |
| CM-BR-07 | All money arithmetic in PHP uses `brick/money` or integer paisa; rounding half-up to 2 decimals at line level, totals = sum of rounded lines. |
| CM-BR-08 | All lists respect data scope permissions (§6). |
| CM-BR-09 | Phone numbers are normalised; duplicate checks use normalised value. |
| CM-BR-10 | Users can only act on documents in an **unlocked** fiscal period (see 08). |

---

## 9. Cross-Module Event Catalogue

| Event | Raised by | Consumed by | Effect |
|---|---|---|---|
| `LeadWon` | CRM | CRM | Opens conversion wizard |
| `LeadConverted` | CRM | Projects, Reports | Project created; conversion stats |
| `ProjectCreated` | Projects | Accounting (dimension), Estimation | Project available for costing |
| `ProjectStatusChanged` | Projects | CRM (customer timeline), Notifications | |
| `EstimateApproved` | Estimation | Projects (budget) | Budget lines created/updated |
| `MeasurementVerified` | Estimation | Sales / Purchases | Available for running bill |
| `RunningBillApproved` | Sales / Purchases | Sales / Purchases | Invoice / vendor bill generated |
| `InvoiceApproved` | Sales | Accounting | Journal posted (AR / Revenue / VAT) |
| `ReceiptPosted` | Sales | Accounting, Projects | Journal; payment schedule progress |
| `VendorBillApproved` | Purchases | Accounting | Journal (Cost / AP / TDS) |
| `VendorPaymentPosted` | Purchases | Accounting | Journal |
| `ExpensePosted` | Accounting | Projects | Project actual cost |
| `DocumentCancelled` | any finance | Accounting | Reversal journal |
| `PeriodLocked` | Accounting | all | Blocks edits in period |
| `EmployeeDeactivated` | HRM | Foundation, Projects | User disabled, open tasks flagged |

---

## 10. Definition of Done (every module)

- Migrations + seeders for all tables and lookup data in the spec.
- Policies and permissions seeded and assigned to default roles.
- All screens in §5 of the module spec built with list / form / detail standards above.
- Every `BR` rule has at least one automated test; every `AC` passes manual QA.
- Audit trail visible on detail pages.
- Exports and print layouts working.
- No N+1 queries on list pages (checked with Laravel Debugbar / `preventLazyLoading`).
- Seeded demo data for UAT.
