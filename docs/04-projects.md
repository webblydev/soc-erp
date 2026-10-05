# SOC ERP v2 — 04 · Projects (Job Files, Contracts, Team, Tasks, Approvals)

**Build phase:** 3 · **Depends on:** 01, 02, 03, 09 (employees) · **Used by:** 05, 06, 07, 08, 10
**Replaces (legacy):** Settings › Project Entry; Task Management › Task Entry, Task Record, My Task, Completed Task, Archived Task; money fields on tasks (`bill_amount`, `collect_amount`, `due_amount`)

---

## 1. Purpose & Scope

A **project** is SOC's job file (legacy `project_id` like `SOC-BD-0101`). Everything operational and financial hangs off it.

In scope:
- Project master, services sold, contract/deed, milestone payment schedule
- Phases and status life-cycle, handover and closure
- Project team (employees with roles)
- Tasks (with templates, checklists, comments, time), replacing all legacy task screens
- Approval & permit tracking (RAJUK etc.)
- Project documents
- Project financial summary (read model fed by 06/07/08)
- Internal projects (non-billable work such as HR & Admin, Accounts)

Out of scope here: estimates/MB/inspections (05), billing (06), purchasing (07).

---

## 2. Permissions

```text
projects.projects.view_own | view_all | create | update | delete | change_status | close | reopen | export
projects.contracts.view | manage
projects.team.manage
projects.tasks.view_own | view_project | view_all | create | update | delete | assign | complete | archive
projects.task_templates.manage
projects.approvals.view | manage
projects.financials.view
```

"Own project" = user's employee is project manager, supervisor or an active team member.

| Permission | super_admin | management | project_manager | engineer | sales_mgr / exec | accountant / fin_mgr | hr_admin |
|---|---|---|---|---|---|---|---|
| projects view | all | all | own (all with setting) | own | own customers' | all | — |
| create / update | ✓ | ✓ | ✓ | — | create via conversion | — | — |
| change_status / close | ✓ | ✓ | ✓ (own) | — | — | — | — |
| contracts manage | ✓ | ✓ | ✓ | — | — | view | — |
| team manage | ✓ | ✓ | ✓ (own) | — | — | — | — |
| tasks | all | all | project | own + create on own projects | own | own | own |
| approvals manage | ✓ | ✓ | ✓ | ✓ (own projects) | view | view | — |
| financials view | ✓ | ✓ | ✓ (own) | — | — | ✓ | — |

---

## 3. Data Model

### 3.1 Lookups

| Table | Extra columns | Seed |
|---|---|---|
| `project_types` | `is_internal`, `is_billable` | RESIDENTIAL · COMMERCIAL · MIXED_USE · INDUSTRIAL · INTERIOR · RENOVATION · SURVEY · ESTIMATE (BLE/UP sheet) · TRAINING · CONSULTANCY · INTERNAL (is_internal, not billable) |
| `project_statuses` | `is_open`, `is_closed`, `allows_billing`, `allows_costing` | ENQUIRY · CONTRACTED · IN_PROGRESS · ON_HOLD · HANDED_OVER · COMPLETED (closed) · CANCELLED (closed, no billing) |
| `project_phases` | — | DESIGN · APPROVAL · PRE_CONSTRUCTION · CONSTRUCTION · FINISHING · HANDOVER · DEFECT_LIABILITY |
| `project_roles` | — | PM Project Manager · SUPERVISOR · ARCHITECT · STRUCTURAL · MEP · SITE_ENGINEER · DRAFTSMAN · SURVEYOR · SUPPORT_OFFICER · ACCOUNTS |
| `task_types` | `default_estimated_hours` | DESIGN · DRAWING · STRUCTURAL_CALC · APPROVAL_FILE · SITE_VISIT · SURVEY · ESTIMATE · CLIENT_MEETING · PROCUREMENT · ACCOUNTS · HR_ADMIN · SUPPLY_CHAIN · INVENTORY · LOGISTIC · CUSTOMER_RELATION · OTHER (legacy internal services land here) |
| `task_statuses` | `is_done`, `is_cancelled` | TODO Pending · IN_PROGRESS On Progress · REVIEW In Review · BLOCKED Blocked · DONE Completed (is_done) · CANCELLED (is_cancelled) |
| `task_priorities` | — | LOW · NORMAL · HIGH · CRITICAL |
| `approval_authorities` | — | RAJUK · DNCC · DSCC · GAZIPUR_CC · CDA · KDA · UNION_PARISHAD · PAURASHAVA · FIRE_SERVICE · DOE · CAAB · OTHER |
| `approval_types` | `typical_days` | LUC Land Use Clearance · BP Building Construction Permit (design approval) · OC Occupancy Certificate · FIRE_NOC · ENV_CLEARANCE · HEIGHT_CLEARANCE · UP_SHEET · BANK_VALUATION · OTHER |
| `approval_statuses` | `is_final`, `is_success` | PREPARING · SUBMITTED · QUERY Query raised · RESUBMITTED · APPROVED (final, success) · REJECTED (final) · WITHDRAWN (final) |
| `hold_reasons` | — | Client payment pending · Client instruction · Approval pending · Land dispute · Other |

