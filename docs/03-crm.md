# SOC ERP v2 — 03 · CRM (Leads, Pipeline, Activities, Sales Teams, Customers, Conversion)

**Build phase:** 2 · **Depends on:** 01, 02 · **Used by:** 04, 06, 10
**Replaces (legacy):** Client Management › Prospect Entry, Prospect List, Client List, Team Member, Report; Settings › Team Entry; dashboard Prospect List / Reminder

---

## 1. Purpose & Scope

- Capture every enquiry as a **lead** with source, owner, team, business line and services of interest.
- Move leads through a configurable **pipeline** with activities, follow-ups and reminders.
- Organise sales staff into **sales teams** with managers.
- Convert won leads into **customers** and **projects** while keeping full lead history.
- Maintain the **customer** master with contacts, balances and timeline.

Out of scope: quotations with pricing (handled as project contract in 04 for v2; a formal quotation document is listed as a future enhancement), marketing campaigns (future).

---

## 2. Actors & Permissions

```text
crm.leads.view_own | view_team | view_all | create | update | delete | assign | convert | export | import
crm.activities.view_own | view_team | view_all | create | update | delete
crm.customers.view_own | view_team | view_all | create | update | delete | export | merge
crm.teams.view | manage
crm.reports.view
```

| Permission | super_admin | management | sales_manager | sales_executive | project_manager | accountant | engineer |
|---|---|---|---|---|---|---|---|
| leads view | all | all | team | own | — | — | — |
| leads create/update | ✓ | ✓ | ✓ | ✓ (own) | — | — | — |
| leads assign | ✓ | ✓ | ✓ (within team) | — | — | — | — |
| leads convert | ✓ | ✓ | ✓ | ✓ (own) | — | — | — |
| leads delete | ✓ | ✓ | — | — | — | — | — |
| customers view | all | all | team | own | all | all | own projects |
| customers create/update | ✓ | ✓ | ✓ | ✓ | — | ✓ (finance fields) | — |
| customers merge | ✓ | ✓ | — | — | — | — | — |
| teams manage | ✓ | ✓ | — | — | — | — | — |

"Own" lead = `assigned_to = user`. "Team" = assigned to any member of a team the user manages. "Own" customer = `account_manager_user_id = user` or user is on any of the customer's projects.

---

## 3. Data Model

### 3.1 Lookups ([LOOKUP] + extra columns)

| Table | Extra columns | Seed |
|---|---|---|
| `lead_sources` | `requires_referrer TINYINT` | LEAFLET Leaflet · F2F Face to Face · FACEBOOK Facebook · GOOGLE Google · YOUTUBE YouTube · REFERENCE Friend & Reference (requires_referrer) · WEBSITE Website · PHONE Phone Call · WHATSAPP WhatsApp · WALKIN Walk-in · EXISTING Existing Customer (requires_referrer) · AGENT Agent (requires_referrer) · OTHER Other |
| `lead_statuses` | `probability_pct DECIMAL(5,2)`, `is_won`, `is_lost`, `is_closed` | NEW New 10 · CONTACTED Contacted 20 · QUALIFIED Qualified 30 · MEETING Meeting 40 · SITE_VISIT Site Visit 50 · PROPOSAL Proposal Sent 60 · NEGOTIATION Negotiation 75 · WON Won 100 (is_won, is_closed, system) · LOST Lost 0 (is_lost, is_closed, system) |
| `lead_priorities` | — | LOW · NORMAL · HIGH · URGENT |
| `lead_levels` | — | ENTRY Entry Level · MID Mid Level · TOP Top Level (legacy) |
| `lost_reasons` | — | Price too high · Chose competitor · Project postponed · No response · Not qualified / no budget · Land/legal issue · Other |
| `activity_types` | `icon`, `requires_duration`, `counts_as_contact` | CALL · MEETING · EMAIL · WHATSAPP · SMS · SITE_VISIT · OFFICE_VISIT · NOTE · FOLLOW_UP |
| `activity_outcomes` | — | Interested · Not interested · Call back · No answer · Wrong number · Meeting fixed · Proposal requested |
| `customer_types` | — | INDIVIDUAL · COMPANY · ORGANIZATION · GOVERNMENT · BANK · NGO · OTHER |
| `customer_statuses` | `is_blocked` | ACTIVE · INACTIVE · BLOCKED (is_blocked) |
| `payment_terms` | `days INT` | IMMEDIATE 0 · NET7 · NET15 · NET30 · MILESTONE (0, use schedule) |

