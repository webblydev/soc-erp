# Projects — Design

**Phase:** 3 (one sub-project covering docs/04 except the parts that need 05–08)
**Source specs:** `docs/00-index-and-conventions.md`, `docs/04-projects.md`, `docs/03-crm.md` §5.7 (conversion project step)
**Builds on:** `docs/superpowers/specs/2026-10-06-crm-design.md`, `docs/superpowers/specs/2026-10-06-hrm-design.md`, `docs/superpowers/specs/2026-10-06-shared-services-design.md`
**Date:** 07 Oct 2026
**Status:** In progress, on branch `projects`. Plan: `docs/superpowers/plans/2026-10-07-projects.md`. Built without review stops at the user's request ("do not wait for approval").

## 1. Goal

Give every job a project file (docs/04): the project master with its services and contract value, the contract with its payment schedule and amendments, the project team, tasks (replacing the legacy task screens), task templates, and approval / permit tracking. Conversion of a won lead now creates the project too (docs/03 §5.7 step 2). Every screen meets the desktop standards (doc 00 §7.1–7.4) and the native mobile rules (doc 00 §7.6).

Estimates (05), invoices and receipts (06), purchases (07) and the GL (08) do not exist yet. Projects builds everything that does not need them and leaves hooks (P2, P22).

## 2. Decisions

