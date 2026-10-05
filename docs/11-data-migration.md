# SOC ERP v2 — 11 · Data Migration & Cut-over

**Build phase:** 7 (scripts developed alongside phases 1–6, dry-runs from phase 5) · **Depends on:** all modules
**Source:** legacy database behind `soft.socbdltd.com`

> Legacy table and column names below come from the legacy UI field bindings and JSON endpoints observed on 05 Oct 2026 (e.g. `/get-client`, `/get-projects`, `/get_accounts`). **Exact table names must be confirmed against the SQL dump** in step M0 — update the mapping sheet, not the approach.

---

## 1. Objectives

1. Move all master data and history needed for daily work and reporting.
2. Fix known data problems during migration, with a written log of every change.
3. Start v2 accounting from **verified opening balances** (default Option A).
4. Prove completeness with reconciliation reports signed off by SOC Accounts and Management.
5. Keep the legacy system read-only for 3 months after cut-over.

---

## 2. Legacy Inventory (observed)

| Legacy entity | Observed endpoint / fields | Approx. rows |
|---|---|---|
| Clients (prospects + sold) | `/get-client`: id, client_id, client_type_id, project_id, client_name, org_name, phone, org_mobile, email, w_number, address, area_id, requirement, sold, note, date, level, source, reminder, comment, status (`p`/`s`), add_by, add_time | ~1,385 |
| Client types | `/get-clientType`: id, name, status | 14 |
| Areas | `/get-areas`: id, name, status | 50 |
| Services ("software") | `/get-software`: id, soft_name, status | 20 |
| Projects | `/get-projects`: id, project_id, project_type_id, name, status, project_type_name, assign_to | 593 (247 duplicate `project_id`) |
| Project types | `/get-types` | 15 |
| Tasks | task form: file_number, assigned person, support officer, entry_date, status, project, project type, client, bill_number, bill_amount, collect_amount, due_amount, deadline, task_detail, is_important | 508 |
| Account holders | `/get_accounts`: Acc_SlNo, branch_id, client_id, Acc_Code, Acc_type_work, Acc_agreement_number, Acc_Tr_Type (blank), Acc_contact_number, Acc_designation, Acc_Name, Acc_Type, Acc_Description | 185 |
| Cash transactions | Tr_Id, voucher_no, receipt_no, Tr_Type (Cash Receive / Cash Payment), account, Tr_date, Tr_Description, In_Amount, Out_Amount | ? |
| Bank accounts | account_name, account_number, account_type, bank_name, branch_name, initial_balance, description | ? |
| Bank transactions | transaction_date, account, transaction_type (Deposit/Withdraw), amount, note | ? |
| Client (project) transactions | transaction_date, task, project, client type, client, transaction_type (Payment/Receive), cash transaction link, voucher_no, amount, note | ? |
| Project expenses | expense_date, project type, project, assign_to, cash transaction, voucher_number, amount, note | ? |
| Vendor expenses | Tr_Type (Bill Receive / Bill Payment), cash transaction, voucher_no, receipt_no, bill_number, vendor, project, date, description, In/Out amount | ? |
| Vendor bills (MB) | header + lines: item_code, mb__no, visit_location, visit_description, vendor_unit, estimated_quantity, vendor_rate | ? |
| Project bills (MB) | same structure as vendor bills | ? |
| Vendors | name, enterprise_name, contact_number, account_id | 16 |
| Project visits | permitee_name, project, location, constructor_name, project_eng_name, field_office_phone, inspection_date, start/end time, weekly/event, description + lines (location, description, finding, regarding) | ? |
| Material estimates | project, address, date, work_name + lines (material, unit, total_estimated_qty, purpose_estimate) | ? |
| Work estimate sheets | project, address, date, work_name + lines (work_description, level, location, length, width, height, nose, unit, quantity) | ? |
| Materials | name | ? |
| Employees | code, name, post_id, department_id, father_name, mother_name, dob, gender, marital_status, present/permanent address, phone, email, reference, status | 42 |
| Departments / Posts | name | 8 / ? |
| Users | name, email, phone, type (Admin/Team Manager/User), team, employee, user_name | 30 |
| Teams | name (Team A–D) | 4 |
| Company profile | name, phone, email, address | 1 |