### 3.2 `projects`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| project_number | VARCHAR(40) | no | UNIQUE; `{bl_prefix}-{seq:4}` |
| name | VARCHAR(255) | no | e.g. "Md. Mokbul Hossain, S. Bonosree" |
| customer_id | BIGINT | yes | null only for internal projects |
| business_line_id | BIGINT | no | |
| project_type_id | BIGINT | no | |
| project_status_id | BIGINT | no | default CONTRACTED (from conversion) or ENQUIRY |
| project_phase_id | BIGINT | yes | |
| branch_id | BIGINT | yes | |
| source_lead_id | BIGINT | yes | |
| description | TEXT | yes | |
| site_address | TEXT | yes | |
| location_id | BIGINT | yes | |
| latitude / longitude | DECIMAL(10,7) | yes | site pin |
| plot_no | VARCHAR(60) | yes | |
| land_area | DECIMAL(12,4) | yes | |
| land_area_unit_id | BIGINT | yes | katha/decimal/sft |
| floors | SMALLINT | yes | |
| basements | SMALLINT | yes | |
| built_up_area_sft | DECIMAL(14,2) | yes | |
| project_manager_id | BIGINT | yes | FK employees |
| supervisor_id | BIGINT | yes | FK employees |
| support_officer_id | BIGINT | yes | FK employees (legacy) |
| start_date | DATE | yes | |
| expected_end_date | DATE | yes | |
| handover_date | DATE | yes | |
| actual_end_date | DATE | yes | |
| contract_value | DECIMAL(18,2) | no | default 0; = Σ project_services.amount (maintained by action) |
| budget_cost | DECIMAL(18,2) | no | default 0; = Σ project_budget_lines (05) |
| retention_pct | DECIMAL(7,4) | yes | |
| hold_reason_id | BIGINT | yes | when ON_HOLD |
| cancel_reason | TEXT | yes | |
| completion_pct | DECIMAL(5,2) | no | default 0; manual or from tasks |
| legacy_project_ids | JSON | yes | all legacy rows merged into this one |
| notes | TEXT | yes | |
| [AUDIT] [SOFT] | | | |

Indexes: `customer_id`, `business_line_id`, `project_status_id`, `project_manager_id`, `start_date`, FULLTEXT(name, site_address).

### 3.3 `project_services`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| project_id | BIGINT | no | FK |
| service_id | BIGINT | no | FK |
| description | VARCHAR(500) | yes | |
| quantity | DECIMAL(18,4) | no | default 1 |
| unit_id | BIGINT | yes | |
| rate | DECIMAL(18,4) | no | |
| discount_amount | DECIMAL(18,2) | no | default 0 |
| amount | DECIMAL(18,2) | no | = round(qty × rate) − discount |
| vat_rate_id | BIGINT | yes | |
| service_status | VARCHAR | — | FK `project_service_statuses` [LOOKUP]: NOT_STARTED · IN_PROGRESS · DELIVERED · CANCELLED |
| delivered_on | DATE | yes | |
| sort_order | SMALLINT | no | |
| [AUDIT] | | | |