| # | Decision |
|---|---|
| P1 | **Scope.** All of docs/04 except: the financial summary read model and snapshots (§8), the money KPIs (Invoiced, Received, Receivable, Cost to date, Budget cost, Gross profit, Margin %), the Estimates & Budget, Site, Billing, Costs and Ledger tabs, the New estimate / inspection / invoice / receipt / work order / expense actions, "Create invoice from milestone", schedule moves to INVOICED / PAID, PRJ-BR-06, PRJ-BR-07 and PRJ-BR-09, the receivable and draft-document parts of PRJ-BR-08, PRJ-AC-07, the reports (§11 → doc 10) and the legacy task import (doc 11). Those land with 05–08 and 10–11. |
| P2 | **Hooks for later modules.** `Project::allowsBilling()` / `allowsCosting()` read the status flags (PRJ-BR-07) and `Project::hasSignedContract()` (PRJ-BR-06) so 06–08 can call them. `payment_schedules.invoice_id`, `project_approvals.fee_expense_id` and `project_services.vat_rate_id` are nullable BIGINT with no FK and are not on any form. `ProjectCompletionChecks` is a registry like HRM `ExitChecks`: Projects registers open tasks and non-final approvals; 06 / 08 add receivable and draft documents. |
| P3 | **Project numbers** (PRJ-BR-01). `NumberSequenceService::next('project', ['bl_prefix' => $line->project_prefix])` on create (format `{bl_prefix}-{seq:4}`, already seeded). The business line and number cannot change after create. Routes use `{project:project_number}`. |
| P4 | **Customer rules** (PRJ-BR-02, CRM-BR-14). A project type with `is_internal` has no customer; any other type requires a customer that is not merged and not blocked. Internal business lines (Catalog `is_internal`) only allow internal project types, and internal types only internal business lines. |
| P5 | **Services and contract value** (PRJ-BR-03). `SaveProjectServices` replaces the service lines: `amount = round(qty × rate, 2) − discount` (≥ 0, brick/money, half-up). `contract_value = Σ amount` of lines not CANCELLED, recalculated in the same transaction. Removed lines are deleted (no billing references them yet; 06 will make lines referenced by invoices undeletable). A signed contract locks the services (PRJ-BR-04): only an approved amendment changes them. The project form edits services on create and while unsigned; the Overview tab shows delivery status, which `SetServiceStatus` changes (NOT_STARTED → IN_PROGRESS → DELIVERED with `delivered_on`; CANCELLED only through an amendment once signed). |
| P6 | **People.** Project manager is required, supervisor and support officer optional; all must be assignable employees (HR-BR-06, PRJ-BR-10) unless the value is unchanged. Saving a project makes sure each of them is an active team member with role PM, SUPERVISOR or SUPPORT_OFFICER (added if missing; a replaced person stays on the team until released). `projects.assigned_pm` goes to the new PM's linked user. |
| P7 | **Data scope** (docs/04 §2, PRJ-BR-18, PRJ-AC-05). `Project::scopeVisibleTo($user)`: `projects.projects.view_all` or super admin → all; with the setting `projects.pm_can_view_all`, a user whose employee is PM of any project → all; `view_own` → projects where the user's employee is PM, supervisor, support officer or an active team member, the user created it, or the user is the customer's account manager or acquirer (sales "own customers'"); otherwise nothing. `ProjectPolicy::view` checks one record the same way. |
| P8 | **Task data scope.** `Task::scopeVisibleTo($user)`: `projects.tasks.view_all` → all; `view_project` → tasks on projects the user can see plus own tasks; `view_own` → tasks where the user's employee is assignee, support officer or reviewer, or the user assigned it or watches it. General tasks (no project) are visible only through "own" or `view_all`. |
| P9 | **Project status** (docs/04 §6.1). Transitions by code: ENQUIRY → CONTRACTED; CONTRACTED → IN_PROGRESS; IN_PROGRESS ⇄ ON_HOLD; IN_PROGRESS → HANDED_OVER; HANDED_OVER → COMPLETED; any open status → CANCELLED; COMPLETED / CANCELLED → IN_PROGRESS only by reopen (`projects.projects.reopen`, reason required). ON_HOLD needs a hold reason; CANCELLED a cancel reason; HANDED_OVER a handover date; COMPLETED an actual end date and passing completion checks (P2) or a management override (a user with `projects.projects.close` and `view_all` gives an `override_reason`, PRJ-AC-06 for tasks and approvals). Non-system statuses added on Master Data are open statuses reachable from and to any open status. Phase can change in the same call or alone. Every change writes `project_status_histories` and fires `ProjectStatusChanged` / `ProjectPhaseChanged` inside the transaction. |
| P10 | **Contract** (docs/04 §3.4). One contract per project, created from the Contract tab as DRAFT (`projects.contracts.manage`). `deed_amount` follows `contract_value` while DRAFT. `SignContract` needs the deed amount to equal the contract value and the schedule Σ to equal the deed amount within ৳1 (PRJ-BR-05, PRJ-AC-02 shows the difference), sets SIGNED and moves the project from ENQUIRY to CONTRACTED when it is still ENQUIRY. TERMINATED is set by `TerminateContract` (reason) and unlocks nothing. Signed / amended contracts are read-only except `terms`. |
| P11 | **Amendments** (PRJ-AC-03). `SaveAmendment` stores a draft amendment with its proposed service lines in `project_contract_amendment_lines` (a copy of the current lines plus changes; `project_service_id` null for a new line). `ApproveAmendment` (`projects.contracts.manage`, a user other than the creator is not required) replaces the project services with the proposed lines, recalculates the contract value, sets `value_change`, `new_deed_amount`, `approved_by`, `approved_at`, the contract's deed amount and status AMENDED, and, when the value went up, adds a MANUAL schedule line "Amendment {n}" for the increase. When it went down, the Contract tab warns that the schedule no longer matches until pending lines are edited. Draft amendments can be deleted. |
| P12 | **Payment schedule** (docs/04 §3.5, §6.4). Edited as a whole by `SavePaymentSchedule` (milestone, trigger, trigger ref, due date, percent, amount). Percent and amount stay in sync against the deed amount (amount wins when both are typed; percent is derived to 4 places). Lines that are DUE are kept; once INVOICED / PAID exist (06) they will be locked. While the contract is SIGNED / AMENDED the Σ must still equal the deed amount ± ৳1. Trigger refs: PHASE → a project phase, APPROVAL → one of the project's approvals, TASK → one of its tasks, DATE → `due_date` required. `ScheduleTriggers::evaluate(Project)` moves PENDING lines whose trigger is satisfied to DUE: DATE when `due_date ≤ today`; PHASE when the project's phase sort order ≥ the ref's; APPROVAL when the approval is APPROVED; TASK when the task is DONE. MANUAL lines move with a "Mark due" button. It runs from listeners on `ProjectPhaseChanged`, `ApprovalStatusChanged` and `TaskCompleted`, and daily from the `EvaluateScheduleTriggers` job. Each line moved sends `projects.milestone_due` to the PM and to active users with the accountant or finance_manager role (PRJ-AC-04). |
| P13 | **Team** (docs/04 §3.6). `AddTeamMember` (assignable employee, role, allocation %, assigned on) refuses a second active row for the same employee and role; `ReleaseTeamMember` sets `released_on` and `is_active = false`. `projects.team_added` goes to the employee's linked user. Needs `projects.team.manage` and the project visible; a PM manages their own projects only (policy `manageTeam`). |
| P14 | **Tasks** (docs/04 §3.8). `CreateTask` / `UpdateTask`: number `task` sequence (`T-{yy}-{seq:5}`); due ≥ start; one level of sub-tasks (the parent has no parent and is on the same project, PRJ-BR-11); assignee, support officer and reviewer are assignable employees; without `projects.tasks.assign` the assignee can only be the actor's own employee or empty (or unchanged). A project task needs the project visible to the actor; engineers create tasks only on their own projects. Default status TODO, priority NORMAL, estimated hours from the type's default. Checklist items and watchers are saved with the task. `projects.tasks.assigned` goes to a new assignee's linked user unless that is the actor. |
| P15 | **Task status** (docs/04 §6.2, PRJ-BR-12, 13). `ChangeTaskStatus` transitions: TODO → IN_PROGRESS, BLOCKED, DONE, CANCELLED; IN_PROGRESS → REVIEW, BLOCKED, DONE, CANCELLED; REVIEW → IN_PROGRESS (reject), DONE, CANCELLED; BLOCKED → IN_PROGRESS, CANCELLED; DONE → IN_PROGRESS (reopen; clears completion and archive); CANCELLED → TODO (restore). Added statuses behave like IN_PROGRESS. Who: the assignee, support officer, reviewer, the project's PM, or a user with `projects.tasks.update` and `view_all`; completing or rejecting from REVIEW is limited to the reviewer, the PM or a `view_all` user. BLOCKED needs a reason. DONE sets `completed_at` / `completed_by` and `progress_pct = 100`; open checklist items need `confirm_open_checklist = true`, or block when `projects.block_complete_with_open_checklist` is on. REVIEW sends `projects.tasks.review_requested` to the reviewer (else the PM). DONE fires `TaskCompleted`. Starting a task whose predecessors are not DONE returns a warning the UI shows, but is allowed (§6.3 soft warning). `projects.completion_from_tasks` sets the project's `completion_pct` to the share of non-cancelled tasks that are DONE after every task status change. |
| P16 | **Task extras** (docs/04 §3.9). Checklist items toggle with `ToggleChecklistItem`. Comments (`AddTaskComment`, soft-deleted by their author or `projects.tasks.delete`) parse `@username`; each mentioned active user who can see the task gets `projects.tasks.mentioned`. `LogTaskTime` needs the actor's employee and adds hours (0.25–24) for a work date ≤ today; `actual_hours` is the Σ, kept by the action. Attachments use the shared `foundation.attachments` panel (Task is `Collaborative`). Watchers are users. |
| P17 | **Templates** (docs/04 §6.3, PRJ-AC-01). `SaveTaskTemplate` stores items with title, type, phase, role, start offset, duration, hours, depends on (an earlier item), and a checklist (one item per line, stored in `task_template_items.checklist` JSON as a list of strings). `ApplyTaskTemplate(project, template, startDate)` creates a task per item: start = project start (or today) + offset, due = start + duration, assignee = the first active team member with the item's role, else the PM; checklist copied; `task_dependencies` rows from the item dependencies. The project form offers "Apply task template" on create, defaulting to the first active template whose service is the first service line (else whose project type matches), when `projects.auto_apply_task_template` is on. The Tasks tab has "Apply template" for later use. `projects.task_templates.manage` edits templates; the template form previews dates from a sample start date. Seeded template: "Building Design & RAJUK Approval" with the twelve items of §3.9. |
| P18 | **Approvals** (docs/04 §3.10, PRJ-BR-15, 16). `SaveApproval` creates / edits an approval (authority, type, reference, responsible employee, dates, fee, notes) and on create copies the type's default checklist (`approval_types.default_checklist`, one item per line — replaces the `approval_type_checklists` table so it is editable on Master Data). `expected_on` defaults to `submitted_on + typical_days`. Status changes only through `AddApprovalEvent` (status, date, note, optional file): it writes the event (file uploaded as an attachment on the approval, `attachment_id` on the event), sets the status and the matching date (`submitted_on` for SUBMITTED / RESUBMITTED, `approved_on` for APPROVED), and fires `ApprovalStatusChanged`. APPROVED needs an approved date and at least one attachment on the approval or the event. Final statuses are terminal. `projects.approvals.status_changed` goes to the PM and the customer's account manager. Overdue = not final and `today > expected_on`. Managed with `projects.approvals.manage` on visible projects; engineers only on their own projects (policy). |
| P19 | **Jobs** (daily, `routes/console.php`). `EvaluateScheduleTriggers` (P12). `SendTaskAlerts`: `projects.tasks.due_tomorrow` per task to the assignee and one `projects.tasks.overdue` summary per user (assignees and PMs) with the count, guarded by a cache key per date so a rerun sends nothing (PRJ-BR-14 overdue rule, `projects.task_overdue_notify` gates it). `NotifyOverdueApprovals`: `projects.approvals.overdue` to the responsible employee's user and the PM once per overdue spell (`overdue_notified_at`, cleared by any event). `ArchiveDoneTasks`: DONE tasks completed more than `projects.archive_done_tasks_after_days` ago get `archived_at`. |
| P20 | **Notifications** (`PreferenceNotification`, `config/notifications.php`): `projects.assigned_pm`, `projects.team_added`, `projects.tasks.assigned`, `projects.tasks.mentioned`, `projects.tasks.review_requested`, `projects.tasks.due_tomorrow`, `projects.tasks.overdue` (database, mail where docs/04 §10 implies an alert), `projects.approvals.overdue`, `projects.approvals.status_changed`, `projects.milestone_due`. Recipients are employees' linked active users; employees without a login are skipped. |
| P21 | **Conversion** (docs/03 §5.7, CRM-AC-05, CRM-AC-06, CRM-BR-09). The wizard gets a Project step between Customer and Review: business line* (from the lead), project type*, name* (default "{customer name}, {site location}"), site address, location, services from the lead (estimated value as rate, qty 1, editable), start date, PM, apply template. "Skip project" shows only when `crm.allow_convert_without_project` is on. `ConvertLead` calls the Projects `CreateProject` action inside its transaction (with `source_lead_id` and the customer) and stores `converted_project_id`; a project failure rolls the whole conversion back. The `projects.projects.create` check is waived for conversion (the actor already holds `crm.leads.convert`). `LeadConverted` gains a nullable `project`. The wizard redirects to the project when the user can see it, else to the customer. `leads.converted_project_id` gets its FK. |
| P22 | **Other modules.** CRM: the customer page gets a Projects tab and a New project action (`projects/create?customer=`); "own" customers include customers of projects the user's employee is on (docs/03 §2), so `engineer` gets `crm.customers.view_own`; `CustomersMerging` moves the duplicate's projects (CRM-AC-10, project part); `DeleteCustomer` refuses customers with projects (CRM-BR-13); the customer timeline and Activities tab include the activities of the customer's projects; the lead page links the converted project. Activities: `project` joins the activity subjects, so projects get the quick-log and an Activities tab; `project_manager` and `engineer` get `crm.activities.view_own`, `create`, `update`. HRM: exit checks for open tasks assigned to the employee and open projects they manage (both informational, with links, HR-AC-03); the employee profile gets Projects and Tasks tabs; `EmployeeDeactivated` needs no listener because "PM inactive" is computed (project list filter and a badge on the project page). |
| P23 | **Lookups on Master Data** under a new `projects.master_data` resource (view / create / update / deactivate), in a "Projects setup" nav tree: project types (`is_internal`, `is_billable` bool), project statuses, project phases, project roles, task types (`default_estimated_hours` number), task statuses, task priorities, approval authorities, approval types (`typical_days` number, `default_checklist` textarea), approval statuses, hold reasons. Status flags (`is_open`, `is_closed`, `allows_billing`, `allows_costing`, `is_done`, `is_cancelled`, `is_final`, `is_success`) are seeder-set on system rows and not editable. `project_service_statuses`, `contract_statuses`, `schedule_triggers` and `schedule_statuses` are seeded and read-only (their codes drive behaviour), not on Master Data. |
| P24 | **Deletes** (every delete is soft, PRJ-BR-17). A project can be deleted (`projects.projects.delete`) only while ENQUIRY with no contract, tasks or approvals. Tasks are soft-deleted with `projects.tasks.delete` (sub-tasks with them). Templates are soft-deleted. Approvals cannot be deleted once they have events beyond the first. |
| P25 | **Exports and print.** Projects and tasks lists export the filtered rows to Excel (`projects.projects.export`; tasks use `projects.tasks.view_*`). The project sheet (§5.10) prints from `projects/{project}/print` with `x-print.letterhead`: project, customer, site, services, contract, schedule and team (financial summary with 06 / 08). |

