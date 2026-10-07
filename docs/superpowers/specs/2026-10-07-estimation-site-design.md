# Estimation & Site — Design

**Phase:** 4 (one sub-project covering docs/05, without the parts that need 06–08)
**Source specs:** `docs/00-index-and-conventions.md`, `docs/05-estimation-site.md`, `docs/11-data-migration.md`
**Builds on:** `docs/superpowers/specs/2026-10-07-projects-design.md`, `docs/superpowers/specs/2026-10-06-catalog-design.md`, `docs/superpowers/specs/2026-10-06-legacy-seed-design.md`
**Date:** 07 Oct 2026
**Status:** Done, 2026-10-07, on branch `estimation`. Plan: `docs/superpowers/plans/2026-10-07-estimation-site.md`. Built without review stops at the user's request; corrections to follow.

## 1. Goal

Give each project its numbers and its site record (docs/05):

- **estimates** (BOQ measurement sheets and material estimates) with sections, revisions, approval, compare and print;
- a **project budget** by cost category, built from approved estimates or edited by hand;
- the **Measurement Book** (MB): executed quantities measured against the BOQ and verified by a second person;
- **site inspections** with findings that are followed up until closed.

This replaces the v1 Work Estimate, Material Estimate and Project Visit screens. The v1 estimates and visits are added to the legacy seeder (§9).

Work orders (07), running bills (06 / 07) and the GL (08) do not exist yet. This module builds everything that does not need them and leaves hooks for them (E1, E2), as Projects did.

## 2. Decisions