### 3.2 `sales_teams`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| name | VARCHAR(80) | no | UNIQUE (legacy Team A–D) |
| manager_user_id | BIGINT | yes | FK users |
| business_line_id | BIGINT | yes | optional focus |
| monthly_target_amount | DECIMAL(18,2) | yes | |
| is_active | TINYINT(1) | no | |
| [AUDIT] | | | |

### 3.3 `sales_team_members`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT | no | |
| sales_team_id | BIGINT | no | FK |
| user_id | BIGINT | no | FK |
| joined_on | DATE | no | |
| left_on | DATE | yes | |
| Index | | | UNIQUE(user_id) WHERE left_on IS NULL — enforce in app: one active team per user |

### 3.4 `leads`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| lead_number | VARCHAR(40) | no | UNIQUE, `L-000124` |
| lead_date | DATE | no | enquiry date (legacy `date`) |
| name | VARCHAR(150) | no | contact person / client name |
| company_name | VARCHAR(200) | yes | legacy `org_name` |
| phone | VARCHAR(30) | no | normalised; index |
| office_phone | VARCHAR(30) | yes | legacy `org_mobile` |
| whatsapp | VARCHAR(30) | yes | legacy `w_number`; index |
| email | VARCHAR(150) | yes | index |
| address | TEXT | yes | |
| location_id | BIGINT | yes | FK locations |
| lead_source_id | BIGINT | no | FK |
| referrer_type | VARCHAR(20) | yes | `customer`,`employee`,`agent`,`other` |
| referrer_id | BIGINT | yes | |
| referrer_name | VARCHAR(150) | yes | free text when not in system |
| business_line_id | BIGINT | yes | FK (legacy client type) |
| lead_level_id | BIGINT | yes | FK |
| lead_status_id | BIGINT | no | FK, default NEW |
| lead_priority_id | BIGINT | no | default NORMAL |
| sales_team_id | BIGINT | yes | derived from assignee at assignment time |
| assigned_to | BIGINT | yes | FK users |
| assigned_at | TIMESTAMP | yes | |
| expected_value | DECIMAL(18,2) | yes | |
| expected_close_date | DATE | yes | |
| site_location_text | VARCHAR(255) | yes | plot / site address if different |
| land_area | VARCHAR(60) | yes | e.g. "5 katha" |
| floors_planned | SMALLINT | yes | |
| next_follow_up_at | DATETIME | yes | denormalised from next open follow-up; index |
| last_activity_at | DATETIME | yes | denormalised |
| lost_reason_id | BIGINT | yes | |
| lost_note | TEXT | yes | |
| won_at / lost_at | TIMESTAMP | yes | |
| converted_customer_id | BIGINT | yes | FK customers |
| converted_project_id | BIGINT | yes | FK projects |
| converted_at | TIMESTAMP | yes | |
| converted_by | BIGINT | yes | FK users |
| legacy_client_id | VARCHAR(40) | yes | migration trace |
| notes | TEXT | yes | |
| [AUDIT] [SOFT] | | | |

Indexes: `lead_status_id`, `assigned_to`, `sales_team_id`, `lead_source_id`, `business_line_id`, `lead_date`, `next_follow_up_at`, `phone`, `whatsapp`, FULLTEXT(name, company_name).

### 3.5 `lead_services`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT | no | |
| lead_id | BIGINT | no | FK cascade delete |
| service_id | BIGINT | no | FK |
| estimated_value | DECIMAL(18,2) | yes | |
| notes | VARCHAR(255) | yes | |
| UNIQUE(lead_id, service_id) | | | |

`leads.expected_value` = SUM(estimated_value) when lines have values, else manual.

### 3.6 `lead_status_histories`

`id, lead_id, from_status_id, to_status_id, changed_by, changed_at, note` — feeds pipeline velocity reports.

### 3.7 `lead_assignment_histories`

`id, lead_id, from_user_id, to_user_id, assigned_by, assigned_at, reason`.