Derived per line (read model): invoiced amount, remaining to invoice.

### 3.4 `project_contracts`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| project_id | BIGINT | no | UNIQUE (one active contract; amendments below) |
| contract_number | VARCHAR(60) | yes | agreement / deed no. (legacy `Acc_agreement_number`) |
| agreement_date | DATE | no | |
| deed_amount | DECIMAL(18,2) | no | must equal project contract_value when signed |
| vat_inclusive | TINYINT(1) | no | |
| advance_pct | DECIMAL(7,4) | yes | |
| retention_pct | DECIMAL(7,4) | yes | |
| defect_liability_months | SMALLINT | yes | |
| signed_by_customer | VARCHAR(150) | yes | |
| signed_by_company_user_id | BIGINT | yes | |
| status | — | — | FK `contract_statuses` [LOOKUP]: DRAFT · SIGNED · AMENDED · TERMINATED |
| terms | TEXT | yes | |
| [AUDIT] | | | |

`project_contract_amendments`: `[STD], project_contract_id, amendment_no, amendment_date, reason, value_change DECIMAL(18,2), new_deed_amount, approved_by, approved_at`. Approved amendments adjust `project_services` (new/changed lines) and the deed amount.

### 3.5 `payment_schedules`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| project_id | BIGINT | no | |
| sort_order | SMALLINT | no | |
| milestone_name | VARCHAR(200) | no | "Advance on signing", "After design approval"… |
| trigger_type | — | — | FK `schedule_triggers` [LOOKUP]: DATE · PHASE · APPROVAL · TASK · MANUAL |
| trigger_ref_id | BIGINT | yes | phase / approval / task id |
| due_date | DATE | yes | |
| percent | DECIMAL(7,4) | yes | of deed amount |
| amount | DECIMAL(18,2) | no | |
| status | — | — | FK `schedule_statuses` [LOOKUP]: PENDING · DUE · INVOICED · PAID · CANCELLED |
| invoice_id | BIGINT | yes | set when billed (06) |
| [AUDIT] | | | |

### 3.6 `project_employees` (team)

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| project_id | BIGINT | no | |
| employee_id | BIGINT | no | |
| project_role_id | BIGINT | no | |
| allocation_pct | DECIMAL(5,2) | yes | |
| assigned_on | DATE | no | |
| released_on | DATE | yes | |
| is_active | TINYINT(1) | no | |
| notes | VARCHAR(255) | yes | |
| [AUDIT] | | | |

Unique active (project_id, employee_id, project_role_id).

### 3.7 `project_status_histories`
`id, project_id, from_status_id, to_status_id, from_phase_id, to_phase_id, reason, changed_by, changed_at`.

### 3.8 `project_tasks`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| task_number | VARCHAR(40) | no | UNIQUE `T-27-00509` |
| project_id | BIGINT | yes | null = general task |
| parent_id | BIGINT | yes | sub-task (1 level only) |
| task_type_id | BIGINT | no | |
| project_phase_id | BIGINT | yes | |
| title | VARCHAR(255) | no | |
| description | TEXT | yes | (legacy `task_detail`) |
| file_number | VARCHAR(60) | yes | legacy file number kept for reference |
| assigned_by | BIGINT | no | FK users |
| assignee_employee_id | BIGINT | yes | FK employees |
| support_officer_id | BIGINT | yes | FK employees |
| reviewer_employee_id | BIGINT | yes | |
| task_priority_id | BIGINT | no | |
| is_important | TINYINT(1) | no | legacy flag |
| task_status_id | BIGINT | no | |
| start_date | DATE | yes | |
| due_date | DATE | yes | (legacy `deadline`) |
| completed_at | DATETIME | yes | |
| completed_by | BIGINT | yes | |
| estimated_hours | DECIMAL(8,2) | yes | |
| actual_hours | DECIMAL(8,2) | no | = Σ task_time_logs |
| progress_pct | TINYINT | no | default 0 |
| archived_at | DATETIME | yes | |
| blocked_reason | VARCHAR(255) | yes | |
| sort_order | INT | no | |
| [AUDIT] [SOFT] | | | |