| # | Decision |
|---|---|
| E1 | **Scope.** All of docs/05 except: vendor-direction MB entries and ES-BR-06 (they need work orders, 07); selecting MB entries into running bills, the BILLED move and the `RunningBillApproved` / `RunningBillCancelled` listeners (06 / 07); the Committed and Actual columns of the budget and the actual part of ES-AC-07 (07 / 08); the "Derive materials" helper (docs/05 open question 2); bank-specific BLE / UP print layouts (open question 1); and the docs/05 §10 registers and aging reports, which go to doc 10. Prints of the estimate and the inspection report are in scope. |
| E2 | **Hooks for later modules.** `measurement_entries.direction` is stored (lookup `mb_directions`: CUSTOMER, VENDOR), but the form only offers CUSTOMER. `work_order_id`, `work_order_item_id`, `running_bill_line_id`, `site_inspections.contractor_vendor_id` and `cost_categories.default_account_id` are nullable BIGINT columns with no FK and do not appear on any form. `MeasurementEntry::isBillable()` reads the status flag. `BudgetCostSources` is a registry like Projects' `ProjectCompletionChecks`: each source returns committed and actual amounts per cost category for a project. Nothing is registered yet, so both columns show 0 and a "from Purchases / Accounting" hint. |
| E3 | **One module, two permission prefixes.** The code lives in `app/Modules/Estimation` (the module list in `.ai/rules/modules.md`). Its `permissions.php` declares both `estimation.*` and `site.*` (docs/05 §2), which `PermissionManifest` already supports. Livewire is split by area: `Estimates\*`, `Budget\*`, `Mb\*`, `Inspections\*`, `Findings\*`. |
| E4 | **Data scope.** Estimates, budget lines, MB entries, inspections and findings are visible when their project is visible (`Project::scopeVisibleTo`, Projects P7) **and** the user holds the matching view permission. Each model gets `scopeVisibleTo($user)`, built on `whereHas('project', visibleTo)`. Management and accountants see all projects, PMs and engineers see their own, and sales see their customers' projects, which is the docs/05 §2 matrix. Writing also needs the project visible, so an engineer creates estimates, MB entries and inspections only on their own projects. |
| E5 | **Estimate numbers and revisions.** `NumberSequenceService::next('estimate')` gives the root number (`EST-{yy}-{seq:4}`, already seeded). A revision keeps the root number with `-R{n}` appended (`EST-27-0012-R1`), so `estimate_number` stays unique and the family reads as one. Routes use `{estimate:estimate_number}`. `root_estimate_id` is null on revision 0 and points to revision 0 on later revisions; `revised_from_id` points to the previous one. Kind, project and root never change after create. |
| E6 | **Lines and quantity** (ES-BR-03, ES-AC-01). Each work line stores its own `measurement_formula` (the Catalog `MeasurementFormula` enum), copied from the work item when one is picked and editable when not. `QuantityCalculator::quantity(formula, nos, l, w, h)` multiplies the factors the formula names (an empty factor counts as 1, except a required length on `nos_l*` formulas). It rounds half-up to 4 decimal places with brick/math and returns the typed quantity for `manual`. A `deduction` line stores a positive quantity and a negative amount. A negative quantity is refused (ES-BR-03). `amount = round(quantity × rate, 2)` (half-up; 0 when the rate is empty). `origin_line_id` links a line to the line it was copied from, through revisions, so the MB and the compare screen can follow a BOQ item across revisions. |
| E7 | **Totals** (`EstimateTotals`). BOQ, BLE and UP_SHEET: subtotal = Σ work line amounts. MATERIAL: subtotal = Σ material line amounts. Material lines on a BOQ are a statement and do not add to the total. Then overhead = subtotal × overhead %, profit = (subtotal + overhead) × profit %, VAT = (subtotal + overhead + profit) × VAT %, total = the sum. Each is rounded to 2 places, and `overhead_amount`, `profit_amount` and `vat_amount` are stored for the print. Totals are recalculated in the same transaction as every line save. Material lines: `total_qty = round(estimated_qty × (1 + wastage % / 100), 4)` and `amount = round(total_qty × rate, 2)`. |
| E8 | **Saving** (`SaveEstimate`). One action saves the header, the sections, the work lines and the material lines together; the lines are replaced and keep their ids when they are posted back. It needs `estimation.estimates.create` (new) or `update` (existing), the project visible, and the estimate in DRAFT or REJECTED (ES-BR-01). Saving a REJECTED estimate moves it back to DRAFT. Line numbers default to `{section position}.{line position:02}`, and a typed line number is kept. A work line needs a unit and a description. A material line needs either a catalog material or a free-text name (§3, v1 has rebar and other materials that are not in the catalog), plus a unit. Site address defaults to the project's site address. Prepared by defaults to the actor's employee. "Copy lines from another estimate" (any estimate the user can see) appends that estimate's sections and lines to the open editor before saving. |
| E9 | **Workflow** (docs/05 §6.1, ES-BR-01, 02, 04). `SubmitEstimate`: DRAFT → SUBMITTED, needs at least one line and `submit`. `ApproveEstimate`: SUBMITTED → APPROVED, needs `approve`. A user who also holds `projects.projects.view_all` (management) may approve any estimate. Anyone else must be the project's PM, and the total must be ≤ `estimation.pm_approval_limit` (ES-BR-04, the message names the limit). On approval, the root's earlier APPROVED revision becomes SUPERSEDED (ES-BR-02), `EstimateApproved` fires, and the budget is rebuilt when E11 applies. `RejectEstimate`: SUBMITTED → REJECTED with a note (`rejection_note`). Every move writes `estimate_status_histories` (status, note, user, time). |
| E10 | **Revise** (ES-AC-02). `ReviseEstimate` works on the latest revision of a root when it is APPROVED. It needs `revise` and a purpose, and refuses when the root already has an open DRAFT, SUBMITTED or REJECTED revision. The new DRAFT copies the header, sections, work lines and material lines, sets `revision_no + 1`, `root_estimate_id`, `revised_from_id`, `revision_purpose` and `estimate_date` = today, and sets `origin_line_id` on each line. The approved revision stays APPROVED until the new one is approved. |
| E11 | **Budget** (docs/05 §3.6, §6.1, ES-BR-05). `BuildBudgetFromEstimate` runs on approval when `estimation.auto_budget_from_approved_estimate` is on and the kind is BOQ or MATERIAL; a "Create budget from estimate" button runs it by hand. It deletes the project's budget lines whose source is any revision of the same root, then adds lines from the approved estimate: one line per cost category for BOQ work lines (lines without a category go to OTHER; deductions net out; a category whose net is ≤ 0 is skipped), and one MATERIAL line per material line (material or name, total qty, unit, amount). `SaveBudget` saves the hand-made lines (no source estimate) as a grid with category, work item, material, description, qty, unit and amount ≥ 0. Estimate-built lines are read-only on the grid. Every change that alters the project's budget total writes `project_budget_revisions` (revision no, reason, old and new total, approved by = the actor, approved at = now). The first revision may have no reason; every later hand change needs one ("Estimate {number} approved" is filled in for builds). Managed with `estimation.budget.manage` on visible projects; a PM manages only the projects they manage. |
| E12 | **Budget view** (docs/05 §5.4, ES-AC-07 budget part). `BudgetSummary::for(Project)` returns one row per cost category with Budget, Committed, Actual, Remaining (budget − committed − actual) and Variance % ((actual − budget) / budget; empty when the budget is 0), plus totals. Committed and Actual come from `BudgetCostSources` (E2). Rows drill down to the budget lines and the source estimate. |
| E13 | **Measurement Book** (docs/05 §3.7, §6.2, ES-BR-07–10, 13). `RecordMeasurement` / `UpdateMeasurement`: number `NumberSequenceService::next('mb_entry')` (`MB-{yy}-{seq:5}`, already seeded), direction CUSTOMER, measured on ≤ today, measured by = an assignable employee (default the actor's). The BOQ line is optional and must be a work line of the project's current APPROVED revision; it fills the item, description, unit and rate. Quantity comes from the formula (the BOQ line's, else the work item's, else `manual`). Rate defaults to the BOQ line rate, else the work item's standard rate (ES-BR-07; docs/05's "contract service rate" does not apply because services are not work items). A rate different from the default needs `site.mb.edit_rate` (management, PM). **Limit** (ES-BR-08, ES-AC-04): cumulative = Σ quantity of the non-rejected entries on lines with the same origin, this one included. Up to the BOQ quantity it is fine. Up to BOQ × (1 + `site.mb_allow_exceed_boq_pct` / 100) the action returns a warning and saves. Above that it is refused with BOQ quantity, previously measured, this entry and cumulative %. `achievement_pct` = cumulative / BOQ × 100. Project status must be open (ES-BR-13). Entries are edited only while RECORDED or REJECTED; editing a REJECTED entry moves it back to RECORDED. |
| E14 | **MB verification** (ES-BR-09, 10, ES-AC-05). With `site.mb_requires_verification` on, new entries are RECORDED, and `VerifyMeasurements` (bulk, `site.mb.verify`) moves RECORDED → VERIFIED, refusing entries where the actor's employee is the measurer or the actor created the entry (maker-checker). With the setting off, a new entry is saved as VERIFIED by its creator. `RejectMeasurements` (RECORDED → REJECTED, reason). `UnverifyMeasurement` moves VERIFIED → RECORDED while `running_bill_line_id` is null. VERIFIED fires `MeasurementVerified` (E2). `DeleteMeasurement` (soft) only for RECORDED or REJECTED. A PM verifies only on projects they manage. Management (with `projects.projects.view_all`) verifies on any project. |
| E15 | **Inspections** (docs/05 §3.8, §6.3, ES-BR-11–13, ES-AC-06). `SaveInspection` saves the header and the findings repeater while DRAFT: number `site_inspection` sequence (`SI-{yy}-{seq:4}`), inspection date ≤ today, end time after start time when both are set, project engineer from the project team (default the actor's employee), contractor name, permittee, site address (default the project's), field office phone (normalised when it is a valid mobile, kept as typed otherwise), weather, workers on site, progress summary, client representative. Project open, or COMPLETED for HANDOVER / SNAG types (ES-BR-13). `SubmitInspection` DRAFT → SUBMITTED and sends `site.findings.assigned` for each finding with a responsible employee. After submit, `AddFinding` adds findings, and the header stays editable for `site.inspections.update` holders until CLOSED. `CloseInspection` (PM or management) refuses while HIGH / CRITICAL findings are open (ES-BR-12). When the last open finding of a SUBMITTED inspection closes, the inspection closes itself. |
| E16 | **Findings** (docs/05 §3.9, §5.9, ES-BR-11). Each finding has a location, description*, finding detail, category, severity* (default MEDIUM), action required, responsible, due date and found by (§3). `responsible_type` is `employee` (an assignable employee), `customer` (the project's customer) or `contractor` (the inspection's contractor name); `vendor` comes with 07. HIGH and CRITICAL need a responsible and a due date (ES-BR-11). `ChangeFindingStatus`: OPEN → IN_PROGRESS → RESOLVED or ACCEPTED, with OPEN → RESOLVED / ACCEPTED allowed directly, and a closed finding reopened to OPEN by a PM. A closed status needs a closure note and sets `closed_on` / `closed_by`. Who: `site.inspections.close_finding` and either PM / management, or the responsible employee. Photos are attachments on the finding (`Collaborative`, document type `site_photo`). The close sheet takes an "after" photo, stored with the title "After: …". |
| E17 | **Notifications** (`PreferenceNotification`, `config/notifications.php`, docs/05 §9). `estimation.estimates.submitted` goes to the approvers: the project's PM when the total is within the limit, else active users with the management role. `estimation.estimates.approved` and `estimation.estimates.rejected` go to the preparer's user. `site.mb.awaiting_verification` is a daily digest to each PM with the count of RECORDED entries older than one day. `site.findings.assigned` goes to the responsible employee's user. `site.findings.overdue` goes to the responsible employee's user and the PM once per overdue spell (`overdue_notified_at`, cleared by any status change), gated by `site.finding_overdue_notify`. Users without a login are skipped. |
| E18 | **Jobs** (daily, `routes/console.php`): `SendMbVerificationDigest` (guarded by a cache key per date), `NotifyOverdueFindings`. |
| E19 | **Compare** (docs/05 §5.3, ES-AC-03). `EstimateComparison::between(a, b)` takes two revisions of the same root. It pairs lines by origin (else by line number), marks each pair added, removed, changed (qty, rate or amount differs) or same, gives the qty, rate and amount differences, and the subtotal and total differences. Material lines are compared the same way on their own tab. |
| E20 | **Prints and exports** (docs/05 §5.2, §10, ES-AC-06). From `estimates/{estimate}/print?layout=`: `measurement` (sections, lines with nos / L / W / H / qty), `abstract` (abstract of cost: lines with qty, unit, rate, amount, section subtotals and the overhead / profit / VAT footer), `materials` (material statement). The inspection report prints from `site/inspections/{inspection}/print` with the v1 Project Visit fields and the findings with their photos. All use `x-print.letterhead` and the browser's print to PDF, as the project sheet does. Excel: the estimates, MB and inspection lists export the filtered rows (`estimation.estimates.export`; the MB and inspection exports need only view). An estimate exports its lines, and "Import lines" reads the same layout back into a DRAFT (unknown work item codes and units are reported by row and nothing is imported). |
| E21 | **Project page** (docs/04 §5.3 tabs left for 05). The project page gets two tabs: **Estimates & Budget** (with `estimation.estimates.view`: the project's estimates, latest revision only, New estimate, then the budget summary of E12 with `estimation.budget.view`) and **Site** (with `site.mb.view` or `site.inspections.view`: recent MB entries with the cumulative progress per BOQ line, and inspections with open findings, plus New MB entry and New inspection). The key figure cards gain "Budget" and "Open findings" when the user can see them. |
| E22 | **Completion checks** (Projects P2). Estimation registers two `ProjectCompletionCheck`s: open HIGH / CRITICAL findings, and MB entries still RECORDED. Both block COMPLETED unless overridden, like open tasks. |
| E23 | **Lookups.** On Master Data under `estimation.master_data` (view / create / update / deactivate), in an "Estimation setup" nav tree: cost categories, inspection types, finding categories and finding severities (`color`). Seeded and read-only, because their codes drive behaviour: `estimate_kinds`, `estimate_statuses`, `mb_statuses`, `mb_directions`, `inspection_statuses`, `finding_statuses`. `cost_categories.default_account_id` stays off the form until 08. |
| E24 | **Deletes** (soft). An estimate is deleted with `estimation.estimates.delete` while DRAFT or REJECTED. Revision 0 can be deleted only when no later revision exists. MB entries follow E14. An inspection can be deleted only while DRAFT (`site.inspections.delete`). Budget lines from an estimate are removed only by rebuilding. |

## 3. Data model

Migrations are in `database/migrations/estimation/` (loaded by `EstimationServiceProvider`) and models in `app/Modules/Estimation/Models`. All models are Auditable. FKs are `ON DELETE RESTRICT` except where noted. Morph aliases: `estimate_kind`, `estimate_status`, `cost_category`, `mb_status`, `mb_direction`, `inspection_type`, `inspection_status`, `finding_category`, `finding_severity`, `finding_status`, `estimate`, `estimate_line`, `estimate_material_line`, `project_budget_line`, `measurement_entry`, `site_inspection`, `site_inspection_finding`.

- **Lookups** ([LOOKUP] + the extras of docs/05 §3.1): `estimate_kinds` (+ `has_work_lines`, `has_material_lines`, `is_customer_facing` booleans), `estimate_statuses` (`is_locked`, `is_approved`), `cost_categories` (`default_account_id` without FK), `mb_statuses` (`is_billable`, `is_locked`), `mb_directions`, `inspection_types` (+ `allowed_after_completion`), `inspection_statuses`, `finding_categories`, `finding_severities` (+ `requires_follow_up`), `finding_statuses` (`is_closed`).
- **`estimates`**: per §3.2 plus `overhead_amount`, `profit_amount`, `vat_amount` DECIMAL(18,2), `rejection_note` TEXT, `legacy_ref` VARCHAR(40) nullable unique (§9). `prepared_by` / `checked_by` FK employees, `approved_by` FK users, `root_estimate_id` / `revised_from_id` FK estimates. Index (`project_id`, `root_estimate_id`). `[AUDIT] [SOFT]`.
- **`estimate_status_histories`**: `[STD]`, `estimate_id` (cascade), `estimate_status_id`, `note`, `changed_by`, `changed_at`.
- **`estimate_sections`**: per §3.3, cascade on estimate delete.
- **`estimate_lines`**: per §3.4 plus `measurement_formula` VARCHAR(20) and `origin_line_id` FK estimate_lines (null on delete); `estimate_section_id` null on delete; cascade on estimate delete.
- **`estimate_material_lines`**: per §3.5 with `material_id` **nullable** plus `material_name` VARCHAR(200) nullable (one of the two is required) and `origin_line_id`.
- **`project_budget_lines`**: per §3.6, `material_name` like above. **`project_budget_revisions`**: per §3.6 plus `[STD]`.
- **`measurement_entries`**: per §3.7 with `mb_direction_id` FK in place of the `direction` string, `work_order_id`, `work_order_item_id` and `running_bill_line_id` without FK (E2), and `measurement_formula`. Indexes per §3.7. `[AUDIT] [SOFT]`.
- **`site_inspections`**: per §3.8 plus `site_address` VARCHAR(255), `project_engineer_name` VARCHAR(150) (v1 free text when no employee matches), `weather`, `legacy_visit_ref` INT nullable unique; `contractor_vendor_id` without FK. `[AUDIT] [SOFT]`.
- **`site_inspection_findings`**: per §3.9 plus `found_by_name` VARCHAR(150) (v1 "Finding by"), `overdue_notified_at`, `legacy_detail_ref` INT nullable unique, `sort_order`, `[AUDIT]`; cascade on inspection delete; `closed_by` FK users.

`Estimate`, `MeasurementEntry`, `SiteInspection` and `SiteInspectionFinding` implement `Collaborative` (attachments; notes on Estimate) with `isViewableBy` from their policies.

### 3.1 Seeds (`database/seeders/Estimation/`, idempotent, by `code`)

- All lookups with the rows of docs/05 §3.1. `estimate_kinds`: BOQ (work + material lines), MATERIAL (material lines), BLE and UP_SHEET (work lines, customer facing). `mb_statuses`: RECORDED, VERIFIED (billable), BILLED (locked), REJECTED. `mb_directions`: CUSTOMER, VENDOR. `inspection_types`: HANDOVER and SNAG are allowed after completion. `finding_severities`: HIGH and CRITICAL require follow-up, with colours LOW neutral, MEDIUM info, HIGH warning, CRITICAL danger. `finding_categories` per docs/05.
- Settings group `estimation`: `pm_approval_limit` (int, 5,000,000), `auto_budget_from_approved_estimate` (bool, true). Group `site`: `mb_requires_verification` (bool, true), `mb_allow_exceed_boq_pct` (int, 10), `finding_overdue_notify` (bool, true).
- Permissions and grants (§4.1). The number sequences `estimate`, `mb_entry` and `site_inspection` already exist.

## 4. Architecture

### 4.1 Permissions (`app/Modules/Estimation/permissions.php`)

```text
estimation.estimates.view | create | update | submit | approve | revise | delete | print | export
estimation.budget.view | manage
estimation.master_data.view | create | update | deactivate
site.mb.view | create | update | verify | edit_rate | delete
site.inspections.view | create | update | close_finding | delete | print
```

| Role | Grants |
|---|---|
| super_admin | everything (Gate::before) |
| management | `estimation.*`, `site.*` |
| project_manager | estimates view, create, update, submit, approve, revise, delete, print, export; budget view, manage; master_data view; mb view, create, update, verify, edit_rate, delete; inspections view, create, update, close_finding, delete, print |
| engineer | estimates view, create, update, submit, revise, print; budget view; mb view, create, update, delete; inspections view, create, update, close_finding, print |
| accountant, finance_manager | estimates view, print, export; budget view; mb view; inspections view |
| sales_manager, sales_executive | estimates view, print |
| viewer | already `*.view` (Foundation) |

The scope comes from the project (E4), so there are no `view_own` / `view_all` variants. `edit_rate` is new (E13).

### 4.2 Placement

- `app/Modules/Estimation/` with `EstimationServiceProvider` (migrations, morph map, Livewire location, policies, listeners, the `BudgetCostSources` singleton, project completion checks), registered in `bootstrap/providers.php` after Projects.
- Routes `routes/modules/estimation.php`, `app` middleware, `can:` per route:
  - `estimates` (`estimation.estimates.index`), `estimates/create?project=&kind=`, `estimates/{estimate:estimate_number}`, `/edit`, `/print`, `/export`, `estimates/{estimate}/compare?with=`
  - `projects/{project:project_number}/budget` (`estimation.budget.show`)
  - `site/mb` (`site.mb.index`), `site/mb/create?project=`, `site/mb/{entry:mb_number}`, `/edit`
  - `site/inspections` (`site.inspections.index`), `/create?project=`, `/{inspection:inspection_number}`, `/edit`, `/print`
  - `site/findings` (`site.findings.index`, board)
- Livewire (class-based), views in `resources/views/livewire/estimation/...`: `Estimates\Index`, `Estimates\Editor`, `Estimates\Show`, `Estimates\Compare`, `Budget\Show`, `Budget\ProjectTab`, `Mb\Index`, `Mb\Form`, `Mb\Show`, `Inspections\Index`, `Inspections\Form`, `Inspections\Show`, `Findings\Board`, `Site\ProjectTab`.
- Actions: `SaveEstimate`, `SubmitEstimate`, `ApproveEstimate`, `RejectEstimate`, `ReviseEstimate`, `DeleteEstimate`, `ImportEstimateLines`, `BuildBudgetFromEstimate`, `SaveBudget`, `RecordMeasurement`, `UpdateMeasurement`, `VerifyMeasurements`, `RejectMeasurements`, `UnverifyMeasurement`, `DeleteMeasurement`, `SaveInspection`, `SubmitInspection`, `CloseInspection`, `DeleteInspection`, `AddFinding`, `ChangeFindingStatus`. Each one authorizes against the actor, validates (throwing `ValidationException`) and writes in `DB::transaction()`. Shared rules live in `Concerns\ValidatesEstimateInput` and `Concerns\ValidatesInspectionInput`.
- Services: `QuantityCalculator`, `EstimateTotals`, `EstimateComparison`, `MeasurementLimits` (cumulative, limit, achievement), `BudgetSummary`, `BudgetCostSources` + `Contracts\BudgetCostSource`, `CompletionChecks\OpenFindingsCheck`, `CompletionChecks\UnverifiedMeasurementsCheck`.
- Events: `EstimateSubmitted`, `EstimateApproved`, `EstimateRejected`, `MeasurementVerified`, `FindingAssigned`. Listener: `BuildBudgetOnApproval`.
- Exports: `EstimatesExport`, `EstimateLinesExport`, `MeasurementsExport`, `InspectionsExport`. Import: `EstimateLinesImport` (`app/Support/Imports` pattern of Catalog).

### 4.3 Navigation

The empty **Estimation & Site** group gets: Estimates (`estimation.estimates.view`), Measurement book (`site.mb.view`), Site inspections (`site.inspections.view`, mobile primary), Findings (`site.inspections.view`), and "Master data" (tree) with the four lookups (`estimation.master_data.view`). The detail modal list gains the estimate show, MB create / edit / show and inspection show routes. The editor and the inspection form are full pages.

## 5. Screens

All screens follow doc 00 §7.6 on mobile: a fixed top bar with back and title; list rows instead of tables; bottom sheets for filters, row actions, status changes and pickers; sticky action bars on forms; a FAB for create; 44 px tap targets; `wire:navigate` everywhere; `x-lookup-select` / `x-employee-select` for FKs.

### 5.1 Estimates — `estimates`
Desktop columns: Estimate #, Kind, Project, Title, Date, Rev., Status, Prepared by, Total. Filters: kind, project, status, date range. A "Latest revision only" switch, on by default. Search: number, title, project. Bulk: Export. Mobile rows show the number + rev, title, project, status badge and total; the filter sheet; FAB → new estimate (project picker first when no `?project=`).

### 5.2 Estimate editor — `estimates/create`, `estimates/{estimate}/edit`
- Header card: kind* (fixed after create), project*, title*, site address, date*, prepared by*, checked by, overhead %, profit %, VAT %, notes.
- **Work tab** (kinds with work lines). Desktop is a spreadsheet grid grouped by section, with the docs/05 §5.2 columns plus a formula column. Picking a work item (search) fills description, unit, rate and formula. Quantity is live (read-only unless the formula is `manual`); deduction rows show in red. There are section subtotals and a footer with subtotal, overhead, profit, VAT and total. Keyboard (Alpine): Tab moves across, Enter adds a row, Ctrl+D duplicates, Ctrl+↑ / ↓ moves. Buttons: add section, add line, copy lines from…, import Excel. On mobile, lines are cards per section showing line no., description, the "2 × 20 × 0.833 × 10" factors, qty with unit, and amount. Tapping a card opens a full-height bottom sheet with the line fields, using `inputmode="decimal"` inputs. An "Add line" button sits at the end of each section.
- **Materials tab**: material (catalog search, or a typed name), unit, est. qty, wastage %, total qty (live), rate, amount, purpose.
- Sticky bar: Save draft · Submit (on mobile, Submit sits in the ⋮ sheet).

### 5.3 Estimate detail — `estimates/{estimate}`
Header: number, rev badge, kind, project link, status badge, total. Actions by status and permission: Edit, Submit, Approve, Reject (note sheet), Revise (purpose sheet), Create budget, Compare with…, Print (layout sheet), Export, Delete. A revision strip lists every revision with its status. The tabs (segmented control) are Lines (read-only grid / cards), Materials, Revisions & history (status history), Documents and Notes. Key figure cards: Subtotal, Total, Lines, and the previous revision's total with the difference.

### 5.4 Compare — `estimates/{estimate}/compare?with=`
Two revision pickers. Rows are coloured by state (added, removed, changed), with qty / rate / amount for both sides and the difference, plus a totals card. On mobile there is one card per changed line, and a switch shows the unchanged lines.

### 5.5 Budget — the project tab and `projects/{project}/budget`
Category rows: Budget · Committed · Actual · Remaining · Variance % (coloured: within budget, over by ≤ 10 %, over by > 10 %). Each expands to its lines and their source estimate links. "Edit budget" opens the hand-made lines grid (a sheet on mobile) with a reason field that is required after the first revision. A revisions list shows the number, reason, old → new total, who and when.

### 5.6 Measurement Book — `site/mb`
Desktop columns per docs/05 §5.5 (the work order / vendor and running bill columns appear with 06 / 07). Filters: project, status, date range, measured by, BOQ line search. Preset chips: Awaiting verification · Mine · Verified · Rejected. Bulk: Verify, Reject (reason sheet), Export. Mobile rows show the MB number, item description, project, qty + unit, and a status badge. Selection mode for bulk verify; FAB → new entry.

### 5.7 MB entry form — `site/mb/create?project=`, `/edit`
The fields of docs/05 §5.6 for the customer direction. A BOQ line picker (from the current approved revision, showing its line no., description and qty) fills the item, unit, rate and formula. A progress card shows BOQ qty, previously measured, this entry, cumulative and %, with a warning or blocked state (E13). Rate is read-only without `edit_rate`. Photos are attachments after the first save. Sticky Save bar.

### 5.8 MB detail — `site/mb/{entry}`
Fields, progress card, status and verification info, attachments, history; Verify / Reject / Unverify / Edit / Delete as allowed.

### 5.9 Site inspections — `site/inspections`
Columns (v1-compatible): Inspection #, Date, Project, Project engineer, Contractor, Permittee, Type, Findings (open / total), Status. Filters: project, type, status, date range, engineer, has open findings. Mobile rows show the number, project, date, type, an open-findings count badge and a status badge; FAB → new inspection.

### 5.10 Inspection form — `site/inspections/create?project=`, `/edit` (phone-first)
Header fields (E15) in one column. The findings repeater shows each finding as a card with location, description, finding, category, severity, action, responsible (type + picker), due, and found by. Photos are added with a camera input (`accept="image/*" capture="environment"`) once the inspection is saved. Sticky bar: Save · Submit.

### 5.11 Inspection detail — `site/inspections/{inspection}`
Header with status; key figures (findings open / total, HIGH+ open, days since the inspection); finding cards with severity badges, photos and Change status (a sheet with the status, closure note and "after" photo); Add finding; Close inspection; Print report; Documents; History.

### 5.12 Findings board — `site/findings`
Kanban by finding status (`wire:sort` calling `ChangeFindingStatus`; a refused move snaps back with a toast; a closing move opens the closure sheet first). Filters: project, severity, responsible, overdue. On mobile there are status chips and a list of cards.

## 6. Error handling

- Action rule failures throw `ValidationException`. They show inline, or as a toast in sheets and on the board.
- 403 comes from route `can:` middleware, policies and `authorize()` in every Livewire action. Nav items and buttons are hidden without the permission.
- These are refused with a message: editing a locked estimate; submitting without lines; approving above the PM limit (with the limit); a second open revision; a negative quantity; a material line with neither material nor name; a budget amount < 0; a budget change without a reason after the first revision; an MB entry on a closed project, on a BOQ line that is not on the current approved revision, above the limit (with the figures) or with a changed rate without `edit_rate`; verifying your own entry; unverifying a billed entry; HIGH / CRITICAL findings without a responsible or due date; closing an inspection with HIGH / CRITICAL findings open; closing a finding without a note; an Excel import with bad rows (listed by row).
- Jobs are idempotent (E17, E18).

## 7. Testing

Pest feature tests live under `tests/Feature/Estimation/` (in-memory SQLite).

| Area | Cases |
|---|---|
| Access | routes 403 / 200 by permission; nav items; the scope follows the project (an engineer sees estimates, MB and inspections of their own projects only; accountants see all; sales see their customers' projects); Livewire ids of another project's lines are refused |
| Seeders | idempotent; lookups and flags; settings; permissions and grants |
| Quantity / totals | every formula; ES-AC-01 (333.20 cft); deduction; manual; negative refused; overhead / profit / VAT chain; material total qty and amount |
| Estimates | numbers and `-R{n}`; save replaces lines and keeps ids; locked statuses refuse edits (ES-BR-01); rejected → draft on save; submit needs lines; approve by PM within the limit, refused above it, allowed for management (ES-BR-04); superseding (ES-BR-02); revise copies everything and sets origins; one open revision; reject note; history rows; delete rules; copy lines; import good and bad files |
| Budget | built on approval (ES-AC-02 rebuild); categories net deductions; manual lines; reason rule (ES-BR-05); revision rows; summary rows with a fake cost source (ES-AC-07 figures) |
| MB | number; BOQ line must be on the current approved revision; rate default and `edit_rate`; warn and block at the limit with figures (ES-AC-04); cumulative across revisions through origin; maker-checker (ES-AC-05); setting off saves VERIFIED; reject / unverify / delete rules; closed project refused (ES-BR-13); `MeasurementVerified` |
| Inspections | number; HIGH needs responsible and due (ES-BR-11); submit notifies; close refused with HIGH open (ES-BR-12); auto close; HANDOVER after completion; finding status moves and who may make them; overdue once per spell |
| Jobs | digest once a day; overdue findings |
| Projects | Estimates & Budget and Site tabs by permission; key figure cards; completion checks block COMPLETED |
| Screens | list filters and presets; editor saves (desktop payload); detail actions; compare (ES-AC-03); MB form progress card; inspection form with three findings and photos and its print (ES-AC-06); board move; exports; prints |
| Legacy | §9 importers: counts, mapping, idempotency |

No browser tests. When the build is done, the screens are listed for the user to check at 390×844 and at desktop width.

## 8. Out of scope

Everything in E1, plus material coefficients per work item, MB book page printing (docs/05 open question 3: the MB book and page are stored and shown, not printed as a book), offline inspection capture, and the estimate approval chains beyond the PM / management split.

## 9. Legacy seed — estimates and visits

These continue the legacy seed design (`2026-10-06-legacy-seed-design.md` §7). Three importers run after `ImportTasks`: `ImportWorkEstimates`, `ImportMaterialEstimates` and `ImportProjectVisits`. v1 `project_id` in these tables is the `tbl_project` row id, found through `projects.legacy_project_ref`. A row whose project was not imported is skipped and counted. Data: 4 work estimates (1 active, 1 line), 9 material estimates (7 active, 13 active lines), 466 visits (450 active, across 23 projects), and 556 visit details (542 active).

| # | Decision |
|---|---|
| L17 | **Work estimates.** Active rows only → BOQ estimates, number from the `estimate` sequence dated by `date`, title "Work estimate — {project name}" (v1 `work_name` holds the project id), site address from `address`, status APPROVED with `approved_at` = `AddTime` and no approver (v1 has no approval step), prepared by = the project's PM, else the first active employee. Lines: description, level, location, nos (0 → 1), L / W / H (0 → empty), unit by code or symbol (case-insensitive), else `nos`, quantity = v1 quantity with `quantity_is_manual` and formula `manual`, the v1 `measurement` text in remarks, no rate (amount 0). `legacy_ref` = `work_estimate:{id}`. No budget is built (events muted, L16). |
| L18 | **Material estimates.** Active rows only → MATERIAL estimates (same header rules, title "Material estimate — {project name}"). Lines: the v1 material id → the catalog material through a new `LegacyMap::materialFor` (v1 ids 3–10 → the eight materials `MaterialSeeder` seeds from v1, L4); id 0 → a catalog material whose normalised name equals `material_name`, else `material_name` as free text. Unit from the v1 unit text (code or symbol), else the material's unit, else `nos`. Estimated qty = the leading number of `total_estimated_qty`. When that is blank and `purpose_estimate` is a plain number, it is the qty and the purpose is empty (v1 row 27 has the two shifted). Otherwise the qty is 0 with the text kept in purpose. No rate. `legacy_ref` = `material_estimate:{id}`. |
| L19 | **Project visits → inspections.** Active rows → site inspections, number from the `site_inspection` sequence dated by `inspection_date`; a `0000-00-00` date → `AddTime`'s date. Type: `inspection_type_weekly` = 1 → WEEKLY, else EVENT (v1 "Precipitation Event" is the event box). Contractor name = `constructor_name`, permittee, site address = `location`, field office phone (normalised when valid, else kept trimmed to 30 characters), start / end time (`00:00:00` → empty), progress summary = `description`. Project engineer: `project_eng_name` kept in `project_engineer_name`, and `project_engineer_id` set when exactly one employee's normalised full name contains every word of the v1 name (titles removed, at least 5 letters). `created_at` = `AddTime`, created by = the project's creator, else the admin. `legacy_visit_ref` = v1 id. |
| L20 | **Visit details → findings.** Active details of imported visits. Location = `visit_location`; description = `visit_description`, else the location, else "Finding"; found by = `visit_finding` (the v1 "Finding by" field holds a name, not a finding); severity MEDIUM; no category. v1 "Regarding taking" sets the status: `yes` → RESOLVED (closed on the inspection date, note "Action taken (v1)"), `no_application` → ACCEPTED (note "Not applicable (v1)"), `no` → OPEN. Then the inspection is CLOSED when no finding is open, else SUBMITTED. `legacy_detail_ref` = v1 id. |
| L21 | **No events, idempotent.** As L3 and L16: an imported row (by its legacy ref) is skipped, never updated; model events are muted, so there are no audit rows, notifications or budget builds. |

## 10. Implementation deviations

Where the build differs from the sections above, the build is authoritative.

- §3: migrations are dated `2026_10_13_*` so they run after the Projects legacy-ref migration. `HasCodeLookup` moved from Projects to `app/Support/Lookups` and is shared.
- E6: the dimensions a formula names (other than nos) are required on save; an empty nos counts as 1. The editor's work item picker is a native select, not a search.
- E8: `SaveEstimate::linesOf()` gives the lines in the saved order, with ids for the editor and without them for "copy lines from".
- E11: a BOQ builds the budget from its work lines only; its material lines stay a statement, since they would count the same cost twice. The budget total is kept in `projects.budget_cost` through the Projects action `SetBudgetCost`.
- E13: a rate the entry already had may be kept on edit; when there is no default rate (no BOQ line, no work item rate) the typed rate is accepted. Warnings come back on `RecordMeasurement::$warnings` / `UpdateMeasurement::$warnings` and show as a flash message.
- E14: bulk verify and reject are all or none; any refused entry stops the batch with the reasons.
- E15, E16: findings of a DRAFT inspection cannot change status ("submit first"); `AddFinding` also works on drafts. Responsible employees are checked with the assignable-employee rule inside the findings validator.
- E17: the notification classes are `EstimateAwaitingApproval`, `EstimateDecided` (approved / rejected), `MeasurementsAwaitingVerification`, `FindingAssignedToYou` and `FindingOverdue`.
- E20: the Excel line import reads work lines only; material lines are typed in the editor.
- E21: the project tabs are `estimates` (Estimates & Budget) and `site`; the key figure cards Budget and Open findings show with `viewBudget` and `site.inspections.view`.
- §5.6: the MB list has a mobile selection mode (top-bar button) with a sticky Verify / Reject bar.
- §9 (L19, L20): v1 text "N/A", "NA" and "Not found" counts as empty. Legacy tests are `tests/Feature/Legacy/ImportEstimatesTest.php` and `ImportProjectVisitsTest.php`.

## 11. Screens to check (390×844 and desktop)

- Estimates: list `estimates`, editor `estimates/create?project=…` (desktop grid and the mobile line sheet), detail `estimates/{number}`, compare `estimates/{number}/compare`, prints (measurement, abstract, materials).
- Budget: `projects/{number}/budget` and the project page's Estimates & Budget tab.
- Measurement Book: list `site/mb` (presets, mobile selection mode), form `site/mb/create?project=…` (progress card), detail `site/mb/{number}`.
- Site inspections: list `site/inspections`, form `site/inspections/create?project=…`, detail `site/inspections/{number}` (status sheet, photo sheet, Add finding), report print.
- Findings board `site/findings` (drag on desktop, status chips on mobile).
- Project page: Site tab and the Budget / Open findings cards.
- Master Data: cost categories, inspection types, finding categories, finding severities.

