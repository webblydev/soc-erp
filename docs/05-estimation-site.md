# SOC ERP v2 — 05 · Estimation & Site (BOQ, Material Estimates, Budget, Measurement Book, Site Inspections)

**Build phase:** 4 · **Depends on:** 01, 02, 04 · **Used by:** 06, 07, 10
**Replaces (legacy):** Project Operation › Work Estimate Sheet Entry/Record, Project Material Estimate/Record, Project Visit/Record; MB line grids inside Project Bill Entry and Vendor Bill Entry

---

## 1. Purpose & Scope

- **Work estimate / BOQ** — measurement sheets (nos × L × W × H) priced by work item; supports revisions. Also used for standalone estimate jobs (Bank Loan Estimate, Union Parishad sheet, Estimating Work).
- **Material estimate** — material quantities per project/work with revisions and purpose.
- **Project budget** — cost budget by cost category (from approved estimates or manual) for Budget-vs-Actual.
- **Measurement Book (MB)** — measured executed quantities (with MB book & page no.), verified, then billed to the customer (06) or certified for a vendor (07).
- **Site inspections** — weekly / event inspections with findings and follow-up to closure.

---

## 2. Permissions

```text
estimation.estimates.view | create | update | submit | approve | revise | delete | print | export
estimation.budget.view | manage
site.mb.view | create | update | verify | delete
site.inspections.view | create | update | close_finding | delete | print
```

| Permission | management | project_manager | engineer | accountant | sales |
|---|---|---|---|---|---|
| estimates view | all | own projects | own projects | all | own customers |
| estimates create/update/submit | ✓ | ✓ | ✓ | — | — |
| estimates approve | ✓ | ✓ (own, ≤ limit) | — | — | — |
| budget manage | ✓ | ✓ | — | view | — |
| MB create/update | ✓ | ✓ | ✓ | view | — |
| MB verify | ✓ | ✓ | — | — | — |
| inspections create/update | ✓ | ✓ | ✓ | — | — |
| close finding | ✓ | ✓ | ✓ (responsible) | — | — |

---

## 3. Data Model

### 3.1 Lookups

| Table | Extra | Seed |
|---|---|---|
| `estimate_kinds` | — | BOQ Work Estimate / BOQ · MATERIAL Material Estimate · BLE Bank Loan Estimate · UP_SHEET Union Parishad Sheet |
| `estimate_statuses` | `is_locked`, `is_approved` | DRAFT · SUBMITTED (locked) · APPROVED (locked, approved) · REJECTED · SUPERSEDED (locked) |
| `cost_categories` | `default_account_id` | MATERIAL · LABOUR · SUBCONTRACT · EQUIPMENT · TRANSPORT · APPROVAL_FEE · CONSULTANT · OVERHEAD · OTHER |
| `mb_statuses` | `is_billable` | RECORDED · VERIFIED (billable) · BILLED · REJECTED |
| `inspection_types` | — | WEEKLY Weekly · EVENT Event-based · CASTING Pour/Casting · MATERIAL Material check · HANDOVER Handover · SNAG Snag list |
| `inspection_statuses` | — | DRAFT · SUBMITTED · CLOSED |
| `finding_categories` | — | Quality · Safety · Progress · Material · Design deviation · Workmanship · Other (legacy "Regarding") |
| `finding_severities` | `color` | LOW · MEDIUM · HIGH · CRITICAL |
| `finding_statuses` | `is_closed` | OPEN · IN_PROGRESS · RESOLVED (closed) · ACCEPTED (closed, no action) |