Indexes: (project_id, task_status_id), (assignee_employee_id, task_status_id, due_date), `due_date`, `archived_at`.

**No money columns.** Legacy bill/collect/due → opening invoices/receipts (11).

### 3.9 Task supporting tables

| Table | Columns |
|---|---|
| `task_checklist_items` | id, task_id, title, is_done, done_by, done_at, sort_order |
| `task_comments` | [STD], task_id, user_id, body TEXT, [SOFT]; mentions parsed `@username` → notification |
| `task_time_logs` | [STD], task_id, employee_id, work_date, hours DECIMAL(6,2), note |
| `task_watchers` | task_id, user_id |
| `task_templates` | [STD], name, service_id (nullable), project_type_id (nullable), is_active |
| `task_template_items` | id, task_template_id, title, task_type_id, project_phase_id, project_role_id (who gets it), offset_days_start, duration_days, estimated_hours, checklist JSON, sort_order, depends_on_item_id |

Example template "Building Design & RAJUK Approval": Site survey → Architectural concept → Client approval of concept → Architectural drawings → Structural design → MEP drawings → Soil test coordination → RAJUK file preparation → RAJUK submission → Query response → Approval collection → Handover of approved drawings.

### 3.10 Approvals

`project_approvals`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| project_id | BIGINT | no | |
| approval_authority_id | BIGINT | no | |
| approval_type_id | BIGINT | no | |
| reference_no | VARCHAR(100) | yes | authority file / memo no. |
| responsible_employee_id | BIGINT | yes | |
| approval_status_id | BIGINT | no | |
| prepared_on | DATE | yes | |
| submitted_on | DATE | yes | |
| expected_on | DATE | yes | submitted_on + typical_days |
| approved_on | DATE | yes | |
| valid_until | DATE | yes | |
| authority_fee | DECIMAL(18,2) | yes | (paid via expense/vendor bill, link below) |
| fee_expense_id | BIGINT | yes | FK expenses (08) |
| notes | TEXT | yes | |
| [AUDIT] | | | |

`project_approval_events`: `[STD], project_approval_id, approval_status_id, event_date, note, attachment_id, created_by` — full trail (query raised, resubmitted…).

`approval_checklist_items`: `id, project_approval_id, title, is_done, done_at, attachment_id` (documents needed: land deed, mutation, DCR, NID, tax receipt, soil report, drawings…). Default checklist per approval type in `approval_type_checklists`.

---

## 4. Settings (group `projects`)

| Key | Type | Default |
|---|---|---|
| projects.pm_can_view_all | bool | false |
| projects.completion_from_tasks | bool | false (manual %) |
| projects.require_contract_before_billing | bool | true |
| projects.auto_apply_task_template | bool | true |
| projects.task_overdue_notify | bool | true |
| projects.archive_done_tasks_after_days | int | 30 |
| projects.default_status_on_conversion | fk | CONTRACTED |

---

## 5. Screens

### 5.1 Projects — List (`/projects`)
Columns: Project #, Name, Customer, Business line, Type, Status, Phase, PM, Start, Expected end, Contract value, Billed, Received, Receivable, Cost to date, Gross margin %, Open tasks, Overdue tasks, Approval status (latest).
Filters: status, phase, business line, type, PM, customer, location, date range, has overdue tasks, approvals pending, internal/billable.
Saved views: *My projects* · *Active* · *On hold* · *Approvals pending* · *Handed over not completed* · *Receivable > 0*.
Bulk: change PM, export.

### 5.2 Project — Form
Sections:
- **General**: Business line* (locks prefix after save), Project type*, Name*, Customer* (search; "+ New" if `crm.customers.create`), Status, Phase, Branch, Description.
- **Site**: Site address, Location (tree), Map pin, Plot no., Land area + unit, Floors, Basements, Built-up area.
- **People**: Project manager*, Supervisor, Support officer.
- **Dates**: Start, Expected end, Handover.
- **Services** grid: Service*, Description, Qty, Unit, Rate, Discount, Amount, VAT → footer: Contract value.
- **Apply task template** (checkbox + template select; default from first service).