## 3. Data model

Migrations in `database/migrations/projects/` (loaded by `ProjectsServiceProvider`), models in `app/Modules/Projects/Models`. All models are Auditable. FKs are `ON DELETE RESTRICT` except where noted. Morph aliases: `project_type`, `project_status`, `project_phase`, `project_role`, `task_type`, `task_status`, `task_priority`, `approval_authority`, `approval_type`, `approval_status`, `hold_reason`, `project`, `project_service`, `project_contract`, `project_contract_amendment`, `payment_schedule`, `project_employee`, `task`, `task_comment`, `task_time_log`, `task_template`, `project_approval`.

- **Lookups** ([LOOKUP] + extras of docs/04 §3.1, P23): `project_types`, `project_statuses`, `project_phases`, `project_roles`, `task_types`, `task_statuses`, `task_priorities`, `approval_authorities`, `approval_types` (+ `default_checklist` TEXT), `approval_statuses`, `hold_reasons`, `project_service_statuses`, `contract_statuses`, `schedule_triggers`, `schedule_statuses`.
- **`projects`**: per §3.2. `customer_id` FK customers nullable; `business_line_id`, `project_type_id`, `project_status_id`, `project_phase_id`, `branch_id`, `location_id`, `land_area_unit_id` (units), `hold_reason_id` FKs; `source_lead_id` FK leads nullable; `project_manager_id`, `supervisor_id`, `support_officer_id` FK employees. `legacy_project_ids` JSON. FULLTEXT MySQL only; search uses `LIKE`. `[AUDIT] [SOFT]`.
- **`project_services`**: per §3.3; `project_service_status_id` FK. `vat_rate_id` without FK (P2).
- **`project_contracts`**: per §3.4, `project_id` unique, `contract_status_id` FK, plus `terminated_at`, `termination_reason`.
- **`project_contract_amendments`**: per §3.4 plus `status` (`draft` | `approved`) VARCHAR(20) and `[AUDIT]`; unique (`project_contract_id`, `amendment_no`).
- **`project_contract_amendment_lines`**: `[STD]`, `project_contract_amendment_id` (cascade), `project_service_id` nullable FK, `service_id`, `description`, `quantity`, `unit_id`, `rate`, `discount_amount`, `amount`, `project_service_status_id`, `sort_order`.
- **`payment_schedules`**: per §3.5, `schedule_trigger_id`, `schedule_status_id` FKs, `invoice_id` without FK (P2), plus `due_at` TIMESTAMP nullable (when it moved to DUE).
- **`project_employees`**: per §3.6; index (`project_id`, `employee_id`, `project_role_id`, `is_active`).
- **`project_status_histories`**: per §3.7, cascade on project delete.
- **`tasks`** (docs/04 names it `project_tasks`; `tasks` because general tasks have no project): per §3.8. `parent_id` FK tasks. Indexes per §3.8. `[AUDIT] [SOFT]`.
- **`task_checklist_items`**, **`task_comments`** (`[SOFT]`), **`task_time_logs`**, **`task_watchers`** (unique task/user), **`task_dependencies`** (unique pair): per §3.9 / §6.3, cascade on task delete.
- **`task_templates`** (`[SOFT]`), **`task_template_items`** (cascade): per §3.9; `checklist` JSON list of strings; `depends_on_item_id` FK items nullable (null on delete).
- **`project_approvals`**: per §3.10 plus `overdue_notified_at` TIMESTAMP nullable; `fee_expense_id` without FK (P2). `[AUDIT]`.
- **`project_approval_events`**: per §3.10, `attachment_id` FK attachments null on delete.
- **`approval_checklist_items`**: per §3.10, cascade on approval delete, `attachment_id` null on delete.
- **`leads.converted_project_id`** FK projects (one migration in `database/migrations/projects/`).

