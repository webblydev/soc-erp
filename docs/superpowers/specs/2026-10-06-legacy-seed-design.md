# Legacy seed data — Design

**Date:** 06 Oct 2026
**Branch:** `legacy-seed` (from `hrm`)
**Source:** `docs/legacy_database.sql`, a copy of the v1 production database (35 `tbl_*` tables). It is git-ignored and loaded locally into the `soc_legacy` database.
**Builds on:** `docs/11-data-migration.md` (mapping and clean-up rules), and the Foundation, Catalog, CRM and HRM specs.
**Status:** Done, 2026-10-06, on branch `legacy-seed`. Plan: `docs/superpowers/plans/2026-10-06-legacy-seed.md`. Run on the dev database: 42 employees, 29 new users (+ admin reused), 4 teams, 1,386 leads (643 won, 742 contacted, 1 new), 643 customers, 6,036 activities.

## 1. Goal

Make the dev database look like the real system:

1. **Master and setup seeders** reflect the real v1 setup data. They are committed code and contain no personal data.
2. **A `LegacyDataSeeder`** copies the real v1 records into every table that exists today (employees, users, sales teams, customers, leads, lead services, activities). It reads the legacy database when it runs, so no customer or staff data enters git.

This is a **dev seeder**, not the cut-over migration. The formal `migrate:legacy` commands with `legacy_id_map` and `migration_log` (docs/11 §3.1) stay in build phase 7. The seeder follows the docs/11 mapping rules so the two give the same result.

## 2. Decisions