### 3.8 `crm_activities`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| subject_type | VARCHAR(20) | no | `lead`, `customer`, `project` |
| subject_id | BIGINT | no | |
| activity_type_id | BIGINT | no | |
| title | VARCHAR(200) | no | |
| description | TEXT | yes | |
| scheduled_at | DATETIME | yes | for planned activities / follow-ups |
| completed_at | DATETIME | yes | null = open |
| duration_minutes | SMALLINT | yes | |
| outcome_id | BIGINT | yes | FK activity_outcomes |
| owner_user_id | BIGINT | no | who must do it |
| reminder_at | DATETIME | yes | |
| reminder_sent_at | DATETIME | yes | |
| location_text | VARCHAR(255) | yes | for meetings/site visits |
| [AUDIT] [SOFT] | | | |

Indexes: (subject_type, subject_id), (owner_user_id, completed_at, scheduled_at), (reminder_at, reminder_sent_at).

State: **Open** (completed_at null, scheduled_at future) · **Overdue** (open and scheduled_at < now) · **Done**.

### 3.9 `customers`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| customer_number | VARCHAR(40) | no | UNIQUE `C-000057` |
| customer_type_id | BIGINT | no | |
| name | VARCHAR(200) | no | person or organisation display name |
| company_name | VARCHAR(200) | yes | |
| phone | VARCHAR(30) | no | normalised; index |
| alternate_phone | VARCHAR(30) | yes | |
| whatsapp | VARCHAR(30) | yes | |
| email | VARCHAR(150) | yes | |
| address | TEXT | yes | |
| location_id | BIGINT | yes | |
| nid_or_reg_no | VARCHAR(40) | yes | NID / trade licence / reg. no. (for agreements) |
| tin | VARCHAR(30) | yes | |
| bin | VARCHAR(30) | yes | |
| business_line_id | BIGINT | yes | primary business line |
| account_manager_user_id | BIGINT | yes | |
| source_lead_id | BIGINT | yes | first lead |
| lead_source_id | BIGINT | yes | copied from first lead (for reports) |
| acquired_by_user_id | BIGINT | yes | salesperson at conversion |
| payment_term_id | BIGINT | yes | |
| credit_limit | DECIMAL(18,2) | yes | 0/null = no limit |
| receivable_account_id | BIGINT | yes | null → AR control from settings |
| customer_status_id | BIGINT | no | default ACTIVE |
| is_also_vendor | TINYINT(1) | no | informational |
| legacy_client_id | VARCHAR(40) | yes | |
| notes | TEXT | yes | |
| [AUDIT] [SOFT] | | | |

Indexes: `phone`, `email`, `customer_status_id`, `account_manager_user_id`, FULLTEXT(name, company_name).

### 3.10 `customer_contacts`

`[STD], customer_id, name, designation, phone, email, is_primary, notes, [SOFT]` — one primary per customer.

### 3.11 Computed customer figures (view or cached columns refreshed by events)

| Figure | Formula |
|---|---|
| Total contracted | SUM(projects.contract_value) where project not cancelled |
| Total invoiced | SUM(invoices.total_amount) approved, not cancelled |
| Total received | SUM(receipts.amount) posted |
| Outstanding | Total invoiced − allocated receipts − credit notes |
| Unapplied advance | receipts not allocated |
| Overdue | Σ open invoice balance where due_date < today |

Source of truth for balances is the GL (08): customer AR sub-ledger = Σ(debit − credit) of journal lines on AR control accounts with `customer_id`. Cached figures are for display only and must reconcile.

---

## 4. Settings (group `crm`)

| Key | Type | Default |
|---|---|---|
| crm.duplicate_check_fields | json | `["phone","whatsapp","email"]` |
| crm.auto_assign_mode | string | `none` (`none`, `round_robin_team`) |
| crm.follow_up_required_on_status | json | `["CONTACTED","QUALIFIED","MEETING","SITE_VISIT","PROPOSAL","NEGOTIATION"]` |
| crm.stale_lead_days | int | 14 (no activity → flagged) |
| crm.reminder_lead_minutes | int | 30 |
| crm.lost_reason_required | bool | true |
| crm.allow_convert_without_project | bool | false |

---

## 5. Screens

### 5.1 Leads — List (`/crm/leads`)
Views: **List** and **Kanban** (columns = active open statuses by sort order; drag card → status change modal if rules require input).

List columns: Lead #, Date, Name, Company, Phone, Source, Business line, Services, Level, Status, Priority, Assigned to, Team, Expected value, Next follow-up (red if overdue), Last activity, Age (days).

