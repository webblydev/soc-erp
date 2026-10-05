# SOC ERP v2 — 02 · Catalog (Business Lines, Services, Units, Work Items, Materials)

**Build phase:** 1 · **Depends on:** 01 · **Used by:** 03, 04, 05, 06, 07, 08
**Replaces (legacy):** Settings › Type Entry (client types = business lines), Service Entry (`software` / "Requirement"), Material Entry, Project Entry project types

---

## 1. Purpose & Scope

One place that defines **what SOC sells and measures**:

- Business lines (divisions) and their project number prefixes and revenue accounts
- Service categories and services (sellable) with default rates and accounts
- Units of measure
- Work items (BOQ / measurement items)
- Materials

Legacy data shows two concepts mixed together: "Client Type" (SOC-CON, SOC-CON-BLE, SOC-CETP, SOC-DSW, SOC-AGENT…) and "Requirement / Service" (Building Design Work, RAJUK Approval, Interior…). v2 separates them: **business line** = which division owns the work; **service** = what is delivered.

---

## 2. Permissions

```text
catalog.business_lines.view | create | update
catalog.services.view | create | update
catalog.units.view | create | update
catalog.work_items.view | create | update | import
catalog.materials.view | create | update | import
```

| Role | Grants |
|---|---|
| management, super_admin | all |
| project_manager | view all; create/update work_items, materials |
| accountant / finance_manager | view all; update service revenue accounts |
| sales_* , engineer | view |

---

## 3. Data Model

### 3.1 `business_lines` [LOOKUP] + columns

| Column | Type | Null | Notes |
|---|---|---|---|
| project_prefix | VARCHAR(30) | no | UNIQUE; e.g. `SOC-BD`, `SOC-BD&RA`, `SOC-CON-BLE` |
| revenue_account_id | BIGINT | yes | FK accounts (default revenue) |
| manager_employee_id | BIGINT | yes | FK employees |
| is_internal | TINYINT(1) | no | internal (HR, Accounts, Management) — not sellable |

Seed (from legacy client types and project prefixes — confirm with SOC):

| code | name | project_prefix |
|---|---|---|
| BD | Building Design | SOC-BD |
| BDRA | Building Design & RAJUK Approval | SOC-BD&RA |
| CON | Construction & Engineering Services | SOC-CON |
| CON-BLE | Bank Loan Estimate | SOC-CON-BLE |
| CON-UPS | Union Parishad Sheet | SOC-CON-UPS |
| CON-PWE | (confirm meaning) | SOC-CON-PWE |
| INT | Interior Design & Work | SOC-INT |
| CETP | Civil Engineering Training Program | SOC-CETP |
| DSW | Digital Survey Work | SOC-DSW |
| AMZ | (confirm meaning) | SOC-AMZ |
| EBC | (confirm meaning) | SOC-EBC |
| TSE | TSE (M&S) — confirm meaning | SOC-TSE |
| AGENT | Agent / Referral channel | SOC-AGENT |
| MGT | Management (internal) | SOC-MANAGEMENT |
| MS | Marketing & Sales (internal) | SOC-M&S |

### 3.2 `service_categories` [LOOKUP]
Seed: `DESIGN` Design · `APPROVAL` Approval & Permits · `WORKS` Construction & Works · `ENGG` Engineering & Supervision · `SURVEY` Survey & Testing · `ESTIMATE` Estimating · `TRAINING` Training · `OTHER` Other.

### 3.3 `services`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| code | VARCHAR(40) | no | UNIQUE |
| name | VARCHAR(150) | no | |
| service_category_id | BIGINT | no | FK |
| business_line_id | BIGINT | yes | default business line |
| default_unit_id | BIGINT | yes | FK units |
| default_rate | DECIMAL(18,4) | yes | |
| pricing_basis | VARCHAR(20) | no | `fixed`, `per_unit`, `percent_of_cost` (lookup `pricing_bases`) |
| revenue_account_id | BIGINT | yes | overrides business line |
| vat_rate_id | BIGINT | yes | FK tax_rates |
| requires_approval_tracking | TINYINT(1) | no | creates approval checklist on project (e.g. RAJUK) |
| default_task_template_id | BIGINT | yes | FK task_templates (04) |
| description | TEXT | yes | appears on quotation/invoice |
| is_active | TINYINT(1) | no | |
| [AUDIT] [SOFT] | | | |