`Project`, `Task` and `ProjectApproval` implement `Collaborative` (attachments, notes on Project; attachments on Task and ProjectApproval) with `isViewableBy` from their policies.

### 3.1 Seeds (`database/seeders/Projects/`, idempotent, by `code`)

- All lookups with the rows of docs/04 §3.1 plus: `project_service_statuses` NOT_STARTED · IN_PROGRESS · DELIVERED · CANCELLED; `contract_statuses` DRAFT · SIGNED · AMENDED · TERMINATED; `schedule_triggers` DATE · PHASE · APPROVAL · TASK · MANUAL; `schedule_statuses` PENDING · DUE · INVOICED · PAID · CANCELLED. Status rows are system rows with their flags; ENQUIRY / CONTRACTED / IN_PROGRESS / ON_HOLD / HANDED_OVER are open, COMPLETED and CANCELLED closed; CANCELLED has no billing and no costing; COMPLETED allows costing (with the warning of PRJ-BR-07, for 07 / 08). `approval_types.typical_days`: LUC 45, BP 90, OC 60, FIRE_NOC 30, ENV_CLEARANCE 60, HEIGHT_CLEARANCE 30, UP_SHEET 15, BANK_VALUATION 15, OTHER 30 (docs/04 open question 3), with default checklists for LUC, BP and OC (land deed, mutation, DCR, NID, tax receipt, soil report, drawings as fitting). Task type default hours: DESIGN 16, DRAWING 16, STRUCTURAL_CALC 12, SITE_VISIT 4, SURVEY 6, ESTIMATE 8, others 2–4.
- Settings group `projects`: the seven keys of docs/04 §4 plus `projects.block_complete_with_open_checklist` (bool, false). `default_status_on_conversion` stores the status code (`CONTRACTED`).
- The "Building Design & RAJUK Approval" template (P17).
- Permissions and grants (§4.1). Number sequences `project` and `task` already exist.