### 5.3 Project — Detail (`/projects/{id}`) — the project "cockpit"

Header: number, name, customer link, status & phase badges, PM. Actions: Edit · Change status · Add task · Log activity · New estimate (05) · New inspection (05) · New invoice (06) · Receive payment (06) · New work order (07) · New expense (08) · Print project sheet.

KPI strip: Contract value · Invoiced · Received · Receivable · Cost to date (actual) · Budget cost · Gross profit · Margin % · Tasks done/total · Next milestone due.

Tabs:
1. **Overview** — details, services with delivery status and invoiced/remaining, team summary, latest activities.
2. **Contract & Schedule** — contract form, amendments, payment schedule grid (milestone, due, %, amount, status, invoice link) with "Create invoice from milestone" button.
3. **Team** — members with role, allocation, dates; add/release.
4. **Tasks** — list / board (by status) / Gantt-lite (start-due bars by phase); filters by assignee, phase, status.
5. **Approvals** — cards per approval with status, dates, days elapsed vs typical; event trail; checklist.
6. **Estimates & Budget** (05) — estimates list, budget vs actual by cost category.
7. **Site** (05) — inspections, open findings, MB entries.
8. **Billing** (06) — running bills, invoices, receipts, credit notes.
9. **Costs** (07/08) — work orders, vendor bills, expenses, advances settled.
10. **Ledger** — project ledger from GL (date, doc, description, debit, credit, balance) — same as legacy Project Ledger.
11. **Activities** · 12. **Documents** · 13. **History**.

### 5.4 Change status modal
Target status select → conditional fields: ON_HOLD → hold reason*, note; CANCELLED → cancel reason*, (warning if invoices exist); HANDED_OVER → handover date*; COMPLETED → actual end date*, checklist of closure conditions (§7). Phase change optional in same modal.

### 5.5 Tasks — global (`/tasks`)
Saved views replacing legacy menus: **My Tasks** · **Assigned by me** · **All (Task Record)** · **Overdue** · **Completed** · **Archived**.
Columns (legacy-compatible): Task #, File no., Client/Customer, Entry date, Project, Project type, Title, Assignee, Supervisor/Support officer, Assigned by, Priority, Due (deadline), Status, Completed date/by.
Filters: status, assignee, project, type, phase, priority, due range, important.
Board view by status with drag; bulk: reassign, change status, set due date, archive.

### 5.6 Task — Form (drawer)
Project (optional), Parent task, Type*, Phase, Title*, Description (rich text), Assignee, Support officer, Reviewer, Priority, Important ☐, Start, Due, Estimated hours, Checklist items, Watchers, Attachments.

### 5.7 Task — Detail (drawer/page)
Status buttons (Start · Submit for review · Complete · Block · Cancel), checklist, comments with @mentions, time log (add hours), attachments, history, sub-tasks.

### 5.8 Task templates (`/projects/task-templates`)
List; form with item grid (title, type, phase, role, start offset, duration, hours, depends on, checklist). Preview generated dates from a sample start date.

### 5.9 Approvals tracker (`/projects/approvals`)
Cross-project list: Project, Authority, Type, Reference, Responsible, Status, Submitted, Expected, Days pending, Overdue flag. Filters: authority, type, status, responsible. Detail opens approval card with timeline and checklist; "Add event" (status, date, note, file).

### 5.10 Project sheet print
One-page summary: project + customer + site + services + contract + schedule + team + financial summary.

---

## 6. Workflows

### 6.1 Project status

```text
ENQUIRY → CONTRACTED → IN_PROGRESS ⇄ ON_HOLD
                            ↓
                       HANDED_OVER → COMPLETED
any open → CANCELLED
COMPLETED/CANCELLED → (reopen, management only) → IN_PROGRESS
```

### 6.2 Task status

```text
TODO → IN_PROGRESS → REVIEW → DONE
  ↘        ↓  ↑         ↓
   BLOCKED ←┘ └── (rejected review back to IN_PROGRESS)
any → CANCELLED ; DONE → (reopen) IN_PROGRESS
DONE + archive_after_days → archived_at set (hidden from default views)
```