Filters: status (multi), source, business line, service, level, priority, assigned to, team, location (tree), date range (lead date / created), follow-up (today / overdue / none), stale, converted (yes/no).

Saved views (seeded): *My open leads*, *Today's follow-ups*, *Overdue follow-ups*, *Unassigned*, *Won this month*, *Lost this month*.

Bulk: Assign, Change status (non-closing only), Export, Add follow-up.

Kanban card: name, company, phone, services chips, expected value, next follow-up, owner avatar. Column header shows count and Σ expected value.

### 5.2 Leads — Form (`/crm/leads/create`, `/edit`)

Section **Contact**: Name*, Company, Phone* (live duplicate check), WhatsApp (☐ same as phone), Office phone, Email, Address, Location (tree select).

Section **Enquiry**: Lead date* (default today), Source*, Referrer (shown when source requires: search customers/employees or type name), Business line, Services* (multi with optional estimated value per service), Level, Priority, Expected value, Expected close date, Site location, Land area, Floors planned, Notes.

Section **Assignment**: Assigned to (users with `crm.leads.view_own`; default = creator if sales role), Team (read-only, derived).

Section **First follow-up** (create only): Activity type, Date/time, Note — required if setting says so for initial status.

Duplicate check panel: when phone/WhatsApp/email matches an existing lead or customer, show matches (number, name, status, owner) with actions **Open existing**, **Add as new enquiry for this customer** (links `referrer` = existing customer, source EXISTING), **Create anyway** (requires reason; audited).

### 5.3 Lead — Detail (`/crm/leads/{id}`)
Header: lead #, name, status badge, priority, owner; actions: Edit, Log activity, Schedule follow-up, Change status, Assign, **Mark Won → Convert**, Mark Lost, Print profile.

Pipeline bar (clickable stages).

Tabs: **Overview** (all fields, services) · **Activities** (timeline: open first, then done; quick-log) · **Documents** · **Notes** · **History** (status + assignment + audit).

Right rail: age, days in current status, last activity, next follow-up, expected value, conversion links (customer/project) when converted.

### 5.4 Activity quick-log (modal, reusable on lead, customer, project)
Fields: Type*, Title* (auto from type), Description, When (done now / scheduled), Duration (if type requires), Outcome, Owner (default me), Reminder (none / 15 min / 30 min / 1 h / 1 day before), **Schedule next follow-up** (date/time + type) — creates second open activity.

### 5.5 My Activities (`/crm/activities`)
Tabs: Overdue · Today · Upcoming · Done. Columns: when, type, title, subject (link), owner, outcome. Actions: Mark done (opens outcome + next follow-up), Reschedule.

Calendar view (week/month) of scheduled activities.

### 5.6 Change status modal
- To any open status: optional note; if status in `follow_up_required_on_status` and no open follow-up exists → follow-up required.
- To **LOST**: lost reason* (if setting), note.
- To **WON**: launches conversion wizard (5.7). Status only becomes WON when wizard completes.

### 5.7 Conversion wizard
Step 1 **Customer**: system searches customers by phone/email/name.
- Option A: Link existing customer (select).
- Option B: Create new customer — prefilled from lead (name, company, phones, email, address, location, business line, source); user completes customer type*, NID/reg no., TIN, payment term.

Step 2 **Project**: (skippable only if `allow_convert_without_project`)
Business line* (from lead), Project type*, Project name* (default "{customer name}, {site location}"), Site address, Location, Services (from lead services; editable values), Contract/deed amount, Start date, Project manager, Notes.

Step 3 **Review & Confirm**: summary; **Convert** button.

Result: customer (new or linked), project created (04 `CreateProject` action), lead updated to WON with converted ids, activities on lead remain and are visible from customer timeline. Redirect to project.

### 5.8 Sales Teams (`/crm/teams`)
List: team, manager, members count, business line, target, open leads, won this month. Form: name*, manager*, business line, monthly target, members (add with joined date; remove sets left date).

### 5.9 Customers — List (`/crm/customers`)
Columns: Customer #, Name, Company, Type, Phone, Location, Account manager, Projects (count), Contracted, Invoiced, Received, Outstanding, Overdue, Status.
Filters: type, status, account manager, business line, location, has outstanding, has overdue, source.
Bulk: Export, change account manager.