### 3.2 `estimates`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| estimate_number | VARCHAR(40) | no | UNIQUE `EST-27-0012` |
| estimate_kind_id | BIGINT | no | |
| project_id | BIGINT | no | |
| title | VARCHAR(255) | no | legacy `work_name` |
| site_address | TEXT | yes | legacy `address` (default project) |
| estimate_date | DATE | no | |
| revision_no | SMALLINT | no | 0 = original |
| root_estimate_id | BIGINT | yes | first version |
| revised_from_id | BIGINT | yes | previous version |
| revision_purpose | VARCHAR(500) | yes | legacy "Purpose of Revised Estimate" |
| estimate_status_id | BIGINT | no | |
| prepared_by | BIGINT | no | FK employees |
| checked_by | BIGINT | yes | |
| approved_by | BIGINT | yes | FK users |
| approved_at | DATETIME | yes | |
| subtotal | DECIMAL(18,2) | no | Σ lines |
| overhead_pct | DECIMAL(7,4) | yes | |
| profit_pct | DECIMAL(7,4) | yes | |
| vat_pct | DECIMAL(7,4) | yes | |
| total_amount | DECIMAL(18,2) | no | |
| is_customer_facing | TINYINT(1) | no | BLE / UP sheets are deliverables |
| notes | TEXT | yes | |
| [AUDIT] [SOFT] | | | |

### 3.3 `estimate_sections`
`id, estimate_id, name ("Substructure", "Ground floor"…), sort_order`.

### 3.4 `estimate_lines` (work / measurement sheet)

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| estimate_id | BIGINT | no | |
| estimate_section_id | BIGINT | yes | |
| line_no | VARCHAR(20) | no | e.g. "2.03" |
| work_item_id | BIGINT | yes | |
| description | TEXT | no | legacy `work_description` |
| level | VARCHAR(60) | yes | floor / level |
| location | VARCHAR(120) | yes | grid / room |
| nos | DECIMAL(18,4) | no | default 1 (legacy `nose`) |
| length | DECIMAL(18,4) | yes | |
| width | DECIMAL(18,4) | yes | |
| height | DECIMAL(18,4) | yes | |
| deduction | TINYINT(1) | no | line subtracts (openings) |
| unit_id | BIGINT | no | |
| quantity | DECIMAL(18,4) | no | computed per formula, or manual |
| quantity_is_manual | TINYINT(1) | no | |
| rate | DECIMAL(18,4) | yes | |
| amount | DECIMAL(18,2) | no | = quantity × rate (negative if deduction) |
| cost_category_id | BIGINT | yes | for budget roll-up |
| remarks | VARCHAR(255) | yes | |
| sort_order | INT | no | |

Quantity formula from work item `measurement_formula`: `nos_l_w_h` → nos×L×W×H; `nos_l_w` → nos×L×W; `nos_l` → nos×L; `nos` → nos; `manual` → typed.

### 3.5 `estimate_material_lines`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| estimate_id | BIGINT | no | |
| material_id | BIGINT | no | |
| unit_id | BIGINT | no | |
| estimated_qty | DECIMAL(18,4) | no | legacy `total_estimated_qty` |
| wastage_pct | DECIMAL(7,4) | yes | |
| total_qty | DECIMAL(18,4) | no | = estimated × (1 + wastage) |
| rate | DECIMAL(18,4) | yes | |
| amount | DECIMAL(18,2) | no | |
| purpose | VARCHAR(255) | yes | legacy `purpose_estimate` |
| sort_order | INT | no | |

### 3.6 `project_budget_lines`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| project_id | BIGINT | no | |
| cost_category_id | BIGINT | no | |
| work_item_id | BIGINT | yes | |
| material_id | BIGINT | yes | |
| description | VARCHAR(255) | yes | |
| budget_qty | DECIMAL(18,4) | yes | |
| unit_id | BIGINT | yes | |
| budget_amount | DECIMAL(18,2) | no | |
| source_estimate_id | BIGINT | yes | |
| [AUDIT] | | | |

`project_budget_revisions`: `id, project_id, revision_no, reason, old_total, new_total, approved_by, approved_at` (each budget change after first approval logged).