---

## 3. Approach

```text
M0 Obtain dump → M1 Load into `legacy` schema → M2 Profile & mapping sheets
→ M3 Clean-up decisions signed by SOC → M4 Scripts (idempotent) → M5 Dry run #1
→ M6 Reconcile & fix → M7 Dry run #2 (+ UAT on migrated data) → M8 Cut-over
→ M9 Post cut-over support & legacy freeze
```

### 3.1 Technical rules
- Separate DB connection `legacy` (read-only user).
- One artisan command per entity: `migrate:legacy {entity} --dry-run`; orchestrator `migrate:legacy all`.
- Every migrated row stores `legacy_table` + `legacy_id` in `legacy_id_map (legacy_table, legacy_id, new_table, new_id, migrated_at, notes)` → idempotent re-runs (upsert by map).
- Every clean-up action writes `migration_log (entity, legacy_id, action, detail JSON, created_at)` — e.g. `merged_duplicate_project`, `source_code_mapped`, `phone_normalised`, `skipped_test_row`.
- Migration runs with audit logging **off** and events **muted**; journals posted via `JournalPoster` with `journal_type = OPENING`.
- Created_by = system user "Migration"; original `add_by`/`add_time` mapped to `created_by`/`created_at` where the user maps.

---

## 4. Mapping & Clean-up Rules

### 4.1 Foundation
| Legacy | v2 | Rule |
|---|---|---|
| users | users | username = user_name; password **not** migrated (bcrypt compatibility unknown) → set random + `must_change_password`, distribute via HR; type Admin → `management` (MD) or `super_admin` (1–2 named), Team Manager → `sales_manager`, User → role by department (mapping sheet) |
| teams | sales_teams + members | from users.team |
| company profile | company_profile | |
| areas (50) | locations | manual mapping sheet: each area string → location node (create area nodes under correct thana); "Others" → null |

### 4.2 Catalog
| Legacy | v2 | Rule |
|---|---|---|
| client types (14) | business_lines | per 02 §3.1 table; `alamin test` dropped (log) |
| software/services (20) | services (sellable) or task_types (internal) | per 02 §3.3; trailing spaces trimmed; duplicates "Interior Design Work" / "Interior Design & Work" kept separate unless SOC merges |
| project types (15) | project_types + business line hint | "CETP-2024"/"CETP-2025" → type TRAINING (year in project name) |
| materials | materials | code generated `MAT-0001`; unit from first use in estimates |

### 4.3 CRM
| Legacy | v2 | Rule |
|---|---|---|
| clients where status = `p` | leads | lead_number generated in legacy id order; status: has comment/reminder → CONTACTED else NEW; reminder (future date) → open follow-up activity; comment/note → note activity |
| clients where status = `s` | leads (WON) + customers | customer created per unique normalised phone (dedupe); lead.converted_customer_id; converted_project_id from client.project_id (after project de-dup) |
| clients.source | lead_source_id | code map (confirm): `L`→LEAFLET, `FF`→F2F, `F`→REFERENCE? or FACEBOOK?, `FB`→FACEBOOK, `G`→GOOGLE, `Y`→YOUTUBE; unknown → OTHER + log |
| clients.level | lead_level_id | Entry→ENTRY, Middle→MID, Top→TOP |
| clients.requirement | lead_services (+ project_services) | service id map |
| clients.client_type_id | business_line_id | |
| clients.area_id | location_id | via area map |
| clients.add_by | assigned_to | user map |
| clients.client_id | legacy_client_id | kept, searchable |
| phones | normalised | invalid → kept in notes, phone = placeholder flagged for cleanup list |

Duplicate customers (same phone) → merged; log both legacy ids.