Seed (legacy sellable services):
Building Design Work · Building Design & RAJUK Approval Work · Interior Design Work · Interior Design & Work · Interior & Exterior Work · Building Renovation Work · Engineering Service · Structural Design & Supervision · Bank Loan Estimate · Union Parishad Sheet · Digital Survey Work · Soil Test Work · Estimating Work · CETP & ATP Program.

Legacy internal "services" (HR & Admin Work, Accounts Work, Supply Chain Management Work, Inventory Management Work, Customer Relation, Logistic Work) → **task_types** in 04, not services. Test rows (`alamin work`) dropped.

### 3.4 `units` [LOOKUP] + columns
`symbol VARCHAR(15)`, `unit_kind` (lookup: length, area, volume, weight, count, time, lump). Seed: rft, rm, sft, sqm, cft, cum, nos, kg, ton, bag, ltr, katha, decimal, floor, job, ls (lump sum), month, day, participant.

### 3.5 `work_item_categories` [LOOKUP]
Earthwork, Concrete, Reinforcement, Masonry, Plaster, Flooring, Painting, Doors & Windows, Electrical, Plumbing, Sanitary, Interior, Steel Works, Miscellaneous.

### 3.6 `work_items`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| code | VARCHAR(40) | no | UNIQUE (legacy `item_code`) |
| name | VARCHAR(255) | no | |
| work_item_category_id | BIGINT | no | |
| unit_id | BIGINT | no | |
| measurement_formula | VARCHAR(20) | no | `nos_l_w_h`, `nos_l_w`, `nos_l`, `nos`, `manual` |
| standard_rate | DECIMAL(18,4) | yes | e.g. PWD schedule rate |
| specification | TEXT | yes | |
| is_active | TINYINT(1) | no | |
| [AUDIT] | | | |

### 3.7 `material_categories` [LOOKUP]
Cement, Sand, Brick, Stone/Aggregate, Rod/Steel, Tiles, Paint, Wood, Electrical, Plumbing, Sanitary, Glass & Aluminium, Others.

### 3.8 `materials`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| code | VARCHAR(40) | no | UNIQUE |
| name | VARCHAR(200) | no | |
| material_category_id | BIGINT | no | |
| unit_id | BIGINT | no | |
| standard_rate | DECIMAL(18,4) | yes | |
| expense_account_id | BIGINT | yes | default 5100 Materials |
| is_active | TINYINT(1) | no | |
| [AUDIT] | | | |

---

## 4. Screens

| Screen | Type | Notes |
|---|---|---|
| Business Lines | list + form | prefix uniqueness check; shows count of projects; revenue account select (posting accounts of type Revenue only) |
| Services | list + form | filters: category, business line, active; form fields per §3.3 |
| Units | Master Data generic screen | |
| Work Items | list + form + **Excel import** | import template: code, name, category, unit, formula, rate |
| Materials | list + form + **Excel import** | import template: code, name, category, unit, rate |

Import behaviour: preview grid with validation errors per row → confirm → upsert by `code`; result summary (created / updated / failed).

---

## 5. Business Rules

| ID | Rule |
|---|---|
| CT-BR-01 | `project_prefix` unique, allowed chars `A-Z 0-9 - &`, max 30; cannot change once a project uses it. |
| CT-BR-02 | Revenue account on service/business line must be an active posting account of type Revenue. |
| CT-BR-03 | Service default rate ≥ 0. |
| CT-BR-04 | Internal business lines (`is_internal`) cannot be chosen on leads or invoices; allowed on internal projects. |
| CT-BR-05 | Work item code and material code unique; codes are case-insensitive. |
| CT-BR-06 | Changing a standard rate never changes existing estimates/invoices (rates are copied at line creation). |

Revenue account resolution order on invoice line: line override → service.revenue_account_id → business_line.revenue_account_id → setting `accounting.default_revenue_account`.

---

## 6. Acceptance Criteria

| ID | Criterion |
|---|---|
| CT-AC-01 | Creating a project under business line "Building Design" auto-numbers `SOC-BD-0103` (continuing from migrated max). |
| CT-AC-02 | Importing 200 work items from Excel with 3 bad rows creates 197 and lists the 3 errors. |
| CT-AC-03 | A service marked `requires_approval_tracking` creates a RAJUK approval checklist when added to a project. |
| CT-AC-04 | An invoice line for "Soil Test Work" posts to the Survey & Soil Test revenue account. |

## 7. Open Questions

1. Meaning of business-line codes AMZ, EBC, CON-PWE, TSE(M&S).
2. Should CETP be billed per participant (unit = participant)?
3. Does SOC use PWD schedule rates for BOQ? If so, provide the schedule to import as work items.