## 4. Architecture

### 4.1 Permissions (`app/Modules/Projects/permissions.php`)

```text
projects.projects.view_own | view_all | create | update | delete | change_status | close | reopen | export
projects.contracts.view | manage
projects.team.manage
projects.tasks.view_own | view_project | view_all | create | update | delete | assign | complete | archive
projects.task_templates.manage
projects.approvals.view | manage
projects.financials.view
projects.master_data.view | create | update | deactivate
```

| Role | Grants |
|---|---|
| super_admin | everything (Gate::before) |
| management | `projects.*` except `projects.projects.view_own`, `projects.tasks.view_own`, `projects.tasks.view_project` |
| project_manager | projects view_own, create, update, change_status, close, export; contracts view, manage; team manage; tasks view_project, create, update, delete, assign, complete, archive; task_templates manage; approvals view, manage; financials view; master_data view |
| engineer | projects view_own; tasks view_own, create, update, complete; approvals view, manage |
| sales_manager, sales_executive | projects view_own; tasks view_own, create, update, complete; approvals view |
| accountant, finance_manager | projects view_all; contracts view; tasks view_own, create, update, complete; approvals view; financials view |
| hr_admin | tasks view_own, create, update, complete |
| viewer | already `*.view` / `*.view_all` (Foundation) |