| # | Decision |
|---|---|
| L1 | **Personal data stays out of git** (user's choice). Part B reads a `legacy` DB connection (`config/database.php`, env `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD`, defaulting to the main MySQL connection's host and credentials). With no `LEGACY_DB_DATABASE` set, or when the database cannot be reached, the seeder prints a notice and does nothing. `docs/legacy_database.sql` is git-ignored. |
| L2 | **Dev seeder per docs/11** (user's choice). `Database\Seeders\Legacy\LegacyDataSeeder` is **not** called by `DatabaseSeeder`. It runs with `php artisan db:seed --class="Database\Seeders\Legacy\LegacyDataSeeder"` after the normal seed. Each entity has its own small importer class. All of them run in one transaction, with model events muted (no audit rows, notifications or Action side effects), as docs/11 §3.1 says. |
| L3 | **Idempotent through legacy keys.** Employees: `legacy_employee_id`. Users: username (case-insensitive). Sales teams: name. Customers and leads: `legacy_client_id`, plus a new `legacy_client_ref` that holds the legacy row id (the legacy `client_id` code repeats and is sometimes blank). A legacy row already imported is skipped, never updated. Re-running adds only new rows. |
| L4 | **Master data (Part A).** <br>• **Company profile:** the seeder fills address, phone and email from the legacy company row when they are empty. <br>• **Designations:** the 18 legacy posts replace the docs/09 sample list (user's choice). Codes are derived from the names; "Head of …" posts keep their department. <br>• **Departments:** already match; the legacy names are kept as the display names. <br>• **Materials:** the 8 real legacy materials, with category and unit (cement → CEMENT/bag; sands → SAND/cft; stone chips → STONE_AGGREGATE/cft). The two junk rows are dropped. <br>• **Locations:** the 50 legacy areas are mapped in a committed file `database/seeders/Foundation/data/legacy_areas.php` (legacy area id → location path). Missing thana or area nodes are created under the right parent, so the level follows the depth. "Others" maps to none. Plain district areas ("Pabna, Bangladesh") map to the district. <br>• **Unchanged:** business lines, services, lead sources and levels already hold the v1 values (docs/02, docs/03). Test rows ("alamin test", "alamin work", "ghgtf") are not seeded. |
| L5 | **Employees (42).** <br>• Code kept (`E00001`); name split on the last space; post mapped to designation; department mapped. <br>• Gender `male`/`female` mapped; marital status `married` → MARRIED, `unmarred` → SINGLE. <br>• Date of birth, addresses, father's and mother's names and reference kept. Phone normalised; an invalid or missing phone becomes the company phone, and the original value goes into the employee notes. Email → official email. <br>• Type PERMANENT. Joining date = `added_date` (date part). <br>• Status `a` → ACTIVE. Status `d` → RESIGNED with exit date = `update_date` or `added_date` and reason "Other". <br>• A JOINED event is written, plus RESIGNED for former staff, with `approved_by` null. |
| L6 | **Users (30).** <br>• Username = `user_name`; name, email and phone kept. Password `password` (dev only), `must_change_password` false. Active = status `a`. <br>• Linked to their employee. When two users point at the same employee (`Admin` and `LSD`), the first by id keeps the link. <br>• Roles (user's choice): type `a` → super_admin; `t` → sales_manager; `u` → by the employee's department: Design or Project Operation → engineer, Marketing & Sales or Customer Relation → sales_executive, Accounts → accountant, HR & Admin → hr_admin, Supply Chain or Logistic → engineer, none → sales_executive. <br>• A legacy username equal to an existing user's (e.g. `Admin` vs the seeded `admin`) maps to that user, which is left unchanged. |
| L7 | **Sales teams.** Users' `team_name` letters a–d become teams "Team A" … "Team D". The manager is the team's first active type-`t` user (else none). Members are the team's active users that own leads, joined on their first lead's date; a user already in another team is skipped (CRM R7, one team per user). |
| L8 | **Clients → leads and customers.** Status `d` (4 rows) is skipped. For the rest, in legacy id order: <br>• **Lead:** number `L-{seq:6}` from the normal sequence; `lead_date` = `date` when valid, else `add_time`; name, company (`org_name`), phone and WhatsApp (`w_number`) normalised; office phone (`org_mobile`) when valid; email lower-cased when valid; address. <br>• Location from the area map; business line from the `client_id` prefix (§3); source `L` → LEAFLET, `FF` → F2F, `F` → REFERENCE (user's answer), `FB` → FACEBOOK, else OTHER; level Entry/Middle/Top → ENTRY/MID/TOP; priority NORMAL. <br>• Assigned to the `add_by` user (else the seeded admin), with `sales_team_id` from that user's team. `created_at` = `add_time`; `legacy_client_id` = `client_id`; `legacy_client_ref` = legacy id; `note` → lead `notes`. <br>• **Services:** from `requirement` (comma-separated software ids, §3); unknown or empty → none. Lead services need at least one service only in the form (CRM-BR-01); seeded leads may have none. <br>• **Status:** `s` → WON, `won_at` = `add_time`. `p` → CONTACTED when it has any note, comment or reminder, else NEW. A status history row is written. <br>• **Customer** for each WON lead: number `C-{seq:6}`, type INDIVIDUAL (COMPANY when `org_name` is set), ACTIVE, the same contact fields, account manager = the lead owner, attribution (source lead, lead source, acquired by). The lead gets `converted_customer_id`, `converted_at` and `converted_by`. No phone is shared in the data, so no merging is needed; a later duplicate phone would be linked to the existing customer. |
| L9 | **Activities.** <br>• Each `tbl_clientdetails` row → a completed NOTE activity on the lead (or on the customer for WON leads). `completed_at` = `added_date`, title = the first 60 characters of the note, description = the note. The owner is the user whose username matches `added_by` (case-insensitive), else the lead owner. <br>• `tbl_client.comment` → one completed NOTE "Legacy comments" at `add_time`. <br>• `reminder`: years `00YY` are read as `20YY`. A reminder after today → an open FOLLOW_UP activity scheduled at 10:00 that day for the lead owner (open leads only); past reminders are dropped. <br>• Then `LeadFollowUps::refresh()` sets `next_follow_up_at` and `last_activity_at`. |
| L10 | **Number sequences** advance naturally because each number comes from `NumberSequenceService`. |
| L11 | **Out of scope until the modules exist:** vendors, account holders, cash, bank, bills, expenses, visits and estimates. Projects and tasks were added with the Projects module (§7). |
| L12 | **Tests** use an in-memory SQLite `legacy` connection. A helper builds the subset of the legacy schema the seeder reads and inserts a few rows. No test needs the real dump. |

## 3. Mapping tables (in `Database\Seeders\Legacy\LegacyMap`)

**`client_id` prefix → business line code.** The prefix is upper-cased and spaces removed before matching.
- `SOC-CON` → CON (`SOC-CON-BLE` → CON-BLE, `SOC-CON-UPS` → CON-UPS)
- `SOC-BD&RA` → BDRA; `SOC-BD` → BD
- `SOC-CETP`, `SOC-CTEP`, `CETP-` → CETP
- `SOC-EBC` → EBC; `SOC-AMZ` → AMZ; `SOC-TSE` → TSE; `SOC-AGENT` → AGENT; `SOC-DSW` → DSW
- anything else → none

**Software id → service code:**
- 1 → BD; 2 → BDRA; 3 → INT-DESIGN
- 6 → ENGG; 12 → RENOVATION; 13 → CETP
- 14 → INT-EXT; 15 → INT-WORK; 16 → BLE; 17 → UPS
- 18 → DSW; 19 → SOIL; 21 → ESTIMATE
- 4, 5, 7, 8, 9, 10, 11 (internal work) and 20 (test) → no lead service

**Post id → designation code** (the 18 posts, in order):
- 1 MD, 2 CEO, 3 ED, 4 CHAIRMAN
- 5 HEAD_DESIGN, 6 HEAD_PROJECT_OPS, 7 HEAD_ACCOUNTS, 8 HEAD_HR_ADMIN, 9 HEAD_SUPPLY_CHAIN, 10 HEAD_MKT_SALES
- 11 STRUCTURAL_ENGINEER, 12 HEAD_CUSTOMER_REL, 13 PROJECT_ENGINEER, 14 ARCHITECT, 15 JR_PROJECT_ENGINEER, 16 DEPUTY_PROJECT_ENGINEER, 17 TECH_SUPPORT_ENGINEER, 18 HEAD_LOGISTIC

**Department id → code:**
- 1 DESIGN, 2 PROJECT_OPS, 3 MKT_SALES, 4 ACCOUNTS
- 5 HR_ADMIN, 6 CUSTOMER_REL, 7 SUPPLY_CHAIN, 8 LOGISTIC

## 4. Files

- `config/database.php`: `legacy` connection.
- `database/seeders/Foundation/data/legacy_areas.php`; `LocationSeeder` also places the legacy areas; `CompanyProfileSeeder` fills the contact fields.
- `database/seeders/Hrm/HrmLookupSeeder.php`: the legacy posts as designations.
- `database/seeders/Catalog/MaterialSeeder.php` (new, called by `CatalogSeeder`).
- `database/migrations/crm/…_add_legacy_client_ref_to_leads_and_customers.php`.
- `database/seeders/Legacy/`: `LegacyDataSeeder`, `LegacyMap`, `LegacyContext` (shared lookups and id maps for one run), and importers `ImportEmployees`, `ImportUsers`, `ImportSalesTeams`, `ImportClients` (leads + customers + services), `ImportClientActivities`.
- `tests/Feature/Legacy/` with a `legacySchema()` helper.

## 5. Testing

| Area | Cases |
|---|---|
| Connection | no `LEGACY_DB_DATABASE` → notice, nothing written |
| Master data | legacy areas placed at the right depth, "Others" none; designations are the 18 posts; 8 materials; company contact filled once |
| Employees | code, names, designation, department, gender, marital status, resigned with exit date, JOINED/RESIGNED events, re-run adds nothing |
| Users | roles by type and department, employee link (first wins), password works, existing username reused |
| Teams | letters → teams, manager, members, one team per user |
| Clients | pending → NEW / CONTACTED, sold → WON + customer with attribution, deleted skipped, source / level / business line / services / location / owner mapped, numbers in id order, re-run adds nothing |
| Activities | details → notes with the right owner and date; comment note; future reminder → open follow-up, `00YY` fixed, past dropped; follow-up columns refreshed |

## 6. Implementation deviations

Where the build differs from the sections above, the build is authoritative.

- L3: each importer runs its own small transactions inside the seeder's outer transaction, so an importer can also be run on its own in tests.
- L4: the sample designations from docs/09 are deleted when unused, else deactivated, so an existing dev database ends up with the 18 posts too.
- L6: usernames are stored lower-case (the User model does this). A v1 username the user form would refuse is turned into a slug ("HR & Admin" → `hr-admin`); notes written under the v1 name still find the user. An employee goes to the first v1 user pointing at it even when that user maps to an existing account, so `admin` stays unlinked and `lsd` gets no employee. A v1 email already used by another user is dropped; a phone that is not a valid mobile is dropped.
- L8: a pending client also counts as contacted when it has follow-up notes (`tbl_clientdetails`), not only a note, comment or reminder. Leads keep at most 40 characters of the v1 client code (the column width).
- L9: follow-up columns are refreshed only on open leads; notes of won clients sit on the customer.
- §4: no separate `LegacyContext` lookups class beyond `idFor()` and `fallbackUserId()`; test helpers live in `tests/Feature/Legacy/LegacySchema.php`, required from `tests/Pest.php`.

## 7. Addendum — projects and tasks (07 Oct 2026)

Added after the Projects module (docs/04, `2026-10-07-projects-design.md`). Two importers run after `ImportClientActivities`: `ImportProjects` and `ImportTasks`. The data: 358 `tbl_project` rows (9 deleted, 1 test row), 508 `tbl_task` rows, 22 `tbl_type` rows.

| # | Decision |
|---|---|
| L13 | **Legacy keys.** New nullable columns `projects.legacy_project_ref` and `tasks.legacy_task_ref` hold the v1 row id; an imported row is skipped, never updated (like L3). `legacy_project_ids` holds `[v1 id]`. |
| L14 | **Projects.** Rows with status `d` and the test row (`01111` "alamin test") are skipped. <br>• **Number:** `project_id` kept, with tabs and outer spaces trimmed. The two v1 numbers used twice with different names (`SOC-BD-0107`, `SOC-DSW-0013/2025`) keep the number on the earliest row; the later row gets `-{v1 id}` appended and a note (docs/11 asks for manual review). <br>• **Type and business line** from `tbl_type` (map in §7.1). The business line comes from the number prefix when it matches one (`LegacyMap::businessLineFor`, plus `SOC-MANAGEMENT` → MGT, `SOC-M&S` → MS, `ATP-` → CETP), else from the type. A project whose type or line is internal becomes INTERNAL on MGT / MS, with no customer. <br>• **Customer:** from the v1 client whose `project_id` points at the row (earliest client first), through the customers created by `ImportClients`; else the most common client of the project's tasks (`client_list_id`, else the task `client_id` code); else the one imported customer whose name (titles like Md./Mr. and punctuation removed, at least 6 letters) equals the project name before its first comma, bracket or dash, with the note "Customer matched by name from v1; check it.". A billable project with neither gets a new INDIVIDUAL customer named after the first task's client (or the project), phone from that task (else the company phone), note "Created for v1 project …". <br>• **Status** IN_PROGRESS; `created_at` = `add_time`; `created_by` = the `add_by` user; start date = the earliest task entry date, else `add_time`. <br>• **People:** PM = the employee who holds most of the project's tasks; the other assignees and support officers join the team (DESIGN department → ARCHITECT, PROJECT_OPS → SITE_ENGINEER, else SUPPORT_OFFICER), assigned on their first task's entry date. <br>• **Services:** the linked lead's services, qty 1, rate 0 (the contract value stays 0; v1 has no prices). <br>• The linked won lead gets `converted_project_id` when it has none. <br>• **Sequences:** each business line's `project` sequence moves past the highest `{prefix}-{n}` imported (MG-AC-06). |
| L15 | **Tasks.** All 508 rows. <br>• **Number** `T-{yy}-{seq:5}` from the task sequence in v1 id order, dated by `entry_date`; `file_number` kept. <br>• **Title** = the first line of `task_detail` (max 120 characters), else "{type} — {project name or client name}"; description = `task_detail`. <br>• **Project** from `projectId` (the numeric link; the text `project_id` disagrees on 24 rows); none → a general task. <br>• **Type** from `tbl_type` (§7.1). Assignee and support officer from `assign_to` / `support_id` (employee ids; 0 → none). `assign_by` is empty in v1, so assigned by = the project's creator, else the admin. <br>• **Status:** `p` → TODO, `o` → IN_PROGRESS, `c` → DONE (completed at `completed_date` 17:00, else the deadline; completed by the assignee's user, else the admin; progress 100), `a` → DONE when it has a completed date else TODO, `d` → CANCELLED and archived (status `d` means deleted in v1; kept for the count check RC-04). <br>• `is_important` `true` → important and priority HIGH, else NORMAL. Start = `entry_date`; due = `deadline` (not before the start). `completed_by_comment` → a task comment by the completer. <br>• Bill, collect and due amounts are not imported (docs/11 §4.5). |
| L16 | **No events.** Like the other importers, projects and tasks are written directly with model events muted: no audit rows, notifications, template tasks or milestone triggers. |

### 7.1 `tbl_type` → project type, default business line, task type

| v1 type | Project type | Line | Task type |
|---|---|---|---|
| 1 Building Design Work | RESIDENTIAL | BD | DESIGN |
| 2 Building Design & RAJUK Approval | RESIDENTIAL | BDRA | APPROVAL_FILE |
| 3 Interior Design Work, 13 Wood Work, 14 Paint Work | INTERIOR | INT | DESIGN (3), OTHER (13, 14) |
| 4 Building Renovation Work | RENOVATION | CON | OTHER |
| 5 Engineering Service work | CONSULTANCY | CON | STRUCTURAL_CALC |
| 6 Accounts, 7 Logistic, 8 Inventory, 9 Supply Chain, 10 HR & Admin | INTERNAL | MGT | ACCOUNTS, LOGISTIC, INVENTORY, SUPPLY_CHAIN, HR_ADMIN |
| 11 Customer Relation, 12 Marketing & Sales | INTERNAL | MS | CUSTOMER_RELATION, OTHER |
| 15 Bank Loan Estimate | ESTIMATE | CON-BLE | ESTIMATE |
| 16 Union Parishad Sheet | ESTIMATE | CON-UPS | ESTIMATE |
| 18 CETP-2024, 21 CETP-2025 | TRAINING | CETP | OTHER |
| 19 Digital Survey work | SURVEY | DSW | SURVEY |
| 20 Soil Test work | SURVEY | CON | SURVEY |
| 22 Estimating Work | ESTIMATE | CON | ESTIMATE |
| other / 17 (test) | RESIDENTIAL | BD | OTHER |

**Run on the dev database (07 Oct 2026):** 348 projects (BD 169, BD&RA 69, AMZ 28, CON 24, CON-UPS 24, CON-BLE 11, DSW 10, internal 6, others 7), 27 linked to their client and lead, 63 matched by name, 119 with a new customer; 194 have no PM because v1 has no task for them. 508 tasks: 354 done, 42 in progress, 69 to do, 43 cancelled and archived; 109 are general tasks. A second run creates nothing. The run also found that two index names were too long for MySQL (fine on the SQLite tests); the Projects migrations now name them.

