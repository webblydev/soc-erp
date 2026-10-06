# CRM — Design

**Phase:** 2 (one sub-project covering all of docs/03)
**Source specs:** `docs/00-index-and-conventions.md`, `docs/03-crm.md`
**Builds on:** `docs/superpowers/specs/2026-10-06-admin-screens-design.md`, `docs/superpowers/specs/2026-10-06-shared-services-design.md`, `docs/superpowers/specs/2026-10-06-catalog-design.md`
**Date:** 06 Oct 2026
**Status:** Approved, 2026-10-06. Plan: `docs/superpowers/plans/2026-10-06-crm.md`.

## 1. Goal

Capture every enquiry as a lead, move it through the pipeline with activities and reminders, organise sales staff into teams, and convert won leads into customers (docs/03). Every screen meets the desktop standards (doc 00 §7.1–7.3) and the native mobile rules (doc 00 §7.6).

Projects (04), invoices and receipts (06) and the GL (08) do not exist yet. CRM builds everything that does not need them and leaves clear hooks for the modules that add the rest (R1, R2).

## 2. Decisions

| # | Decision |
|---|---|
| R1 | **Conversion creates or links the customer only** (user's choice). The wizard has two steps: Customer, then Review & Confirm. `ConvertLead` links or creates the customer and sets the lead to WON with `converted_customer_id`, `converted_at` and `converted_by` in one transaction (CRM-BR-09). It then fires `LeadConverted(lead, customer)`. `leads.converted_project_id` is a nullable `BIGINT UNSIGNED` with no FK (Catalog C1). 04 adds the Project step, the FK, `crm.allow_convert_without_project` (seeded now as `false` but not read yet), the redirect to the project, and CRM-AC-05 / CRM-AC-06 for the project part. Until then the wizard redirects to the customer. |
| R2 | **No money figures yet** (user's choice). The customer list and detail page show no contracted, invoiced, received, outstanding, overdue or advance figures, and the Projects, Invoices, Receipts and Statement tabs and the New project / New invoice / Receive payment / Statement actions are left out. 04, 06 and 08 add them. `receivable_account_id` is a nullable BIGINT with no FK and is not on the form (08). CRM-BR-14 and CRM-BR-15 and CRM-AC-09 are checked by 04 and 06. |
| R3 | **Phone numbers.** `App\Support\Phone` takes over `User::normalisePhone` (User keeps calling it). Spaces, dashes and brackets are stripped and a `+880` / `880` prefix becomes `0`, so numbers are stored in the local `01XXXXXXXXX` form. This differs from CRM-BR-02 (`+8801…`) on purpose: users already store the local form and duplicate checks compare like with like. Valid values are a BD mobile `^01[3-9]\d{8}$` or a landline `^0[2-9]\d{6,9}$` after normalising. Applies to lead `phone`, `whatsapp`, `office_phone` and customer `phone`, `alternate_phone`, `whatsapp` and contact phones. |
| R4 | **Lookups use the Master Data screen** under a new `crm.master_data` permission resource, shown in a "CRM setup" nav tree (like Catalog setup). Extra fields: `lead_sources.requires_referrer` (bool), `lead_statuses.probability_pct` (number 0–100), `activity_types.icon` (text, a lucide name), `requires_duration` and `counts_as_contact` (bool), `payment_terms.days` (number). `lead_statuses.is_won/is_lost/is_closed` and `customer_statuses.is_blocked` are **not** editable: they are set by the seeder on system rows, and statuses added later are open statuses. Status order on the pipeline bar and Kanban is `sort_order`. |
| R5 | **Saved views are fixed presets**, not per-user saved views (Admin D1 has no saved views yet). The six views of docs/03 §5.1 are chips above the lead list that set the filters. |
| R6 | **Data scope.** `Lead` uses `HasDataScope` with owner column `assigned_to`. Team users are the active members of every team the user manages, and the team scope also includes leads whose `sales_team_id` is one of those teams (so a manager sees unassigned leads of the team). Unassigned leads with no team are visible only with `view_all`. `Customer` owner columns: `account_manager_user_id` and `acquired_by_user_id`; 04 adds "on one of its projects". Activity lists use `owner_user_id`. Anyone who can view a lead or customer sees all of its activities on its detail page. A `LeadPolicy` and `CustomerPolicy` check single records with the same rules; routes use `can:viewAny` / `can:view`. |
| R7 | **Sales teams.** A user has at most one active membership (`left_on` null), enforced by `SaveSalesTeam`. Removing a member sets `left_on` to today. The manager does not have to be a member and may manage several teams. Teams have no code, so their routes use the id. |
| R8 | **Assignment (CRM-BR-05).** Assignable users are active users holding any `crm.leads.view_*` permission. A user with `crm.leads.assign` and `view_all` may assign to anyone; with `view_team`, to members of teams they manage; everyone else only to themselves. `sales_team_id` is copied from the assignee's active team when the lead is assigned. Every change writes `lead_assignment_histories` and sends `crm.lead_assigned` to the new owner, unless they assigned it to themselves. |
| R9 | **Round-robin (§6.3)** is built behind `crm.auto_assign_mode` (default `none`). With `round_robin_team`, a new lead that has a team but no assignee goes to the active member with the fewest open leads, then the one assigned longest ago. The lead form gets an optional Team select only when the mode is on. |
| R10 | **Duplicates (CRM-BR-03, CRM-AC-01).** `FindDuplicates` compares the fields in `crm.duplicate_check_fields` against open leads and non-merged customers, matching any of phone / WhatsApp to any of the other record's phone fields. The form checks live (debounced) and on save. Choices: **Open existing**; **Add as new enquiry for this customer** (sets source `EXISTING`, `referrer_type = customer`, `referrer_id`); **Create anyway** with a required reason, stored as an audit row with event `duplicate_override` and the reason and matched numbers in `new_values`. The same check runs on customer phone (CRM-BR-12) with the same reason override. |
| R11 | **Status changes.** `ChangeLeadStatus` handles open statuses: a note, CRM-BR-07 (a status in `crm.follow_up_required_on_status` needs an open follow-up; the modal can create one in the same call) and history (CRM-BR-06). `MarkLeadLost` needs a reason when `crm.lost_reason_required` (CRM-BR-08). `ReopenLead` moves LOST back to the open status held before LOST and needs a reason. WON is set only by `ConvertLead`. Converted leads are read-only except activities, notes and documents (CRM-BR-10). |
| R12 | **Follow-ups.** A follow-up is any open activity with `scheduled_at`. `LogActivity`, `CompleteActivity`, rescheduling and deleting an activity on a lead refresh `leads.next_follow_up_at` (earliest open `scheduled_at`) and `last_activity_at` (latest `completed_at`) in the same transaction. Logging a done activity can also schedule the next follow-up (§5.4). |
| R13 | **Jobs.** `SendDueReminders` runs every five minutes. `SendDailyDigest` runs every minute and only acts when the local time reaches `notifications.daily_digest_time` and the day's digest has not been sent (a cache key per date), so an admin change to the time applies without a deploy. `FlagStaleLeads` runs daily. Stale (CRM-BR-11) is computed in queries; a new `leads.stale_notified_at` column stops repeat notifications and is cleared when an activity is logged. |
| R14 | **Notifications** use `PreferenceNotification` with these keys added to `config/notifications.php`: `crm.lead_assigned` (database, mail), `crm.follow_up_reminder` (database, mail), `crm.daily_digest` (mail), `crm.lead_won` (database), `crm.lead_stale` (database). `crm.lead_won` goes to the lead's team manager and to users with the `management` role. |
| R15 | **Customer finance fields.** Payment term, credit limit, TIN and BIN need a new `crm.customers.update_finance` permission (accountants per docs/03 §2). Other editors see these fields read-only. |
| R16 | **Customer delete and merge.** No delete screen; customers are made inactive through their status (CRM-BR-13). The `delete` permission stays in the manifest for 04 / 06. Merge (§5.12) moves leads (both `converted_customer_id` and customer referrers), activities, contacts, attachments and notes, sets `merged_into_id` and soft-deletes the duplicate in one transaction, writing an audit row per moved table. It fires `CustomersMerging(survivor, duplicate)` inside the transaction so 04, 06 and 08 can move projects, invoices, receipts and journal lines later (CRM-AC-10 completes then). Survivor attribution (CRM-BR-16) is kept. |
| R17 | **Activities are polymorphic** with Laravel morphs `subject_type` / `subject_id` using the morph aliases `lead` and `customer`; 04 adds `project`. The quick-log is one Livewire component mounted on lead and customer pages and on My Activities. A customer's timeline shows its own activities plus those of every lead converted to it or referring to it. |
| R18 | **Exports and imports.** Leads and customers get Excel export of the filtered list (`crm.*.export`) through the shared `QueryExport`. No lead import: legacy data arrives by migration (docs/11). `crm.leads.import` stays in the manifest, unused. |
| R19 | **Internal business lines** are excluded from the lead and customer business line selects (CT-BR-04 for leads). Lead services must be active services. |
| R20 | **Deferred.** Reports (§10 → doc 10, including CRM-AC-08), the global top-bar search, the customer `ProjectStatusChanged` timeline entries and invoice/receipt events (§8 In), Bulk "Add follow-up" (Assign and Change status stay), and docs/03 open questions 2 (agents as an entity) and 4 (priced quotation). Open question 1 is handled by migration (11); open question 5 is answered by the existing land fields. |

## 3. Data model

Migrations in `database/migrations/crm/` (loaded by `CrmServiceProvider`), models in `app/Modules/Crm/Models`. All models are Auditable. All FKs are `ON DELETE RESTRICT` except where noted. Morph aliases: `lead_source`, `lead_status`, `lead_priority`, `lead_level`, `lost_reason`, `activity_type`, `activity_outcome`, `customer_type`, `customer_status`, `payment_term`, `sales_team`, `lead`, `crm_activity`, `customer`, `customer_contact`.

- **Lookups** ([LOOKUP] + extra columns per docs/03 §3.1): `lead_sources`, `lead_statuses`, `lead_priorities`, `lead_levels`, `lost_reasons`, `activity_types`, `activity_outcomes`, `customer_types`, `customer_statuses`, `payment_terms`.
- **`sales_teams`**: per §3.2, `manager_user_id` FK users, `business_line_id` FK business_lines.
- **`sales_team_members`**: per §3.3, FKs cascade on team delete; index (`user_id`, `left_on`). One active row per user is enforced in `SaveSalesTeam` (R7).
- **`leads`**: per §3.4, plus `stale_notified_at` TIMESTAMP nullable (R13). `converted_project_id` without FK (R1). `referrer_type` is one of `customer`, `employee`, `agent`, `other`; `employee` stores a user id until HRM (09). The FULLTEXT index is MySQL only; search uses `LIKE` so tests on SQLite work.
- **`lead_services`**: per §3.5, cascade on lead delete.
- **`lead_status_histories`**, **`lead_assignment_histories`**: per §3.6, §3.7, cascade on lead delete.
- **`crm_activities`**: per §3.8 with `subject_type` / `subject_id` as morphs (R17).
- **`customers`**: per §3.9, plus `merged_into_id` FK customers nullable (R16). `receivable_account_id` without FK (R2).
- **`customer_contacts`**: per §3.10, cascade on customer delete. One primary per customer, enforced in `SaveCustomer`.

`Lead` and `Customer` implement `Collaborative` and use `HasAttachments` / `HasNotes`, with `isViewableBy` from their policies.

### 3.1 Seeds (`database/seeders/Crm/`, idempotent upsert by `code`)

- All ten lookups with the rows in docs/03 §3.1. `lead_statuses` WON and LOST are system rows with their flags; NEW is a system row too, because it is the default. `customer_statuses` ACTIVE and BLOCKED are system rows. `payment_terms` NET7 / NET15 / NET30 get 7 / 15 / 30 days. Activity type icons: `phone`, `users`, `mail`, `message-circle`, `message-square`, `map-pin`, `building`, `sticky-note`, `calendar-clock`. CALL, MEETING, SITE_VISIT and OFFICE_VISIT count as contact; MEETING, SITE_VISIT and OFFICE_VISIT require a duration.
- Settings group `crm` with the seven keys of docs/03 §4.
- Permissions and grants (§4.1).
- No sales teams or leads. Factories exist for tests and the UAT demo seeder.
- Number sequences `lead` and `customer` already exist.

## 4. Architecture

### 4.1 Permissions (`app/Modules/Crm/permissions.php`)

```text
crm.leads.view_own | view_team | view_all | create | update | delete | assign | convert | export | import
crm.activities.view_own | view_team | view_all | create | update | delete
crm.customers.view_own | view_team | view_all | create | update | update_finance | delete | export | merge
crm.teams.view | manage
crm.master_data.view | create | update | deactivate
crm.reports.view
```

| Role | Grants |
|---|---|
| super_admin | everything (Gate::before) |
| management | `crm.*` except `*.view_own`, `*.view_team` |
| sales_manager | leads view_team, create, update, assign, convert, export; activities view_team, create, update, delete; customers view_team, create, update, export; teams view; master_data view |
| sales_executive | leads view_own, create, update, convert; activities view_own, create, update; customers view_own, create, update |
| project_manager | customers view_all |
| accountant, finance_manager | customers view_all, update_finance, export |
| engineer | none until 04 adds "own projects" |
| viewer | already `*.view` (teams, master data, reports) |

"Update" on a lead also needs the lead to be visible (R6), so a sales executive edits only their own leads. Lead delete is soft, admin only (management), and refused for converted leads (CRM-BR-18).

### 4.2 Placement

- `app/Modules/Crm/` with `CrmServiceProvider` (migrations, morph map, Livewire, policies, event listeners), registered in `bootstrap/providers.php`.
- Routes: `routes/modules/crm.php`, prefix `crm/`, name prefix `crm.`, `app` middleware, `can:` per route. Leads use `{lead:lead_number}` and customers `{customer:customer_number}`.
- Livewire (class-based), views in `resources/views/livewire/crm/...`:
  - `Leads\Index` (list and Kanban), `Leads\Form`, `Leads\Show`, `Leads\Convert`, `Leads\ChangeStatus` (modal / bottom sheet)
  - `Activities\QuickLog`, `Activities\Index` (tabs and calendar)
  - `Teams\Index`, `Teams\Form`
  - `Customers\Index`, `Customers\Form`, `Customers\Show`, `Customers\Merge`
- Actions (`app/Modules/Crm/Actions`): `CreateLead`, `UpdateLead`, `AssignLead`, `ChangeLeadStatus`, `MarkLeadLost`, `ReopenLead`, `DeleteLead`, `ConvertLead`, `LogActivity`, `CompleteActivity`, `RescheduleActivity`, `DeleteActivity`, `SaveSalesTeam`, `SaveCustomer`, `MergeCustomers`, `FindDuplicates`. Each authorizes against the actor, validates (throws `ValidationException`) and writes in `DB::transaction()`.
- Services: `LeadFollowUps` (refreshes the denormalised lead columns, R12), `RoundRobinAssigner` (R9).
- Jobs (`app/Modules/Crm/Jobs`): `SendDueReminders`, `SendDailyDigest`, `FlagStaleLeads`, scheduled in `routes/console.php`.
- Events: `LeadConverted`, `CustomerCreated`, `CustomersMerging`.
- Notifications (`app/Modules/Crm/Notifications`): `LeadAssigned`, `FollowUpReminder`, `DailyDigest`, `LeadWon`, `LeadStale`.
- `App\Support\Phone` (R3).

### 4.3 Master Data

`config/lookups.php` gains the ten CRM tables with `module => 'crm'` and `permission => 'crm.master_data'` and the extra fields of R4.

### 4.4 Navigation

The existing **CRM** group gets:

- Leads → `crm.leads.index` (mobile primary)
- My activities → `crm.activities.index` (mobile primary)
- Customers → `crm.customers.index` (mobile primary)
- Sales teams → `crm.teams.index`
- CRM setup (tree): Lead sources, Lead statuses, Lead priorities, Lead levels, Lost reasons, Activity types, Activity outcomes, Customer types, Customer statuses, Payment terms → master data, gated by `crm.master_data.view`

The Navigation service's visibility check is extended to accept a list of permissions (any of), so Leads shows for `crm.leads.view_own|view_team|view_all`.

## 5. Screens

All screens follow doc 00 §7.6 on mobile: fixed top bar with back and title, list rows instead of tables, bottom sheets for filters, row actions, status changes and pickers, sticky action bars on forms, a FAB for create, 44 px tap targets, `wire:navigate` everywhere, and `x-lookup-select` for FKs.

### 5.1 Leads — `crm/leads`

- **List view** (desktop): the columns of docs/03 §5.1. Next follow-up is red when overdue; stale leads get a "Stale" badge. Filters: status (multi), source, business line, service, level, priority, assigned to, team, location (tree, includes descendants via `full_path`), lead date range, follow-up (today / overdue / none), stale, converted. Search: lead number, name, company, phone, WhatsApp, email. Preset chips (R5). Bulk: Assign, Change status (open statuses only), Export.
- **Kanban view** (desktop, `x-ui.kanban`): one column per open status in sort order, with count and Σ expected value in the header. Dragging a card calls `ChangeLeadStatus`; if the move needs input (follow-up, CRM-AC-03) the change-status modal opens and the card snaps back on cancel. Closed statuses are not columns. The card shows name, company, phone, service chips, expected value, next follow-up and owner initials.
- **Mobile:** list rows (name, company or phone, status badge, next follow-up, chevron) with infinite scroll and pull-to-refresh. The Kanban becomes a horizontally scrollable status chip bar (with counts) that filters the same list, since a horizontal board is not native on a phone. Filters and presets in a bottom sheet. FAB → new lead.

### 5.2 Lead form — `crm/leads/create`, `crm/leads/{lead}/edit`

Sections Contact, Enquiry, Assignment and First follow-up (create only) per docs/03 §5.2:

- Phone with live duplicate check (R10); WhatsApp with "Same as phone".
- Referrer appears when the source requires one: a search over customers and users, or a typed name (CRM-BR-04).
- Services: a repeater of service + optional estimated value + note, at least one (CRM-BR-01). Expected value fills from the sum while untouched and stays manual once edited.
- Lead date ≤ today, default today.
- Assigned to defaults to the creator when they hold a `crm.leads.view_*` permission; Team is read-only (or a select under R9).
- First follow-up is required when the initial status is in `crm.follow_up_required_on_status`.
- Duplicate panel: an `x-ui.alert` above the form on desktop and a bottom sheet on mobile, with the three choices.

Full-page form (`x-shell.form-page`), one column on mobile with a sticky Save bar. The edit page is blocked for converted leads.

### 5.3 Lead detail — `crm/leads/{lead}`

- **Header:** number, name, status badge, priority, owner. Actions (each shown only with permission): Edit, Log activity, Schedule follow-up, Change status, Assign, Mark won → Convert, Mark lost, Reopen, Print profile.
- **Pipeline bar:** open statuses as clickable steps; clicking one opens the change-status modal.
- **Tabs:** Overview (fields and services) · Activities (open first, then done; quick-log button) · Documents (`foundation.attachments`) · Notes (`foundation.notes`) · History (status and assignment histories plus `foundation.history`).
- **Right rail:** age, days in current status, last activity, next follow-up, expected value, converted customer link.
- **Mobile:** summary cards stack under the header, tabs become a segmented control, actions move to a bottom sheet behind one top-bar button, and the right rail becomes the summary cards.
- **Print profile:** an A4 page using `x-print.letterhead` with the lead's fields, services and activities.

### 5.4 Activity quick-log

A modal (desktop) or bottom sheet (mobile) with the fields of docs/03 §5.4. Title defaults from the type. Duration is required when the type requires it. Reminder offsets: none / 15 min / 30 min / 1 h / 1 day before `scheduled_at`. "Schedule next follow-up" (date/time + type) creates a second, open activity. Mark done opens the same component in completion mode (outcome + next follow-up).

### 5.5 My activities — `crm/activities`

- Tabs: Overdue · Today · Upcoming · Done (segmented control on mobile). Columns: when, type, title, subject (link), owner, outcome. Filter by owner within the user's scope. Row actions: Mark done, Reschedule.
- **Calendar** view (week / month, `x-ui.scheduler`) of scheduled activities. On mobile, the calendar is a day-grouped agenda list.

### 5.6 Change status

Per docs/03 §5.6 and R11. On desktop a modal, on mobile a bottom sheet. Choosing WON goes to the convert wizard.

### 5.7 Convert wizard — `crm/leads/{lead}/convert`

1. **Customer:** matches by phone, WhatsApp, email and name, with the customer from an "Add as new enquiry" link pre-selected. Option A links an existing, non-blocked customer. Option B creates a new customer prefilled from the lead; the user completes customer type, NID / reg. no., TIN and payment term.
2. **Review & confirm:** summary and Convert.

The result is the customer, the lead set to WON, `LeadConverted` and `crm.lead_won` sent, and a redirect to the customer. A new customer gets `source_lead_id`, `lead_source_id` and `acquired_by_user_id` from the lead; a linked customer keeps its first attribution (CRM-BR-16, CRM-BR-17). `x-ui.stepper` on desktop, full-screen steps with a sticky Next / Convert bar on mobile.

### 5.8 Sales teams — `crm/teams`, `crm/teams/create`, `crm/teams/{team}/edit`

List: team, manager, members, business line, monthly target, open leads, won this month. Form: name*, manager*, business line, monthly target, active, and a members repeater (user + joined date; removing sets left date; rows with a left date show as past members). `crm.teams.manage` to create and edit.

### 5.9 Customers — `crm/customers`

List columns: Customer #, Name, Company, Type, Phone, Location, Account manager, Status. Filters: type, status, account manager, business line, location, source. Search: number, name, company, phone, email. Bulk: Export, Change account manager. Mobile rows: name, phone, type, status badge, chevron. FAB → new customer.

### 5.10 Customer form — `crm/customers/create`, `crm/customers/{customer}/edit`

Sections Identity · Contact · Classification (type, business line, account manager, status) · Finance (payment term, credit limit, TIN, BIN; editable with `update_finance`, R15) · Contacts (repeater; one primary). Phone duplicate check with reason override (CRM-BR-12).

### 5.11 Customer detail — `crm/customers/{customer}`

Header: number, name, status, account manager; actions Edit, Log activity, Merge (with `merge`). Tabs: Overview · Activities (R17) · Leads (origin and later enquiries) · Documents · Notes · History. Right rail: source, acquired by, first lead, customer since, credit limit. Mobile as in §5.3.

### 5.12 Customer merge — `crm/customers/{customer}/merge`

Pick the duplicate (the current customer survives, with a "Swap" button). Preview the counts per table that will move (R16). Confirm with a typed reason. Redirects to the survivor.

## 6. Error handling

- Action rule failures throw `ValidationException`, shown inline on forms and in the modals.
- 403 comes from route `can:` middleware, policies and `authorize()` in every Livewire action. Nav items, header actions and bulk actions are hidden when the user lacks the permission.
- Editing a converted lead, deleting a converted lead, assigning outside one's scope, converting a lead to a blocked customer and dragging to a status that needs a follow-up without creating one are all refused with a message.
- Jobs are idempotent: a reminder is sent once (`reminder_sent_at`), the digest once a day, a stale notice once per stale spell.

## 7. Testing

Pest feature tests under `tests/Feature/Crm/` (in-memory SQLite).

| Area | Cases |
|---|---|
| Access | each route 403 / 200 by permission; nav items by permission; CRM-AC-02 (executive sees own, manager sees team incl. unassigned team leads, management sees all) for list, detail and export |
| Seeders | idempotent; lookup rows and system flags; settings; permissions and grants |
| Phone | normalising and the mobile / landline rules (R3); User still normalises the same way |
| Leads | create / update rules CRM-BR-01, 02, 04; services and expected value; internal business line refused; number assigned; duplicate detection and the three choices with the audited reason (CRM-AC-01, CRM-BR-03) |
| Assignment | CRM-BR-05 scopes; team derived; history row and notification; round-robin choice and tie-break |
| Status | history on every change; follow-up required (CRM-AC-03, CRM-BR-07); lost reason (CRM-AC-04); reopen to previous status; WON refused outside conversion; converted lead read-only; delete rules (CRM-BR-18) |
| Activities | log / complete / reschedule / delete refresh `next_follow_up_at` and `last_activity_at`; next follow-up created; duration rule; overdue / today / upcoming / done tabs |
| Jobs | reminder at 14:30 for a 15:00 follow-up with 30 min (CRM-AC-07), sent once, not for completed ones; digest at the set time once a day with the manager summary; stale flagged once and reset by a new activity |
| Conversion | new customer with attribution; link existing keeps first attribution; blocked customer refused; lead WON with links; customer timeline shows lead activities; a failure inside the transaction leaves no customer and the lead unchanged (CRM-AC-06 without the project) |
| Customers | create / update; finance fields need `update_finance`; one primary contact; phone duplicate override; data scope |
| Merge | moves leads, activities, contacts, attachments, notes; duplicate soft-deleted with `merged_into_id`; audit rows; `CustomersMerging` fired in the transaction |
| Teams | one active team per user; removal sets left date; list figures |
| Master data | CRM lookups gated by `crm.master_data`; status flags not editable; system rows protected |

No browser tests. When the build is done, the screens are listed for the user to check at 390×844 and at desktop width.

## 8. Out of scope

- The project step of conversion and everything on 04 / 06 / 08 in R1, R2 and R16.
- CRM reports and CRM-AC-08 (doc 10), the global search, lead import, bulk "Add follow-up", agents as an entity and priced quotations (R18, R20).
- SMS delivery (no gateway).
- Sales team fields on the user form.