### 5.10 Customer — Form
Fields per §3.9 grouped: Identity · Contact · Classification (type, business line, account manager, status) · Finance (payment term, credit limit, TIN/BIN, receivable account — finance roles only) · Contacts grid (§3.10).

### 5.11 Customer — Detail
Header: number, name, status, account manager; actions: Edit, Log activity, New project, New invoice (06), Receive payment (06), Statement (PDF/Excel), Merge (admin).
Tabs: **Overview** · **Projects** (list with status, contract, billed, received, due) · **Invoices** · **Receipts** · **Statement** (running balance from GL, date filter) · **Activities** (customer + all its leads + projects) · **Leads** (origin and any further enquiries) · **Documents** · **History**.
Right rail: contracted / invoiced / received / outstanding / overdue / unapplied advance; credit limit usage bar.

### 5.12 Customer merge (admin)
Pick surviving customer and duplicate → preview of records to move (leads, projects, invoices, receipts, activities, attachments) → confirm. Duplicate soft-deleted with `merged_into_id`. Journal lines' `customer_id` updated in the same transaction; audit row per moved table.

---

## 6. Workflows

### 6.1 Lead state machine

```text
NEW → CONTACTED → QUALIFIED → MEETING → SITE_VISIT → PROPOSAL → NEGOTIATION
  │        │           │          │          │           │           │
  └────────┴───────────┴──────────┴──────────┴───────────┴───────────┴──→ WON (via conversion)
  └────────────────────────────────────────────────────────────────────→ LOST
LOST → (reopen) → previous open status   [crm.leads.update + reason]
WON  → no reopen (converted records exist)
```
Skipping forward is allowed; moving backward is allowed for open statuses (logged).

### 6.2 Follow-up / reminder cycle
1. Activity scheduled with `reminder_at`.
2. Scheduler every 5 min: activities where `reminder_at <= now` and `reminder_sent_at is null` and not completed → notify owner (in-app + email/SMS per preference) → set `reminder_sent_at`.
3. Daily digest at `notifications.daily_digest_time`: each user gets today's and overdue follow-ups; each sales manager gets team overdue counts.
4. Completing an activity updates `leads.last_activity_at` and recalculates `next_follow_up_at`.

### 6.3 Auto-assignment (if `round_robin_team`)
New lead with team chosen but no assignee → assign to active member of that team with fewest open leads; ties → longest since last assignment.

---

## 7. Business Rules

| ID | Rule |
|---|---|
| CRM-BR-01 | Lead requires name, phone, source, ≥ 1 service, lead date ≤ today. |
| CRM-BR-02 | Phone must be a valid BD mobile (`^(?:\+?88)?01[3-9]\d{8}$`) or landline; stored as `+8801…`. |
| CRM-BR-03 | Duplicate detection on normalised phone/WhatsApp/email across open leads and customers; creating a duplicate requires an explicit reason (audited). |
| CRM-BR-04 | Source with `requires_referrer` needs a referrer id or referrer name. |
| CRM-BR-05 | Sales executives can only assign leads to themselves; managers within their team; management anyone. |
| CRM-BR-06 | Every status change writes `lead_status_histories`; every reassignment writes `lead_assignment_histories` and notifies the new owner. |
| CRM-BR-07 | Moving to a status listed in `crm.follow_up_required_on_status` requires at least one open follow-up activity. |
| CRM-BR-08 | LOST requires lost reason when `crm.lost_reason_required`. |
| CRM-BR-09 | WON is only set by the conversion action; the action is atomic (customer + project + lead update in one transaction). |
| CRM-BR-10 | Converted leads are read-only except notes/activities/documents. |
| CRM-BR-11 | A lead is **stale** if open and `last_activity_at` (or created_at) older than `crm.stale_lead_days`. |
| CRM-BR-12 | Customer phone unique among active customers (warn + reason override for shared family numbers). |
| CRM-BR-13 | Customers with any invoice, receipt or project cannot be deleted; only status INACTIVE/BLOCKED. |
| CRM-BR-14 | BLOCKED customers cannot get new projects or invoices (receipts still allowed). |
| CRM-BR-15 | Credit limit (if > 0): approving an invoice that makes outstanding exceed limit requires `sales.invoices.override_credit` (06). |
| CRM-BR-16 | `customers.lead_source_id` and `acquired_by_user_id` are set from the **first** converted lead and never overwritten by later leads (attribution). |
| CRM-BR-17 | Repeat business: a new lead for an existing customer is converted by linking that customer; conversion counts per lead. |
| CRM-BR-18 | Deleting a lead (admin only) is soft; leads with conversions cannot be deleted. |

