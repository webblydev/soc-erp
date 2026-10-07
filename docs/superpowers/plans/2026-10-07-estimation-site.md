# Estimation & Site Implementation Plan

> Steps use checkbox (`- [ ]`) syntax for tracking. Each task ends with its tests passing, Pint run and per-file commits.

**Goal:** Build the Estimation & Site module (docs/05 without the parts that need 06–08): estimates (BOQ and material) with sections, revisions, approval, compare, print and Excel; the project budget; the Measurement Book with verification and BOQ limits; site inspections and findings with the findings board; the project page tabs; and the v1 estimates and visits in the legacy seeder.

**Architecture:** A new `app/Modules/Estimation` module that follows the Projects structure. It has Auditable models; Action classes that authorize, validate and write in a transaction; class-based Livewire screens over BlatUI components; and lookups on the shared Master Data screen. Every record's scope comes from its project (`Project::scopeVisibleTo`). Later modules plug into `BudgetCostSources` (committed and actual costs), `MeasurementVerified` and the reserved work-order / running-bill columns.

**Tech Stack:** Laravel 13 / PHP 8.4, Livewire 4 (class components, `Route::livewire`), BlatUI (`x-ui.*`), Tailwind v4, Pest 4 on in-memory SQLite, brick/money and brick/math, maatwebsite/excel.

**Spec:** `docs/superpowers/specs/2026-10-07-estimation-site-design.md` (decisions E1–E24, L17–L21). Read it together with docs/05 before each task.

## Global Constraints

- Follow the rules in `AGENTS.md` and `.ai/rules/modules.md`:
  - module code goes in `app/Modules/Estimation/{Models,Livewire,Policies,Services,Actions,Events,Listeners,Jobs,Notifications,Concerns,Contracts,Exports,Imports}`;
  - migrations go in `database/migrations/estimation/`, seeders in `database/seeders/Estimation/`, and routes in `routes/modules/estimation.php` (required from `routes/web.php`);
  - business logic goes only in Actions, and every write Action uses `DB::transaction()`.
- Build UI only with BlatUI `x-ui.*` components and the existing `x-shell.*` / `x-print.*` components, in light mode only, under the strict mobile rules.
- Route keys: `{estimate:estimate_number}`, `{entry:mb_number}`, `{inspection:inspection_number}`, `{project:project_number}`. Findings and budget lines use ids.
- Use `App\Support\Money` / brick for money and brick/math for quantities. Amounts are `DECIMAL(18,2)`; rates and quantities are `DECIMAL(18,4)`; percents are `DECIMAL(7,4)`.
- Commits: one commit per file-level change group, a mid-length message, and no AI co-author line (AGENTS.md).
- Run `vendor/bin/pint --dirty --format agent` before each commit that touches PHP.
- Tests: `php artisan test --compact tests/Feature/Estimation/<File>.php`. Never run browser tests.
- No new composer or npm dependencies.

## Review Focus

1. **Scope leaks.** An engineer must not reach another project's estimates, budget, MB entries, inspections or findings by URL, by Livewire ids (line, section, finding, MB entry in a bulk selection) or through the findings board. Pinned in `EstimationAccessTest`.
2. **Locked documents.** No action may change the lines or totals of a SUBMITTED, APPROVED or SUPERSEDED estimate, or the quantity, rate or status of a BILLED MB entry. Pinned in `EstimateWorkflowTest` and `MeasurementTest`.
3. **One approved revision.** Approving a revision always supersedes the earlier approved one and rebuilds only that root's budget lines. Pinned in `EstimateWorkflowTest` and `BudgetTest`.
4. **MB limits and maker-checker.** The cumulative quantity follows the BOQ item across revisions, and a measurer can never verify their own entry while the setting is on. Pinned in `MeasurementTest`.

---

### Task 1: Module scaffold, schema, models and factories