### 6.3 Applying a task template
On project create (or manual "Apply template"): for each item → create task with `start = project.start_date + offset_days_start`, `due = start + duration_days`, assignee = active team member with item's role (else PM), checklist copied; dependencies stored in `task_dependencies(task_id, depends_on_task_id)`; dependent tasks cannot start until predecessors are DONE (soft warning).

### 6.4 Payment schedule trigger
Scheduler daily + on events (`ProjectPhaseChanged`, `ApprovalApproved`, `TaskCompleted`): schedule lines whose trigger is satisfied move PENDING → DUE and notify PM + accountant ("Milestone due: create invoice").

---

## 7. Business Rules

| ID | Rule |
|---|---|
| PRJ-BR-01 | Project number generated from business line prefix sequence; immutable. |
| PRJ-BR-02 | Billable project requires a customer; internal project types must have no customer and cannot be invoiced. |
| PRJ-BR-03 | `contract_value` always equals Σ `project_services.amount` (excluding CANCELLED services); recalculated in the action that changes services. |
| PRJ-BR-04 | When contract is SIGNED, services and deed amount are locked; changes only through an approved amendment. |
| PRJ-BR-05 | Payment schedule Σ amount must equal deed amount (± 1 Taka rounding) before the contract can be marked SIGNED; percent and amount stay in sync. |
| PRJ-BR-06 | If `require_contract_before_billing`, invoices (06) need a SIGNED contract — except advance invoices from schedule line 1. |
| PRJ-BR-07 | Billing allowed only when status `allows_billing`; costing (vendor bills, expenses) only when `allows_costing` (CANCELLED blocks new costs; COMPLETED allows with warning). |
| PRJ-BR-08 | COMPLETED requires: all tasks DONE/CANCELLED, all approvals final, receivable = 0 **or** management override with reason, no draft financial documents. |
| PRJ-BR-09 | CANCELLED with posted invoices requires credit notes for unearned amounts (warning lists them). |
| PRJ-BR-10 | Project manager must be an active employee; when an employee is deactivated, their projects are flagged for PM reassignment. |
| PRJ-BR-11 | Task due date ≥ start date; sub-tasks only one level deep. |
| PRJ-BR-12 | Only assignee, reviewer, PM of the project or users with `tasks.update` (all) can change task status. |
| PRJ-BR-13 | Completing a task with unchecked checklist items asks for confirmation (configurable to block). |
| PRJ-BR-14 | Task overdue = not done/cancelled and due_date < today. |
| PRJ-BR-15 | Approval `expected_on` defaults to submitted_on + type typical_days; overdue when not final and today > expected_on. |
| PRJ-BR-16 | APPROVED approval requires approved_on and at least one attachment (approval letter). |
| PRJ-BR-17 | Projects with any financial document cannot be deleted (soft delete only for empty ENQUIRY projects). |
| PRJ-BR-18 | Engineers see only projects where they are active team members (unless `view_all`). |

---

## 8. Project Financial Summary (read model)

Computed from GL lines tagged with `project_id` (08) — never from typed figures.

| Figure | Source |
|---|---|
| Contract value | projects.contract_value |
| Invoiced | Σ approved invoices − approved credit notes (06) |
| Received | Σ receipt allocations to project invoices + project-tagged advances |
| Receivable | Invoiced − Received (AR lines with project_id) |
| Unbilled contract | Contract value − Invoiced |
| Revenue (GL) | Σ credit − debit on Revenue accounts with project_id |
| Direct cost (GL) | Σ debit − credit on Direct Cost (5xxx) accounts with project_id |
| Gross profit | Revenue − Direct cost |
| Margin % | Gross profit / Revenue |
| Budget cost / variance | 05 budget lines vs direct cost by cost category |
| Payable to vendors | AP lines with project_id |

Cached in `project_financial_snapshots (project_id, as_of, json)` refreshed on finance events and nightly.

---

## 9. Integration