### 3.7 `measurement_entries` (Measurement Book)

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| mb_number | VARCHAR(40) | no | UNIQUE |
| project_id | BIGINT | no | |
| direction | VARCHAR(10) | no | `customer` (SOC bills client) or `vendor` (vendor bills SOC) — lookup `mb_directions` |
| work_order_id | BIGINT | yes | required when vendor (07) |
| work_order_item_id | BIGINT | yes | |
| estimate_line_id | BIGINT | yes | link to BOQ line |
| mb_book_no | VARCHAR(30) | yes | physical MB book |
| mb_page_no | VARCHAR(30) | yes | legacy `mb__no` |
| measured_on | DATE | no | |
| measured_by | BIGINT | no | FK employees |
| work_item_id | BIGINT | yes | legacy `item_code` |
| description | TEXT | no | |
| location | VARCHAR(120) | yes | legacy Floor/Location |
| nos / length / width / height | DECIMAL(18,4) | yes | |
| unit_id | BIGINT | no | |
| quantity | DECIMAL(18,4) | no | |
| rate | DECIMAL(18,4) | no | from WO item / BOQ line / contract |
| amount | DECIMAL(18,2) | no | |
| achievement_pct | DECIMAL(7,4) | yes | legacy "Achievement"; cumulative qty / BOQ qty |
| mb_status_id | BIGINT | no | |
| verified_by | BIGINT | yes | FK users |
| verified_at | DATETIME | yes | |
| running_bill_line_id | BIGINT | yes | set when billed (06/07) |
| remarks | VARCHAR(255) | yes | |
| [AUDIT] [SOFT] | | | |

Indexes: (project_id, direction, mb_status_id), work_order_id, estimate_line_id.

### 3.8 `site_inspections`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| inspection_number | VARCHAR(40) | no | UNIQUE |
| project_id | BIGINT | no | |
| inspection_type_id | BIGINT | no | legacy weekly / event checkboxes |
| inspection_date | DATE | no | |
| start_time / end_time | TIME | yes | |
| permittee_name | VARCHAR(150) | yes | legacy `permitee_name` (land owner / permit holder) |
| contractor_name | VARCHAR(150) | yes | or vendor_id |
| contractor_vendor_id | BIGINT | yes | FK vendors |
| project_engineer_id | BIGINT | yes | FK employees (legacy `project_eng_name`) |
| field_office_phone | VARCHAR(30) | yes | |
| weather | VARCHAR(60) | yes | |
| workers_on_site | SMALLINT | yes | |
| work_progress_summary | TEXT | yes | legacy `description` |
| inspection_status_id | BIGINT | no | |
| client_representative | VARCHAR(150) | yes | |
| [AUDIT] [SOFT] | | | |

### 3.9 `site_inspection_findings`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| site_inspection_id | BIGINT | no | |
| project_id | BIGINT | no | denormalised |
| location | VARCHAR(150) | yes | legacy `visit_location` |
| description | TEXT | no | legacy `visit_description` |
| finding | TEXT | yes | legacy `visit_finding` |
| finding_category_id | BIGINT | yes | legacy `visit_regarding` |
| finding_severity_id | BIGINT | no | |
| action_required | TEXT | yes | |
| responsible_type | VARCHAR(20) | yes | `employee`, `vendor`, `customer` |
| responsible_id | BIGINT | yes | |
| due_date | DATE | yes | |
| finding_status_id | BIGINT | no | |
| closed_on | DATE | yes | |
| closed_by | BIGINT | yes | |
| closure_note | TEXT | yes | |

Photos via `attachments` on the finding (document type Site Photo); before/after.

---

## 4. Settings (group `estimation`)

| Key | Default |
|---|---|
| estimation.pm_approval_limit | 5,000,000 (above → management) |
| estimation.auto_budget_from_approved_estimate | true |
| site.mb_requires_verification | true |
| site.mb_allow_exceed_boq_pct | 10 (warn above; block above with setting) |
| site.finding_overdue_notify | true |

---

## 5. Screens

### 5.1 Estimates — List (`/estimates`)
Columns: Estimate #, Kind, Project, Title, Date, Rev., Status, Prepared by, Total. Filters: kind, project, status, date. Toggle "latest revision only" (default on).

### 5.2 Estimate — Editor
Header: Kind*, Project*, Title*, Site address, Date*, Prepared by*, Checked by, Notes, Overhead %, Profit %, VAT %.