`projects.projects.view` and `projects.tasks.view` are gates meaning "any scope" (like CRM). `close` means a COMPLETED override; `reopen` stays with management.

### 4.2 Placement

- `app/Modules/Projects/` with `ProjectsServiceProvider` (migrations, morph map, Livewire location, policies, gates, listeners, completion-check registry, HRM exit checks), registered in `bootstrap/providers.php` after HRM.
- Routes `routes/modules/projects.php`, `app` middleware, `can:` per route, names `projects.*`:
  - `projects` (`projects.projects.index`), `projects/create`, `projects/{project:project_number}`, `/edit`, `/print`, `/amendments/create`, `/amendments/{amendment}/edit`
  - `projects/task-templates`, `/create`, `/{template}/edit` (`projects.templates.*`)
  - `projects/approvals` (`projects.approvals.index`), `projects/approvals/create?project=`, `projects/approvals/{approval}`, `/edit`
  - `tasks` (`projects.tasks.index`), `tasks/create`, `tasks/{task:task_number}`, `/edit`
- Livewire (class-based), views in `resources/views/livewire/projects/...`:
  - `Projects\Index`, `Projects\Form`, `Projects\Show` (tabs as child components: `Projects\ContractTab`, `Projects\TeamTab`, `Projects\TasksTab`, `Projects\ApprovalsTab`), `Projects\ChangeStatus` (sheet), `Projects\AmendmentForm`
  - `Tasks\Index` (list / board), `Tasks\Form`, `Tasks\Show`
  - `Templates\Index`, `Templates\Form`
  - `Approvals\Index`, `Approvals\Form`, `Approvals\Show`