---

## 8. Integration

| Direction | Event / call | Notes |
|---|---|---|
| Out | `LeadConverted(lead, customer, project)` | Projects module creates project via `CreateProject` action (called inside conversion transaction) |
| Out | `CustomerCreated` | Accounting: customer usable as AR party |
| In | `InvoiceApproved`, `ReceiptPosted`, `CreditNoteApproved` | refresh customer cached figures |
| In | `ProjectStatusChanged` | logged to customer timeline |
| Out | Activity timeline API `Timeline::for($customer)` | merges activities from leads, projects, invoices, receipts |

---

## 9. Notifications

| Key | Trigger | To |
|---|---|---|
| `crm.lead_assigned` | lead assigned / reassigned | new owner |
| `crm.follow_up_reminder` | reminder time reached | activity owner |
| `crm.daily_digest` | daily | each sales user; managers get team summary |
| `crm.lead_won` | conversion done | sales manager, management |
| `crm.lead_stale` | lead becomes stale (daily check) | owner, manager |

---

## 10. Reports (detail in 10)
Lead Source · Pipeline (count and value by stage) · Conversion funnel · Conversion by source / team / salesperson / business line / service · Salesperson performance (leads, activities, won count, won value) · Follow-up compliance (done on time %, overdue) · Lost leads by reason · Area-wise leads · Lead aging · Customer list with balances · Customer acquisition by month.

---

## 11. Components (indicative)

```text
Livewire: Crm\Leads\Index (list+kanban), Crm\Leads\Form, Crm\Leads\Show,
          Crm\Leads\ConvertWizard, Crm\Activities\QuickLog, Crm\Activities\MyActivities,
          Crm\Activities\Calendar, Crm\Teams\Index, Crm\Teams\Form,
          Crm\Customers\Index, Crm\Customers\Form, Crm\Customers\Show, Crm\Customers\Merge
Actions:  CreateLead, UpdateLead, AssignLead, ChangeLeadStatus, MarkLeadLost, ReopenLead,
          ConvertLead, LogActivity, CompleteActivity, CreateCustomer, UpdateCustomer,
          MergeCustomers, FindDuplicates
Jobs:     SendDueReminders (every 5 min), SendDailyDigest, FlagStaleLeads
```

---

## 12. Acceptance Criteria

| ID | Criterion |
|---|---|
| CRM-AC-01 | Entering a phone that belongs to an existing customer shows the match before save and offers "Add as new enquiry for this customer". |
| CRM-AC-02 | A sales executive sees only their own leads; their manager sees all leads of the team; management sees all. |
| CRM-AC-03 | Dragging a lead to "Meeting" without any open follow-up opens a modal that requires a follow-up. |
| CRM-AC-04 | Marking lost without a reason is refused. |
| CRM-AC-05 | Converting lead L-000124 creates customer C-000057 and project SOC-BD-0103 in one step; the lead shows links to both; the customer's timeline shows all lead activities. |
| CRM-AC-06 | If project creation fails during conversion, no customer is created and lead stays in its previous status. |
| CRM-AC-07 | A follow-up scheduled for 15:00 with 30-min reminder notifies the owner at 14:30 (±5 min). |
| CRM-AC-08 | Pipeline Kanban column totals equal the Pipeline report for the same filters. |
| CRM-AC-09 | Customer outstanding on the detail page equals the AR sub-ledger balance from the GL. |
| CRM-AC-10 | Merging two customers moves all projects, invoices and receipts; statement of surviving customer equals sum of both previous statements. |

---

## 13. Open Questions

1. Confirm legacy source code mapping (`F`, `FF`, `L`, `FB`, others).
2. Should agents be stored as a separate entity with commission rates?
3. Is round-robin auto-assignment wanted or do managers assign manually?
4. Is a formal priced **quotation** document needed in v2 (PDF to customer), or is the contract on the project enough?
5. Should leads capture land/plot details (area, road width, floors) as structured fields for design jobs?