Work (BOQ) tab — spreadsheet-like grid grouped by sections:
`Line no · Work item (search) · Description · Level · Location · Nos · L · W · H · Deduct ☐ · Unit · Qty (auto) · Rate · Amount · Category · Remarks`
- Choosing a work item fills description, unit, rate, formula.
- Keyboard: Tab across, Enter new row, Ctrl+D duplicate row, Ctrl+↑/↓ move.
- Section subtotals; footer: subtotal, overhead, profit, VAT, total.
- Import lines from Excel template; copy lines from another estimate.

Material tab (for MATERIAL kind or optional on BOQ): `Material · Unit · Est. qty · Wastage % · Total qty · Rate · Amount · Purpose`. "Derive materials" helper (future: material coefficients per work item).

Actions: Save draft · Submit · Approve / Reject (with note) · **Revise** (creates new version, copies lines, previous → SUPERSEDED on approval of new) · Print (Measurement sheet layout / Abstract of cost / Material statement) · Export Excel · Create budget from estimate.

### 5.3 Estimate compare
Pick two revisions → line-by-line diff (qty, rate, amount) with totals.

### 5.4 Project budget (`/projects/{id}` › Estimates & Budget)
Grid by cost category: Budget · Committed (open work orders, 07) · Actual (GL direct cost) · Remaining · Variance % with colour. Drill-down to lines and to source documents. Manage: edit lines (after approval → revision record with reason).

### 5.5 Measurement Book — List (`/site/mb`)
Columns: MB #, Project, Direction, Work order/Vendor, MB book/page, Measured on, Item, Description, Location, Qty, Unit, Rate, Amount, Achievement %, Status, Running bill.
Filters: project, direction, vendor/WO, status, date, measured by.
Bulk: Verify, Reject.

### 5.6 MB entry form
Project*, Direction*, Work order (if vendor; lists WOs of project) → WO item, or BOQ line (if customer), MB book no., page no., Measured on*, Measured by*, Item, Description*, Location, Nos/L/W/H, Unit, Qty (auto), Rate (prefilled, editable only by PM), Amount, Remarks, Photos.
Shows: BOQ / WO qty, previously measured qty, this qty, cumulative %, warning if over.

### 5.7 Site inspections — List (`/site/inspections`)
Columns (legacy-compatible): Inspection #, Date, Project, Project engineer, Contractor, Permittee, Type, Findings (open/total), Status.

### 5.8 Site inspection — Form (mobile-friendly)
Header fields §3.8; findings grid (location, description, finding, category, severity, action, responsible, due, photos). Large touch targets; camera upload; works on phone browser at site. Submit → status SUBMITTED; PDF report.

### 5.9 Open findings board (`/site/findings`)
Kanban by finding status; filters project, severity, responsible, overdue. Close with note and "after" photo.

---

## 6. Workflows

### 6.1 Estimate
```text
DRAFT → SUBMITTED → APPROVED → (Revise) → new DRAFT rev n+1 … APPROVED → old = SUPERSEDED
              └→ REJECTED → (edit) → DRAFT
```
On APPROVED (BOQ/MATERIAL kind, `auto_budget_from_approved_estimate`): budget lines replaced for that estimate's source (budget revision logged).

### 6.2 MB → billing
```text
RECORDED → VERIFIED → (selected into running bill 06/07) → BILLED
        └→ REJECTED
```

### 6.3 Inspection
```text
DRAFT → SUBMITTED → CLOSED (when all findings closed, or manually by PM)
Finding: OPEN → IN_PROGRESS → RESOLVED | ACCEPTED
```

---

## 7. Business Rules