**Files:**
- `EstimationServiceProvider` and `permissions.php`.
- Migrations: `2026_10_12_100000_create_estimation_lookup_tables`, `…100100_create_estimates_tables` (estimates, status histories, sections, lines, material lines), `…100200_create_budget_tables`, `…100300_create_measurement_entries_table`, `…100400_create_site_inspections_tables`. Name every composite index explicitly; the MySQL 64-character limit bit Projects.
- All models; factories in `database/factories/Estimation/`; `bootstrap/providers.php`; the `routes/web.php` require.
- Test: `EstimationSchemaTest`.

**Produces:**
- `Estimate` with route key `estimate_number`; relations to project, kind, status, sections, lines, material lines, revisions, root and previousRevision; `isLocked()`, `isLatestRevision()`, `hasWorkLines()`, `hasMaterialLines()`.
- `EstimateLine`, `EstimateMaterialLine` (`displayName()` returns the material or the free text), `ProjectBudgetLine`, `ProjectBudgetRevision`.
- `MeasurementEntry` with route key `mb_number`, `isBillable()` and `isLocked()`.
- `SiteInspection` with route key `inspection_number`; `SiteInspectionFinding` with `isClosed()`, `isOverdue()` and `requiresFollowUp()`.
- Lookups with `idFor()` and code constants (reuse Projects' `HasCodeLookup` by moving it to `app/Support/Lookups` when it is generic, else copy the pattern).
- `Project` relations `estimates()`, `budgetLines()`, `measurementEntries()`, `siteInspections()`.
- Factories: `Estimate::factory()->onProject()`, `->ofKind(code)`, `->withStatus(code)`, `->approved()`, `->withLines(n)`; `MeasurementEntry::factory()->forLine(EstimateLine)`; `SiteInspection::factory()->onProject()`; `SiteInspectionFinding::factory()->severity(code)`.

- [ ] Write the schema test (relations, route keys, nullable hook columns without FKs, the `material_id` / `material_name` pair, morph aliases); see it fail, then pass.

### Task 2: Seeders

**Files:** `EstimationLookupSeeder`, `EstimationSettingSeeder` (groups `estimation` and `site`), `EstimationSeeder`; `DatabaseSeeder`; a `seedEstimation()` helper in `tests/Pest.php` (it calls `seedProjects()` first). Test: `EstimationSeederTest` (idempotent; lookups and flags of spec §3.1; the five settings; the permissions and grants of spec §4.1, including `site.mb.edit_rate`).

### Task 3: Master Data, navigation, notification keys

**Files:**
- `config/lookups.php`: the four editable lookups (E23), `finding_severities.color`, `inspection_types.allowed_after_completion` bool, `finding_severities.requires_follow_up` bool (seeder-set and not editable).
- `config/navigation.php`: the Estimation & Site group (§4.3) and the detail modal routes.
- `config/notifications.php`: the six keys of E17.

Test: `EstimationMasterDataTest` (gated by `estimation.master_data`; flags not editable; `default_account_id` absent).

### Task 4: Access — policies and scopes

**Files:**
- `Services\EstimationAccess` (wraps `Project::scopeVisibleTo` and decides "PM of the project or management").
- Policies `EstimatePolicy` (view, create, update, submit, approve, revise, delete, print, export), `BudgetPolicy` (view, manage on a Project), `MeasurementEntryPolicy` (view, create, update, verify, editRate, delete), `SiteInspectionPolicy` (view, create, update, close, delete, print, closeFinding on a finding).
- `scopeVisibleTo` on `Estimate`, `MeasurementEntry`, `SiteInspection`, `SiteInspectionFinding`.

Test: `EstimationAccessTest` (E4: engineer own projects only; accountant all; sales own customers'; PM vs management for approve, verify and close; Review Focus 1 for the scopes).

### Task 5: Quantities, totals and saving estimates

**Files:**
- `Services\QuantityCalculator`, `Services\EstimateTotals`, `Concerns\ValidatesEstimateInput`.
- Action `SaveEstimate`: create with the number (E5); header, sections, work lines and material lines replaced with their ids kept; line number defaults; totals recalculated (E6–E8); REJECTED → DRAFT on save; `copyLinesFrom` input appends another visible estimate's lines.

Tests: `QuantityCalculatorTest` (every formula, ES-AC-01 = 333.2000, deduction, manual, rounding), `EstimateTotalsTest` (BOQ vs MATERIAL, statement material lines on BOQ, the overhead / profit / VAT chain), `SaveEstimateTest` (numbers, ids kept, locked refused, validation of lines, copy lines, default site address and preparer).

### Task 6: Estimate workflow

**Files:**
- Actions `SubmitEstimate`, `ApproveEstimate`, `RejectEstimate`, `ReviseEstimate` (number `-R{n}`, deep copy, `origin_line_id`), `DeleteEstimate`.
- Events `EstimateSubmitted`, `EstimateApproved`, `EstimateRejected`.
- Notifications `EstimateSubmitted`, `EstimateApproved`, `EstimateRejected` (approver routing per E17).

Test: `EstimateWorkflowTest` (E9, E10, E24: transitions, history rows, the PM limit and the management override, superseding, one open revision, ES-AC-02 lines copied, delete rules, notifications).

### Task 7: Budget

**Files:**
- `Contracts\BudgetCostSource`, `Services\BudgetCostSources`, `Services\BudgetSummary`.
- Actions `BuildBudgetFromEstimate`, `SaveBudget`.
- Listener `BuildBudgetOnApproval` (setting and kind gated).

Test: `BudgetTest` (E11, E12: rebuild replaces only the same root's lines; category netting and OTHER fallback; material lines; manual lines; reason rule; revision rows with old / new totals; the summary with a fake registered cost source giving ES-AC-07's ৳20,00,000 / ৳12,50,000 / ৳7,50,000).

### Task 8: Measurement Book actions

**Files:**
- `Services\MeasurementLimits`.
- Actions `RecordMeasurement`, `UpdateMeasurement`, `VerifyMeasurements`, `RejectMeasurements`, `UnverifyMeasurement`, `DeleteMeasurement`.
- Event `MeasurementVerified`.

Test: `MeasurementTest` (E13, E14: number; BOQ line on the current approved revision only; rate default and `edit_rate`; warn vs block with figures (ES-AC-04: 115 % refused at 10 %); cumulative across a revision through `origin_line_id`; maker-checker (ES-AC-05); setting off saves VERIFIED; reject → edit → RECORDED; unverify refused with `running_bill_line_id`; delete rules; closed project refused).

### Task 9: Inspections and findings actions

**Files:**
- `Concerns\ValidatesInspectionInput`.
- Actions `SaveInspection`, `SubmitInspection`, `CloseInspection`, `DeleteInspection`, `AddFinding`, `ChangeFindingStatus`.
- Event `FindingAssigned`; notification `FindingAssigned`.

Test: `InspectionTest` (E15, E16: number; times; ES-BR-11; ES-BR-12; auto close; HANDOVER / SNAG on a COMPLETED project and others refused; who may change a finding's status; closure note; reopen by PM; notifications on submit and on adding a finding).

### Task 10: Jobs and completion checks

**Files:**
- Jobs `SendMbVerificationDigest`, `NotifyOverdueFindings`; notifications `MeasurementsAwaitingVerification`, `FindingOverdue`; `routes/console.php`.
- `Services\CompletionChecks\OpenFindingsCheck` and `UnverifiedMeasurementsCheck`, registered on `ProjectCompletionChecks` from the provider (`resolving`).

Tests: `EstimationJobsTest` (the digest once per date; overdue once per spell, cleared by a status change, setting gated), `EstimationCompletionChecksTest` (COMPLETED blocked, override still works).

### Task 11: Routes and the estimates list

**Files:** `routes/modules/estimation.php` (all routes of spec §4.2, with screens added in later tasks); `Livewire\Estimates\Index` + view; `Exports\EstimatesExport`. Test: `EstimatesIndexTest` (scope, filters, the latest-revision switch, search, export, mobile markup present).

### Task 12: Estimate editor

**Files:**
- `Livewire\Estimates\Editor` + view: the desktop grid with the Alpine keyboard handlers, the mobile line cards and the line sheet, and the materials tab.
- `Imports\EstimateLinesImport`, Action `ImportEstimateLines`, `Exports\EstimateLinesExport` (the same column layout).

Test: `EstimateEditorTest` (create and edit payloads, a work item fills the line, live totals, copy lines, import good and bad files, a locked estimate redirects to the detail page).

### Task 13: Estimate detail, compare and prints

**Files:**
- `Livewire\Estimates\Show` + view (action sheets for reject, revise, print layout and compare).
- `Services\EstimateComparison`, `Livewire\Estimates\Compare` + view.
- `resources/views/estimation/print.blade.php` (measurement, abstract and materials layouts) and its print route controller.

Tests: `EstimateShowTest` (buttons by status and permission, revision strip, tabs, actions wired), `EstimateCompareTest` (ES-AC-03: added, removed and changed lines and the total difference), `EstimatePrintTest`.

### Task 14: Budget screen and project tabs

**Files:**
- `Livewire\Budget\Show` + view (summary, drill-down, edit sheet, revisions) on `projects/{project}/budget`.
- `Livewire\Budget\ProjectTab`, `Livewire\Site\ProjectTab` + views.
- Projects `Livewire\Projects\Show` (two tabs and two key figure cards by permission, E21) + view.

Tests: `BudgetScreenTest`, `ProjectEstimationTabsTest`.

### Task 15: Measurement Book screens

**Files:** `Livewire\Mb\Index` (presets, bulk verify / reject, export), `Mb\Form` (BOQ picker, progress card, rate lock), `Mb\Show` + views; `Exports\MeasurementsExport`. Test: `MeasurementScreensTest`.

### Task 16: Inspection screens, print and findings board

**Files:** `Livewire\Inspections\Index`, `Inspections\Form` (findings repeater, camera input), `Inspections\Show` (finding status sheet with the after photo), `Findings\Board` + views; `resources/views/estimation/inspection-print.blade.php`; `Exports\InspectionsExport`. Tests: `InspectionScreensTest` (ES-AC-06: a phone-sized payload with three findings and photos, then its print, which shows the v1 Project Visit fields), `FindingsBoardTest` (move, refused move, closing move asks for a note).

### Task 17: Legacy estimates

**Files:** `database/seeders/Legacy/ImportWorkEstimates`, `ImportMaterialEstimates`; `LegacyMap::materialFor`, `LegacyMap::unitFor`; `LegacyDataSeeder`; the v1 estimate tables in `tests/Feature/Legacy/LegacySchema.php`. Test: `LegacyEstimatesImportTest` (L17, L18: titles, APPROVED without a budget, units, the shifted qty / purpose row, free-text materials, a skipped unknown project, a re-run adds nothing).

### Task 18: Legacy visits

**Files:** `database/seeders/Legacy/ImportProjectVisits` (visits and details); `LegacyDataSeeder`; the v1 visit tables in `LegacySchema.php`. Test: `LegacyVisitsImportTest` (L19, L20: type flags, zero date, the engineer name match and the kept text, the regarding → status map, inspection CLOSED vs SUBMITTED, a re-run adds nothing).

- [ ] Run the legacy seeder on the dev database and record the counts in the legacy seed design §7 (as for L14 / L15).

### Task 19: Wrap-up

- [ ] Run `php artisan test --compact tests/Feature/Estimation tests/Feature/Projects tests/Feature/Legacy tests/Feature/Foundation`.
- [ ] Run `vendor/bin/pint --dirty --format agent`, and `vendor/bin/phpstan` if it is configured in CI.
- [ ] Mark the spec Done, record implementation deviations, and list the screens for a 390×844 and desktop check.