### 4.4 Projects
| Legacy | v2 | Rule |
|---|---|---|
| projects (593) | projects | **De-dup by `project_id`**: group rows with same project_id; survivor = earliest `add_time`; others' ids recorded in `legacy_project_ids`; all references (tasks, transactions, bills, visits, estimates) re-pointed to survivor; log each merge. Same project_id with **different names** → manual review list before merge |
| project_id string | project_number | kept exactly; number sequences per business line set to max existing + 1 |
| project_type_id | project_type_id, business_line_id | business line from prefix (`SOC-BD`→BD …) |
| status | project_status_id | active → IN_PROGRESS; any with all tasks completed & due 0 → COMPLETED (review list) |
| assign_to | project_manager_id | employee map |
| customer link | customer_id | from clients.project_id; projects without client → review list (internal or missing) |

### 4.5 Tasks
| Legacy | v2 | Rule |
|---|---|---|
| tasks (508) | project_tasks | status Pending→TODO, On Progress→IN_PROGRESS, Completed→DONE (completed_at from completion date); archived → archived_at; file_number kept; assigned person / support officer → employees; deadline → due_date |
| bill_amount / collect_amount / due_amount | **not** task fields | used only as cross-check for opening AR (§5) and reported in migration log |

### 4.6 Estimation & Site
| Legacy | v2 | Rule |
|---|---|---|
| work estimate sheets | estimates (BOQ, APPROVED, rev 0) + estimate_lines | quantity recomputed vs stored; differences logged (stored kept) |
| material estimates | estimates (MATERIAL) + material lines | "revised" purpose rows → revisions grouped by project+work_name ordered by date |
| project visits | site_inspections + findings | weekly/event flags → inspection_type; findings status → ACCEPTED (historical, closed) |
| project bill / vendor bill MB lines | measurement_entries (status BILLED) | linked to the opening invoice/bill or historical reference; direction customer/vendor |

### 4.7 Vendors, Employees
| Legacy | v2 | Rule |
|---|---|---|
| vendors (16) | vendors | category by review sheet; legacy account_id → legacy_account_code |
| employees (42) | employees | code kept; post → designation; gender/marital map; status active/inactive |
| departments, posts | departments, designations | |

### 4.8 Account holders (185) — classification sheet

Each row classified by SOC Accounts in a spreadsheet generated by `migrate:legacy account-holders --export`:

| Column | Values |
|---|---|
| legacy Acc_SlNo, Acc_Code, Acc_Name, Acc_type_work, Acc_designation, client_id | read-only |
| **target_type** | `customer`, `vendor`, `employee`, `expense_category`, `gl_account`, `ignore` |
| **target_ref** | existing v2 code (customer #, vendor #, employee code, expense category code, account code) or `NEW` |
| notes | |

Expected patterns (from observed data): office rent, internet, conveyance, lunch, entertainment, mobile bill, office expenses → `expense_category`; named engineers/architects/staff → `employee`; landowners / clients with design or RAJUK work → `customer`; contractors / service providers → `vendor`; programs (CETP) → `gl_account` (revenue/expense) or business line.

Import back with `--import` → validated (every row classified, refs exist) before transaction migration can run.

### 4.9 Financial history — Option A (default): opening balances + reference history

1. **Historical transactions** (cash, bank, client, project expense, vendor expense, bills) migrated into read-only `legacy_transactions` (normalised: date, type, voucher, MR no., party type/id via classification, project, description, in, out). Viewable from customer / vendor / project "Legacy history" tab and a Legacy Ledger report. **Not posted to GL.**
2. **Opening balance batch** dated cut-over date (e.g. 30 Jun 2027 or month-end chosen):
   - Cash & bank: physically counted cash and bank statement balances (signed by SOC).
   - Customer receivables per project: from legacy client ledger balance (bill − received) → cross-checked with tasks' due amounts → SOC confirms per customer → opening invoices (type OPENING).
   - Customer advances (received > billed) → opening Customer Advances per customer.
   - Vendor payables per vendor/WO: legacy vendor ledger due → SOC confirms → opening bills.
   - Employee advances outstanding → opening advances.
   - Fixed assets, loans, capital (from last audited accounts / SOC) → GL lines.
   - Balancing figure → Opening Balance Equity, then reclassified to Retained Earnings / Capital by the accountant.
3. Opening invoices/bills keep legacy references so customers' payments after cut-over allocate correctly.

**Option B (only if SOC requires):** convert every legacy cash/bank/client/vendor transaction to journals using classification sheet (Cash Receive → Dr Cash / Cr party or income; Cash Payment → Dr party or expense / Cr Cash; Bank Deposit/Withdraw → contra). Requires per-transaction review of unclassifiable rows; accuracy limited by single-entry source. Estimated 2–3× effort of Option A.

---

## 5. Reconciliation Pack (each dry run)

| # | Check | Pass rule |
|---|---|---|
| RC-01 | Row counts legacy vs v2 per entity (minus logged skips/merges) | exact |
| RC-02 | Leads + customers cover all ~1,385 clients | exact |
| RC-03 | Project merge report: 247 duplicates resolved, 0 orphan references | exact |
| RC-04 | Tasks count by status | exact |
| RC-05 | Customer opening AR Σ = SOC-confirmed receivable list | exact |
| RC-06 | Vendor opening AP Σ = SOC-confirmed payable list | exact |
| RC-07 | Cash & bank opening = counted / statement balances | exact |
| RC-08 | Trial balance after opening batch balanced; OBE explained | balanced |
| RC-09 | Per project: legacy (billed, received, due) vs v2 opening + legacy history | difference list reviewed |
| RC-10 | Sample check: 30 random customers, 10 projects, 5 vendors checked screen-by-screen by SOC staff | signed |
| RC-11 | Invalid phone list / unmapped areas / unknown source codes | reviewed lists |

Output: Excel workbook `migration-reconciliation-<run>.xlsx` with one sheet per check + migration log.

---

## 6. Cut-over Runbook

| When | Step | Owner |
|---|---|---|
| T−14 days | Dry run #2 signed off; user training complete; roles assigned | Dev, SOC |
| T−7 | Freeze master-data changes in legacy (no new clients types/areas/services) | SOC admin |
| T−3 | Collect receivable/payable confirmations; agree cut-over date (month-end) | SOC Accounts |
| T−1 (evening) | Legacy set to read-only (DB user privileges / maintenance mode); final dump | Dev |
| T (day 1) | Run `migrate:legacy all`; post opening balances; run reconciliation pack | Dev |
| T | SOC Accounts verifies RC-05..RC-08; Management sign-off | SOC |
| T | Create user accounts & send credentials; enable notifications | Dev, HR |
| T+1 | Go-live: all new entries in v2 | All |
| T+1..T+30 | Hyper-care: daily check-in, issue log, fixes | Dev |
| T+90 | Legacy archived (dump retained 7 years); legacy URL redirected | Dev, SOC |

Rollback: if sign-off fails on day T, legacy is re-opened (read-write) and cut-over moves to next month-end; v2 DB reset from pre-migration snapshot.

---

## 7. Data Quality Lists for SOC (to resolve before cut-over)

1. Source code meanings (`F`, `FF`, `L`, `FB`, others).
2. 247 duplicate project rows — especially same number with different names.
3. Projects with no client and clients with no project.
4. 185 account holders classification.
5. Area → location mapping.
6. Invalid / missing phones and emails.
7. User → role mapping; who keeps super_admin.
8. Receivable and payable confirmation per customer/vendor at cut-over.
9. Test data to drop (e.g. rows named "alamin test", "alamin work").

---

## 8. Acceptance Criteria

| ID | Criterion |
|---|---|
| MG-AC-01 | Re-running the migration on the same dump produces no duplicates (idempotent). |
| MG-AC-02 | Every legacy client, project, task, vendor, employee has a `legacy_id_map` row or a logged skip/merge. |
| MG-AC-03 | Searching a legacy client id or project number in v2 global search finds the record. |
| MG-AC-04 | Reconciliation pack RC-01..RC-11 all green and signed. |
| MG-AC-05 | A customer's v2 statement shows the opening balance on cut-over date and legacy history is viewable on the customer page. |
| MG-AC-06 | Project numbers continue: next Building Design project gets max(SOC-BD-xxxx)+1. |

## 9. Open Questions
1. Option A (opening balances) or Option B (full re-posting)?
2. Cut-over date (month-end) target.
3. Can SOC provide a full DB dump and, if available, the legacy source code (to read table names and hidden logic)?
4. Who at SOC signs the reconciliation (Accounts head, MD)?