| Direction | Event | Effect |
|---|---|---|
| In | `LeadConverted` | `CreateProject` (+ services from lead) |
| Out | `ProjectCreated` | 05 enables estimates; 08 project dimension |
| Out | `ProjectStatusChanged`, `ProjectPhaseChanged` | schedule triggers; customer timeline; notifications |
| Out | `ApprovalStatusChanged` | schedule triggers; notify PM/customer manager |
| In | `InvoiceApproved`, `ReceiptPosted`, `VendorBillApproved`, `ExpensePosted`, journal reversals | refresh financial snapshot; schedule status INVOICED/PAID |
| In | `EmployeeDeactivated` | flag PM/team/task reassignment |

---

## 10. Notifications

| Key | Trigger | To |
|---|---|---|
| `projects.assigned_pm` | PM set/changed | new PM |
| `projects.team_added` | employee added to team | employee |
| `tasks.assigned` | task assigned/reassigned | assignee |
| `tasks.mentioned` | @mention in comment | mentioned user |
| `tasks.due_tomorrow` | daily | assignee |
| `tasks.overdue` | daily | assignee, PM |
| `tasks.review_requested` | status → REVIEW | reviewer / PM |
| `approvals.overdue` | daily | responsible, PM |
| `approvals.status_changed` | event added | PM, account manager |
| `schedule.milestone_due` | schedule → DUE | PM, accountant |

---

## 11. Reports (detail in 10)
Project status summary · Project progress · Project profitability · Budget vs actual · Contract vs billed vs collected (legacy *Monthly Revenue Report* columns) · Project ledger · Task status by employee · Overdue tasks · Task completion trend · Approval tracker & average approval duration per authority · Team allocation · Projects by business line / location.

---

## 12. Components (indicative)

```text
Livewire: Projects\Index, Projects\Form, Projects\Show (tabs as child components),
          Projects\ChangeStatus, Projects\Contract, Projects\Schedule, Projects\Team,
          Tasks\Index (list/board), Tasks\Drawer, Tasks\Templates, Approvals\Index, Approvals\Card
Actions:  CreateProject, UpdateProject, ChangeProjectStatus, SaveProjectServices, SignContract,
          ApproveAmendment, SavePaymentSchedule, AddTeamMember, ReleaseTeamMember,
          ApplyTaskTemplate, CreateTask, UpdateTask, ChangeTaskStatus, LogTaskTime,
          CreateApproval, AddApprovalEvent, RefreshProjectFinancials
Jobs:     EvaluateScheduleTriggers (daily), NotifyOverdueTasks, NotifyOverdueApprovals,
          ArchiveDoneTasks, RebuildFinancialSnapshots (nightly)
```

---

## 13. Acceptance Criteria

| ID | Criterion |
|---|---|
| PRJ-AC-01 | Creating a "Building Design & RAJUK Approval" project with template applied creates the template tasks with dates and assignees from team roles. |
| PRJ-AC-02 | Signing a contract whose schedule totals ৳4,95,000 against a ৳5,00,000 deed is refused with the difference shown. |
| PRJ-AC-03 | After signing, editing a service rate is blocked; an amendment adding ৳50,000 updates contract value once approved. |
| PRJ-AC-04 | Marking the RAJUK approval APPROVED moves the "After approval" milestone to DUE and notifies PM and accountant. |
| PRJ-AC-05 | An engineer assigned to two projects sees only those two in Projects and only own tasks in My Tasks. |
| PRJ-AC-06 | Trying to COMPLETE a project with ৳10,000 receivable is blocked unless management overrides with a reason. |
| PRJ-AC-07 | Project KPI strip equals GL-based project ledger totals for the same date. |
| PRJ-AC-08 | Legacy task screens' columns are all available in the v2 task list (File no., Client Id, Project type, Supervisor, Assigned by, Completed by/date). |

---

## 14. Open Questions

1. Are tasks always tied to a project, or are internal department tasks common (HR, accounts)? (Spec allows both.)
2. Who signs contracts on SOC's side and is a contract PDF template needed?
3. Typical RAJUK timelines to seed `typical_days`.
4. Should customers get any portal/SMS updates on approval status (future)?