- Actions (`app/Modules/Projects/Actions`): `CreateProject`, `UpdateProject`, `SaveProjectServices`, `SetServiceStatus`, `ChangeProjectStatus`, `DeleteProject`, `SaveContract`, `SignContract`, `TerminateContract`, `SaveAmendment`, `ApproveAmendment`, `DeleteAmendment`, `SavePaymentSchedule`, `MarkMilestoneDue`, `AddTeamMember`, `ReleaseTeamMember`, `ApplyTaskTemplate`, `CreateTask`, `UpdateTask`, `ChangeTaskStatus`, `DeleteTask`, `ArchiveTask`, `ToggleChecklistItem`, `AddTaskComment`, `DeleteTaskComment`, `LogTaskTime`, `SaveTaskTemplate`, `DeleteTaskTemplate`, `SaveApproval`, `AddApprovalEvent`, `ToggleApprovalChecklistItem`. Each authorizes against the actor, validates (throws `ValidationException`) and writes in `DB::transaction()`. Shared rules in `Concerns\ValidatesProjectInput`, `Concerns\ValidatesTaskInput`.
- Services: `ProjectAccess` (the actor's employee id and visibility subqueries used by both scopes), `ServiceLines` (amount maths), `ScheduleTriggers`, `ProjectCompletionChecks` + `Contracts\ProjectCompletionCheck`, `TaskTransitions`, `Mentions`.
- Jobs: `EvaluateScheduleTriggers`, `SendTaskAlerts`, `NotifyOverdueApprovals`, `ArchiveDoneTasks`.
- Events: `ProjectCreated`, `ProjectStatusChanged`, `ProjectPhaseChanged`, `TaskCompleted`, `ApprovalStatusChanged`. Listeners: `EvaluateTriggersOnPhase`, `EvaluateTriggersOnApproval`, `EvaluateTriggersOnTask`, `MoveMergedCustomerProjects` (CRM `CustomersMerging`).
- HRM exit checks: `ExitChecks\OpenTasksCheck`, `ExitChecks\ManagedProjectsCheck` in `app/Modules/Projects/Services`.

### 4.3 Navigation

The empty **Projects** group gets: Projects (`projects.projects.view`, mobile primary), My tasks → `projects.tasks.index` (`projects.tasks.view`, mobile primary), Approvals (`projects.approvals.view`), Task templates (`projects.task_templates.manage`), and "Master data" (tree) with the eleven lookups (`projects.master_data.view`). The detail modal list gains the project, task, template and approval create / edit / show routes. With three CRM primary items a user with CRM access still sees CRM first on the bottom bar (limit 3); PMs and engineers get Projects and Tasks.

## 5. Screens

All screens follow doc 00 §7.6 on mobile: fixed top bar with back and title, list rows instead of tables, bottom sheets for filters, row actions, status changes and pickers, sticky action bars on forms, a FAB for create, 44 px tap targets, `wire:navigate` everywhere, `x-lookup-select` / `x-employee-select` for FKs.

### 5.1 Projects — `projects`

- Desktop columns: Project #, Name, Customer, Business line, Type, Status, Phase, PM, Start, Expected end, Contract value, Open tasks, Overdue tasks, Approvals pending (the finance columns of docs/04 §5.1 arrive with 06 / 08). Filters: status, phase, business line, type, PM, customer, location (with descendants), start date range, has overdue tasks, approvals pending, internal / billable, PM inactive. Search: number, name, site address, customer name. Preset chips: My projects · Active · On hold · Approvals pending · Handed over not completed (Receivable > 0 with 06). Bulk: Change PM, Export.
- Mobile rows: number, name, customer, status badge, contract value, chevron; infinite scroll, filters and presets in a bottom sheet, FAB → new project.

### 5.2 Project form — `projects/create`, `projects/{project}/edit`

Sections General · Site · People · Dates · Services (create and while unsigned) · Task template (create only). Business line is fixed after create. Customer: a search over visible, non-blocked customers ("+ New" link to the customer form with `crm.customers.create`); hidden for internal types. `?customer=` preselects. Services grid: service, description, qty, unit, rate, discount, amount (live), and the contract value in the footer. Sticky Save bar on mobile.

### 5.3 Project detail — `projects/{project}`

- **Header:** number, name, customer link, status and phase badges, PM ("inactive" badge when the PM left). Actions: Edit, Change status, Add task, Log activity, Print project sheet (the 05–08 actions later). Mobile: one top-bar ⋮ opening an actions sheet; primary action (Add task) in a sticky bar.
- **KPI cards:** Contract value · Tasks done / total · Overdue tasks · Approvals pending · Next milestone due.
- **Tabs** (segmented control): Overview (details, services with delivery status, team summary, latest activities) · Contract & schedule · Team · Tasks (list / board / timeline; filters assignee, phase, status; Apply template) · Approvals (cards with status, dates, days elapsed vs typical) · Activities · Documents · Notes · History.

### 5.4 Change status — sheet on the project page (P9 fields).

### 5.5 Contract & schedule tab

Contract card (create, edit while DRAFT, Sign, Terminate), amendments list (New amendment page with the services grid prefilled, Approve, Delete draft), schedule grid (milestone, trigger, due, %, amount, status, "Mark due" for MANUAL) with an Edit schedule sheet (repeater) and a Σ vs deed indicator.

### 5.6 Team tab — members with role, allocation, dates; Add member sheet; Release (date) sheet; past members collapsed.

### 5.7 Tasks — `tasks`

Preset chips (the legacy menus): My tasks · Assigned by me · All · Overdue · Completed · Archived. Columns: Task #, File no., Customer, Entry date, Project, Project type, Title, Assignee, Support officer, Assigned by, Priority, Due, Status, Completed date / by (PRJ-AC-08). Filters: status, assignee, project, type, phase, priority, due range, important. Board view by status with drag (`wire:sort`, calls `ChangeTaskStatus`; refused moves snap back with a toast). Bulk: Reassign, Change status, Set due date, Archive, Export. Mobile: rows (number, title, project, due, status badge), board becomes status chips.

### 5.8 Task form — `tasks/create`, `tasks/{task}/edit` (detail modal on desktop)

Project (optional, `?project=` preselects), parent task, type*, phase, title*, description, file no., assignee, support officer, reviewer, priority*, important, start, due, estimated hours, checklist repeater, watchers.

### 5.9 Task detail — `tasks/{task}`

Header with number, title, status badge, priority, important flag; status buttons allowed for the user (Start · Submit for review · Complete · Block · Reject · Cancel · Reopen); predecessors warning; checklist with toggles; comments with @mentions; time log with Add hours sheet; sub-tasks; attachments; history.

### 5.10 Task templates — `projects/task-templates` list; form with the items repeater and a date preview.

### 5.11 Approvals — `projects/approvals`

Cross-project list: Project, Authority, Type, Reference, Responsible, Status, Submitted, Expected, Days pending, Overdue badge. Filters: authority, type, status, responsible, overdue. Detail `projects/approvals/{approval}`: card with dates and days elapsed vs typical, event timeline, checklist toggles, Add event sheet (status, date, note, file), attachments. Form `projects/approvals/create?project=`, `/edit`.

### 5.12 Conversion wizard — the CRM wizard gains the Project step (P21); `x-ui.stepper` on desktop, full-screen steps on mobile.

## 6. Error handling

- Action rule failures throw `ValidationException`, shown inline or as a toast in sheets and on the board.
- 403 comes from route `can:` middleware, policies and `authorize()` in every Livewire action. Nav items and actions are hidden without the permission.
- Refused with a message: customer on an internal project or missing on a billable one, blocked customer, changing services of a signed contract, signing with a schedule mismatch (with the difference), an invalid status move or missing conditional field, completing with open tasks or approvals without an override, a second active team row for the same role, a task due before its start, a sub-task of a sub-task, assigning others without `assign`, completing from REVIEW as the assignee, completing with open checklist items without confirming, APPROVED without a date or attachment, any event on a final approval, deleting a non-empty project.
- Jobs are idempotent (P19).

## 7. Testing

Pest feature tests under `tests/Feature/Projects/` (in-memory SQLite).

| Area | Cases |
|---|---|
| Access | routes 403 / 200 by permission; nav items; PRJ-AC-05 (engineer on two projects sees two; own tasks only); PM own vs `pm_can_view_all`; sales see own customers' projects |
| Seeders | idempotent; lookups and flags; settings; template; permissions and grants |
| Projects | number from the business line prefix, immutable; customer rules (PRJ-BR-02, CRM-BR-14); contract value from services (PRJ-BR-03); people must be assignable; PM / supervisor / support added to the team; `assigned_pm` notification; template applied on create (PRJ-AC-01) |
| Status | allowed and refused moves; conditional fields; history; events; COMPLETED blocked by open tasks / approvals and overridden with reason (PRJ-AC-06 task part); reopen needs permission |
| Contract | sign refused on mismatch with the difference (PRJ-AC-02); sign moves ENQUIRY to CONTRACTED; services locked after signing; amendment approval updates contract value and deed and adds the schedule line (PRJ-AC-03) |
| Schedule | percent / amount sync; Σ rule while signed; triggers DATE, PHASE, APPROVAL (PRJ-AC-04 with notifications), TASK, MANUAL |
| Team | add / release; duplicate active role refused; notification |
| Tasks | numbers; due ≥ start; one sub-task level; assign rule; scope; transitions and who may make them (PRJ-BR-12); review rules; checklist confirm / block (PRJ-BR-13); predecessors warning; completion % setting; mentions; time log Σ; delete / archive |
| Approvals | default checklist copied; expected date (PRJ-BR-15); events set status and dates; APPROVED needs date and attachment (PRJ-BR-16); final is terminal; overdue |
| Jobs | trigger job; due tomorrow and overdue summary once a day; overdue approvals once per spell; archive after N days |
| Conversion | project created with services from the lead and linked (CRM-AC-05); failure rolls back (CRM-AC-06); skip only with the setting; redirect |
| CRM / HRM | customer projects tab; merge moves projects; customer with projects not deletable; project activities on the customer timeline; exit checks list open tasks and managed projects; employee Projects / Tasks tabs |
| Screens | list filters and presets; form saves; detail tabs render; board move; exports; print |

No browser tests. When the build is done, the screens are listed for the user to check at 390×844 and at desktop width.

## 8. Out of scope

Everything in P1, plus customer portal / SMS updates (docs/04 open question 4), a contract PDF template (open question 2: contracts print as part of the project sheet), and recurring tasks.