| ID | Rule |
|---|---|
| ES-BR-01 | Estimates in SUBMITTED/APPROVED/SUPERSEDED are read-only; changes require Revise. |
| ES-BR-02 | Only one non-superseded APPROVED revision per root estimate. |
| ES-BR-03 | Quantity = formula result unless `quantity_is_manual`; negative quantities only via deduction flag. |
| ES-BR-04 | Approval above `pm_approval_limit` needs management. |
| ES-BR-05 | Budget amounts cannot be negative; total budget change after first approval requires reason. |
| ES-BR-06 | Vendor-direction MB requires an approved work order on the same project; rate defaults from WO item and cannot exceed it without PM permission. |
| ES-BR-07 | Customer-direction MB rate defaults from BOQ line / contract service rate. |
| ES-BR-08 | Cumulative measured qty > BOQ/WO qty by more than `mb_allow_exceed_boq_pct` → blocked (requires WO/contract amendment). |
| ES-BR-09 | Only VERIFIED MB entries can be selected into running bills; BILLED entries are locked. |
| ES-BR-10 | MB entries cannot be verified by the person who measured them when `mb_requires_verification` is on (maker-checker). |
| ES-BR-11 | A finding with severity HIGH/CRITICAL must have responsible and due date. |
| ES-BR-12 | Inspection can't be CLOSED with open HIGH/CRITICAL findings. |
| ES-BR-13 | Project in CANCELLED/COMPLETED: no new MB or inspections (except HANDOVER/SNAG types on HANDED_OVER). |

---

## 8. Integration

| Direction | Event | Effect |
|---|---|---|
| Out | `EstimateApproved` | budget lines (04 financials), notify PM |
| Out | `MeasurementVerified` | available for 06 customer running bill / 07 vendor running bill |
| In | `RunningBillApproved` | MB entries → BILLED |
| In | `RunningBillCancelled` | MB entries → VERIFIED |
| Out | `FindingOverdue` | notifications |
| Read | 07 committed (open WO), 08 actuals | budget vs actual |

---

## 9. Notifications
`estimates.submitted` → approver · `estimates.approved/rejected` → preparer · `mb.awaiting_verification` (daily digest) → PM · `findings.assigned` → responsible employee · `findings.overdue` → responsible, PM.

---

## 10. Reports
Estimate abstract & measurement sheet print · Material statement · Estimate revision history · Budget vs Actual (by project, category) · MB register (by project/vendor, date) · Work progress (BOQ qty vs measured qty, % achievement) · Inspection register · Open findings aging · Findings by category/contractor.

---

## 11. Components (indicative)

```text
Livewire: Estimation\Estimates\Index, Estimation\Estimates\Editor (grid), Estimation\Estimates\Compare,
          Estimation\Budget, Site\Mb\Index, Site\Mb\Form, Site\Inspections\Index,
          Site\Inspections\Form, Site\Findings\Board
Actions:  SaveEstimate, SubmitEstimate, ApproveEstimate, RejectEstimate, ReviseEstimate,
          BuildBudgetFromEstimate, SaveBudgetLine, RecordMeasurement, VerifyMeasurements,
          SaveInspection, SubmitInspection, CloseFinding
Services: QuantityCalculator, EstimateTotals
```

---

## 12. Acceptance Criteria

| ID | Criterion |
|---|---|
| ES-AC-01 | A BOQ line "Brick work, nos 2, L 20, W 0.833, H 10, unit cft" computes 333.20 cft. |
| ES-AC-02 | Revising an approved estimate creates Rev 1 with all lines; approving Rev 1 marks Rev 0 SUPERSEDED and rebuilds budget. |
| ES-AC-03 | Estimate compare shows changed lines and total difference. |
| ES-AC-04 | MB entry exceeding WO quantity by 15 % (limit 10 %) is blocked with the cumulative figures. |
| ES-AC-05 | The measurer cannot verify their own MB entry. |
| ES-AC-06 | An inspection created on a phone with 3 findings and photos produces a PDF report matching legacy Project Visit fields. |
| ES-AC-07 | Budget vs Actual shows material budget ৳20,00,000, actual ৳12,50,000 from posted vendor bills, remaining ৳7,50,000. |

---

## 13. Open Questions
1. Should BLE / UP sheet estimates have their own print formats (bank-specific)? Provide samples.
2. Are material coefficients (e.g. cement bags per cft of RCC) wanted to auto-derive material estimates?
3. Is a separate physical MB book numbering required on print?
