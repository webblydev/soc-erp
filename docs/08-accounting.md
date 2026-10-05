# SOC ERP v2 — 08 · Accounting (CoA, Journal Engine, Posting Rules, Bank & Cash, Expenses, Advances, Tax, Period Close)

**Build phase:** 5 (foundation) and 6 (transactions) · **Depends on:** 01, 02 · **Used by:** 03–07, 09, 10
**Replaces (legacy):** Create Account Holder, Cash Transaction, Bank Accounts, Bank Transactions, Project Expense, Cash Ledger, Cash View, Cash/Bank Transaction Reports, Monthly Report

---

## 1. Purpose & Scope

A proper **double-entry** ledger that every module posts into.

- Chart of accounts (hierarchical), account types and groups
- Fiscal years and monthly periods, locking, year-end close
- Journal engine (`JournalPoster`) and configurable posting rules
- Manual journals (with approval) and recurring journals
- Bank, cash and mobile-wallet accounts; contra transfers; bank reconciliation
- Common payments table (receipts in 06, vendor payments in 07, expense payments here)
- Expenses (simple UI, journal behind)
- Employee advances (site imprest) and settlements
- Tax rates (VAT, AIT, TDS, VDS) and tax payable tracking
- Opening balances (cut-over)
- Financial reports (GL, TB, P&L, BS, Cash Flow, Cash/Bank Book) — columns in 10

Not in v2: multi-currency revaluation, fixed-asset register with automatic depreciation (manual journal for now), budgeting at company level (project budgets are in 05).

---

## 2. Permissions

```text
accounting.accounts.view | create | update | deactivate
accounting.fiscal.view | manage | lock_period | unlock_period | close_year
accounting.journals.view | create | submit | post | reverse | export
accounting.recurring.manage
accounting.posting_rules.view | manage
accounting.bank_accounts.view | manage
accounting.contra.view | create | post | cancel
accounting.reconciliation.view | perform
accounting.expenses.view_own | view_all | create | submit | approve | post | cancel
accounting.advances.view | create | post | settle | cancel
accounting.opening_balances.manage
accounting.reports.view
accounting.tax.manage
```

| Permission | management | finance_manager | accountant | project_manager | engineer / others |
|---|---|---|---|---|---|
| accounts manage | view | ✓ | view | — | — |
| fiscal manage / lock / close | view | ✓ | — | — | — |
| journals create/submit | — | ✓ | ✓ | — | — |
| journals post / reverse | ✓ | ✓ | — | — | — |
| posting rules | view | ✓ | — | — | — |
| contra create / post | — | ✓ | ✓ | — | — |
| reconciliation | — | ✓ | ✓ | — | — |
| expenses create/submit | ✓ | ✓ | ✓ | ✓ | ✓ (own claims) |
| expenses approve | ✓ | ✓ | — | ✓ (own projects ≤ limit) | — |
| expenses post (pay) | — | ✓ | ✓ | — | — |
| advances create/post | — | ✓ | ✓ | — | — |
| advances settle | — | ✓ | ✓ | ✓ (submit settlement) | ✓ (submit own) |
| reports | ✓ | ✓ | ✓ | project reports only | — |

---

## 3. Data Model

### 3.1 `account_types` [LOOKUP] + columns

| Column | Type | Notes |
|---|---|---|
| normal_balance | VARCHAR(6) | `debit` / `credit` (two fixed values validated in app; not a business concept) |
| statement | VARCHAR(3) | `BS` / `PL` |

Seed (system): ASSET (debit, BS) · LIABILITY (credit, BS) · EQUITY (credit, BS) · REVENUE (credit, PL) · EXPENSE (debit, PL).

### 3.2 `account_groups` [LOOKUP] + `account_type_id`, `cash_flow_section` (operating/investing/financing/none)

Seed: CURRENT_ASSET · FIXED_ASSET · CURRENT_LIABILITY · LONG_TERM_LIABILITY · EQUITY · OPERATING_REVENUE · OTHER_INCOME · DIRECT_COST · OPERATING_EXPENSE · FINANCE_COST.

### 3.3 `accounts`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| code | VARCHAR(20) | no | UNIQUE (e.g. 1130) |
| name | VARCHAR(150) | no | |
| parent_id | BIGINT | yes | |
| account_type_id | BIGINT | no | must equal parent's type |
| account_group_id | BIGINT | no | |
| level | TINYINT | no | computed |
| path | VARCHAR(255) | no | `1000/1100/1130` |
| is_postable | TINYINT(1) | no | leaf accounts only = 1 |
| is_control | TINYINT(1) | no | AR/AP/advances |
| control_party | VARCHAR(20) | yes | `customer`,`vendor`,`employee` — lines must carry this party |
| is_bank_or_cash | TINYINT(1) | no | |
| requires_project | TINYINT(1) | no | e.g. Direct cost accounts |
| is_system | TINYINT(1) | no | referenced by settings/posting rules |
| currency_id | BIGINT | no | default BDT |
| description | VARCHAR(255) | yes | |
| is_active | TINYINT(1) | no | |
| [AUDIT] | | | |

Seed: full CoA from plan v2 §13.3 (codes 1000–6950). Settings map control/system accounts:

| Setting key | Account |
|---|---|
| accounting.ar_control | 1130 Accounts Receivable |
| accounting.ap_control | 2110 Accounts Payable |
| accounting.customer_advances | 2120 Customer Advances |
| accounting.vendor_advances | 1150 Advances to Vendors |
| accounting.employee_advances | 1140 Advances to Employees |
| accounting.retention_receivable | 1160 |
| accounting.retention_payable | 2160 |
| accounting.ait_receivable | 1170 |
| accounting.vat_payable | 2130 |
| accounting.tds_payable | 2140 |
| accounting.salary_payable | 2150 |
| accounting.retained_earnings | 3200 |
| accounting.opening_balance_equity | 3900 Opening Balance Equity (add to CoA) |
| accounting.bank_charges | 6950 |
| accounting.cheques_in_hand | 1115 Cheques in Hand (add, optional) |
| accounting.default_revenue_account | 4900 |
| accounting.rounding_account | 6990 Rounding Difference (add) |

### 3.4 `fiscal_years`

`[STD], code VARCHAR(10) UNIQUE ("FY27"), name ("FY 2026-27"), start_date, end_date, status (lookup fiscal_year_statuses: OPEN · CLOSING · CLOSED), closed_by, closed_at, closing_journal_entry_id`.

### 3.5 `fiscal_periods`

`[STD], fiscal_year_id, period_no (1–12, 13 = adjustment), name ("Jul 2026"), start_date, end_date, is_locked, locked_by, locked_at, lock_note` — UNIQUE(fiscal_year_id, period_no). Period 13 = year-end adjustments dated last day.

### 3.6 `journal_entries`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| entry_number | VARCHAR(40) | no | UNIQUE `JV-27-00001` |
| entry_date | DATE | no | |
| fiscal_period_id | BIGINT | no | resolved from date |
| journal_type_id | BIGINT | no | lookup `journal_types`: SALES · RECEIPT · PURCHASE · PAYMENT · EXPENSE · CONTRA · ADVANCE · MANUAL · OPENING · RECURRING · REVERSAL · YEAR_END |
| source_type | VARCHAR(40) | yes | morph alias (invoice, receipt, bill, payment, expense…) |
| source_id | BIGINT | yes | |
| source_number | VARCHAR(40) | yes | denormalised doc number for ledgers |
| voucher_number | VARCHAR(40) | yes | printed voucher no. (legacy `voucher_no`) |
| narration | VARCHAR(500) | no | |
| status_id | BIGINT | no | lookup `journal_statuses`: DRAFT · SUBMITTED · POSTED (is_posted) · REVERSED (is_posted) |
| total_debit | DECIMAL(18,2) | no | |
| total_credit | DECIMAL(18,2) | no | |
| reversal_of_id | BIGINT | yes | |
| reversed_by_id | BIGINT | yes | |
| posted_by / posted_at | | yes | |
| is_system_generated | TINYINT(1) | no | from documents (not editable) |
| [AUDIT] | | | |

Indexes: entry_date, (source_type, source_id), status_id, fiscal_period_id.

### 3.7 `journal_entry_lines`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT | no | |
| journal_entry_id | BIGINT | no | |
| line_no | SMALLINT | no | |
| account_id | BIGINT | no | postable only |
| debit | DECIMAL(18,2) | no | ≥ 0 |
| credit | DECIMAL(18,2) | no | ≥ 0; exactly one of debit/credit > 0 |
| description | VARCHAR(500) | yes | |
| customer_id | BIGINT | yes | |
| vendor_id | BIGINT | yes | |
| employee_id | BIGINT | yes | |
| project_id | BIGINT | yes | |
| business_line_id | BIGINT | yes | |
| cost_category_id | BIGINT | yes | |
| branch_id | BIGINT | yes | |
| entry_date | DATE | no | denormalised for fast ledgers |
| is_posted | TINYINT(1) | no | denormalised |
| reconciled_at | DATE | yes | bank recon |
| bank_reconciliation_id | BIGINT | yes | |

Indexes: (account_id, entry_date, is_posted), (customer_id, account_id), (vendor_id, account_id), (employee_id, account_id), (project_id, account_id), journal_entry_id.

### 3.8 `payments` (common money-movement document)

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| payment_number | VARCHAR(40) | no | UNIQUE (`PV-…`, `CT-…`) |
| payment_type_id | BIGINT | no | lookup `payment_types`: VENDOR_PAYMENT · VENDOR_ADVANCE · EXPENSE_PAYMENT · EMPLOYEE_ADVANCE · ADVANCE_REFUND (employee returns cash) · CUSTOMER_REFUND · CONTRA · RETENTION_RELEASE · SALARY · OTHER_RECEIPT · OTHER_PAYMENT |
| direction | VARCHAR(3) | no | `in` / `out` (derived from type) |
| party_type | VARCHAR(20) | yes | vendor, employee, customer, other |
| party_id | BIGINT | yes | |
| party_name | VARCHAR(150) | yes | for "other" |
| project_id | BIGINT | yes | |
| payment_date | DATE | no | |
| amount | DECIMAL(18,2) | no | |
| payment_method_id | BIGINT | no | |
| from_account_id | BIGINT | yes | bank/cash (out, contra) |
| to_account_id | BIGINT | yes | bank/cash (in, contra) or counter account for OTHER |
| cheque_no / cheque_date / transaction_ref | | yes | |
| bank_charges | DECIMAL(18,2) | no | default 0 |
| voucher_number | VARCHAR(40) | yes | printed debit/credit voucher no. |
| status_id | BIGINT | no | DRAFT · POSTED · CANCELLED |
| journal_entry_id | BIGINT | yes | |
| notes | TEXT | yes | |
| [AUDIT] | | | |

`payment_allocations`: `id, payment_id, allocatable_type (bill, expense, employee_advance), allocatable_id, amount, reversed_at`.

### 3.9 `bank_accounts`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| account_id | BIGINT | no | UNIQUE FK accounts (postable, is_bank_or_cash) |
| kind_id | BIGINT | no | lookup `bank_account_kinds`: CASH · PETTY_CASH · CURRENT · SAVINGS · SND · MOBILE_WALLET · CHEQUES_IN_HAND |
| display_name | VARCHAR(150) | no | legacy `account_name` |
| bank_name | VARCHAR(150) | yes | |
| branch_name | VARCHAR(150) | yes | |
| account_number | VARCHAR(60) | yes | |
| routing_number | VARCHAR(20) | yes | |
| custodian_employee_id | BIGINT | yes | for cash/petty cash |
| opening_balance | DECIMAL(18,2) | no | informational; actual via opening journal |
| opening_date | DATE | yes | |
| allow_negative | TINYINT(1) | no | default 0 (cash never negative) |
| is_active | TINYINT(1) | no | |

### 3.10 Bank reconciliation

`bank_reconciliations`: `[STD], bank_account_id, statement_date, statement_balance, opening_reconciled_balance, cleared_total, difference, status (DRAFT/COMPLETED), completed_by/at`.
Lines cleared by setting `journal_entry_lines.bank_reconciliation_id` + `reconciled_at`. Optional `bank_statement_lines` import (CSV: date, description, ref, debit, credit) for matching.

### 3.11 `expenses`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| expense_number | VARCHAR(40) | no | UNIQUE |
| expense_date | DATE | no | |
| expense_kind | — | — | lookup `expense_kinds`: DIRECT_PAID (paid now from cash/bank) · CLAIM (employee reimbursement) · FROM_ADVANCE (settles advance) |
| project_id | BIGINT | yes | |
| employee_id | BIGINT | yes | claimant / spender |
| vendor_id | BIGINT | yes | optional payee |
| payee_name | VARCHAR(150) | yes | |
| description | VARCHAR(500) | no | |
| total_amount | DECIMAL(18,2) | no | Σ lines |
| paid_from_account_id | BIGINT | yes | for DIRECT_PAID |
| employee_advance_id | BIGINT | yes | for FROM_ADVANCE |
| status_id | BIGINT | no | lookup `expense_statuses`: DRAFT · SUBMITTED · APPROVED · POSTED · REJECTED · CANCELLED |
| approved_by / approved_at | | yes | |
| journal_entry_id | BIGINT | yes | |
| [AUDIT] | | | |

`expense_lines`: `id, expense_id, expense_category_id, account_id, project_id, cost_category_id, description, amount, tax_rate_id, vat_amount, receipt_attachment_id`.

`expense_categories` [LOOKUP] + `account_id`, `requires_project`, `requires_receipt_above DECIMAL`: Conveyance · Transport (site) · Lunch / Food · Entertainment · Mobile bill · Internet · Office rent · Utilities · Printing & stationery · Photocopy / plotting · Authority fees (RAJUK etc.) · Labour (daily) · Site materials (small) · Repairs · Bank charges · Miscellaneous. (Legacy account-holder expense heads map here.)

### 3.12 Employee advances

`employee_advances`: `[STD], advance_number, employee_id, project_id, purpose, advance_date, amount, payment_id (disbursement), settled_amount (cached), refunded_amount (cached), balance (cached), due_settlement_date, status (lookup: OPEN · PARTIALLY_SETTLED · SETTLED · CANCELLED), [AUDIT]`.

Settlement = expense of kind FROM_ADVANCE (lines with receipts) + optional cash refund (payment ADVANCE_REFUND) or top-up payment if overspent.

### 3.13 Tax

`tax_types` [LOOKUP]: VAT · VDS · AIT · TDS.
`tax_rates`: `[STD], tax_type_id, name, rate DECIMAL(7,4), payable_account_id, receivable_account_id, effective_from, effective_to, is_active`.

### 3.14 Posting rules

`posting_rules`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT | |
| document_type | VARCHAR(40) | invoice, receipt, credit_note, bill, vendor_payment, vendor_advance, debit_note, expense, employee_advance, advance_settlement, advance_refund, contra, customer_refund, retention_release, salary_accrual, salary_payment |
| line_role | VARCHAR(40) | e.g. `receivable`, `revenue`, `vat_output`, `retention`, `advance_offset`, `cash`, `ait`, `payable`, `tds`, `expense`, `bank_charges` |
| account_source | VARCHAR(20) | `fixed` (account_id), `setting` (key), `document` (from line/header field), `party` (party's own account or control) |
| account_id | BIGINT | when fixed |
| setting_key | VARCHAR(80) | when setting |
| business_line_id | BIGINT | optional override per business line |
| is_active | TINYINT | |

`JournalPoster` reads rules per document type; builders in code define which roles exist and amounts; accounts come from rules → finance can remap without code.

### 3.15 Recurring journals

`recurring_journals`: `[STD], name, journal template JSON (lines), frequency (lookup: MONTHLY · QUARTERLY · YEARLY), day_of_month, next_run_date, end_date, auto_post TINYINT, is_active` — e.g. office rent accrual, depreciation.

### 3.16 Opening balances

`opening_balance_batches`: `[STD], as_of_date, status (DRAFT/POSTED), journal_entry_id, notes`.
`opening_balance_lines`: `id, batch_id, account_id, party_type, party_id, project_id, reference (e.g. legacy invoice no.), document_date, due_date, debit, credit`.
Party-level AR/AP lines also create **opening invoices/bills** (type OPENING, status APPROVED, no revenue posting) so allocation and aging work after cut-over.

### 3.17 `account_balances` (cache)

`account_id, fiscal_period_id, project_id NULL, debit_total, credit_total` — maintained by `JournalPoster` on post/reverse; `php artisan accounting:rebuild-balances` recomputes from lines. Reports may read lines directly; cache is optimisation only.

---

## 4. Journal Engine

```php
interface Postable {
    public function journalLines(): JournalDraft;   // built by document-specific builder
}

final class JournalPoster {
    public function post(Postable $doc, Carbon $date, string $narration): JournalEntry;
    public function reverse(JournalEntry $entry, Carbon $date, string $reason): JournalEntry;
}
```

Posting algorithm (inside caller's transaction):

1. Resolve fiscal period for date; abort if none or locked (`PeriodLockedException`).
2. Build lines from document via builder + posting rules.
3. Drop zero lines; round each to 2 dp; if |Σdr − Σcr| ≤ 0.05 → add rounding line to rounding account; else abort (`UnbalancedJournalException`).
4. Validate each line: account postable & active; control accounts carry their party id; `requires_project` accounts carry project_id.
5. Insert `journal_entries` (status POSTED, number from sequence) and lines (`is_posted = 1`, `entry_date`).
6. Update `account_balances` cache.
7. Set `journal_entry_id` on the source document; dispatch `JournalPosted` after commit.

Reversal: new entry with swapped debit/credit, `journal_type = REVERSAL`, `reversal_of_id`; original → REVERSED (still posted; both net to zero). Reversal date default = today (must be open period); allowed = original date if that period is open.

DB safety net: trigger or scheduled check `accounting:verify` — every posted entry balanced, Σ all lines dr = cr; alerts finance_manager on failure.

---

## 5. Settings (group `accounting`)

Control account keys (§3.3) plus:

| Key | Default |
|---|---|
| accounting.manual_journal_requires_approval | true |
| accounting.expense_pm_approval_limit | 50,000 |
| accounting.advance_settlement_days | 15 |
| accounting.max_open_advances_per_employee | 2 |
| accounting.cash_negative_block | true |
| accounting.lock_period_auto_after_days | 10 (auto-lock previous month on day 10; 0 = manual) |
| accounting.vat_input_claimable | false (VAT on purchases added to cost) |

---

## 6. Posting Rules (authoritative)

Notation: `AR(c,p)` = AR control with customer c, project p. Amounts reference document fields.

| # | Document / event | Debit | Credit |
|---|---|---|---|
| P01 | Invoice approved | AR(c,p) = receivable_amount; Retention Receivable(c,p) = retention_amount; Customer Advances(c) = advance_adjusted | Revenue per line (p, business line) = line amount net of line+header discount (header discount prorated); VAT Payable = vat_amount |
| P02 | Invoice cancelled | reversal of P01 | |
| P03 | Receipt posted | Cash/Bank (deposit acct) = amount_received; AIT Receivable(c) = ait_deducted; VAT Payable (VDS adj.) = vat_deducted | AR(c,p) = Σ allocations; Customer Advances(c,p) = unallocated |
| P04 | Later allocation of advance to invoice | Customer Advances(c) | AR(c,p) |
| P05 | Credit note approved | Revenue per line (p); VAT Payable | AR(c,p) (or Customer Advances if invoice fully paid → refundable) |
| P06 | Cheque bounced | AR(c,p) / Customer Advances (reverse of P03 credit side); AIT Receivable reversal if any; Bank Charges = charges | Bank = amount + charges |
| P07 | Customer refund | Customer Advances(c) | Cash/Bank |
| P08 | Retention release invoice | AR(c,p) | Retention Receivable(c,p) |
| P09 | Vendor bill approved | Expense/Direct cost per line (p, cost category) = amount (+ VAT if not claimable); VAT input (if claimable) | AP(v,p) = payable_amount; TDS Payable(v) = tds; VAT Payable (VDS) = vds; Retention Payable(v,p) = retention; Vendor Advances(v,p) = advance_recovered |
| P10 | Vendor payment | AP(v,p) = Σ allocations; Bank Charges | Cash/Bank |
| P11 | Vendor advance | Vendor Advances(v,p) | Cash/Bank |
| P12 | Debit note | AP(v,p) | Expense/Direct cost per line |
| P13 | Retention release to vendor | Retention Payable(v,p) | Cash/Bank |
| P14 | Expense DIRECT_PAID posted | Expense per line (p) | Cash/Bank (paid_from) |
| P15 | Expense CLAIM approved | Expense per line (p) | Employee payable — Advances to Employees(e) credit (negative advance = owed to employee) |
| P16 | Claim reimbursed | Advances to Employees(e) | Cash/Bank |
| P17 | Employee advance disbursed | Advances to Employees(e,p) | Cash/Bank |
| P18 | Advance settlement (expense FROM_ADVANCE) | Expense per line (p) | Advances to Employees(e,p) |
| P19 | Advance refund by employee | Cash | Advances to Employees(e) |
| P20 | Contra (cash → bank deposit) | Bank | Cash |
| P21 | Contra (bank → cash withdrawal) | Cash | Bank |
| P22 | Contra (bank → bank) | Bank B (+ charges to Bank Charges) | Bank A |
| P23 | Salary accrual | Salaries & Allowances (dept / project allocation) | Salary Payable(e) |
| P24 | Salary payment | Salary Payable(e) | Cash/Bank |
| P25 | TDS / VAT deposit to government | TDS Payable / VAT Payable | Bank |
| P26 | Opening balances | per batch lines | per batch lines; difference → Opening Balance Equity |
| P27 | Year-end close | each Revenue account (close to zero) | each Expense account; net → Retained Earnings |

---

## 7. Screens

### 7.1 Chart of accounts (`/accounting/accounts`)
Tree grid: Code, Name, Type, Group, Postable, Control, Bank/Cash, Requires project, Balance (as of date), Active. Actions: add child, edit, deactivate (only zero balance & no draft docs), view ledger. Import from Excel (initial setup).

### 7.2 Fiscal years & periods (`/accounting/fiscal`)
Create year (auto 12 monthly periods + period 13). Period grid with lock/unlock toggles (reason required). Year-end close wizard (§8.3).

### 7.3 Journals (`/accounting/journals`)
List: JV #, Date, Type, Source doc (link), Voucher no., Narration, Debit, Credit, Status, Created by.
Filters: type, date, status, account, party, project, source type.
Manual journal form: Date*, Voucher no., Narration*, lines grid (Account*, Debit, Credit, Description, Customer/Vendor/Employee (required for control accounts), Project (required where account requires), Cost category); live totals and difference; Submit → Post (approval per setting). System journals are view-only with "Open source document".
Actions: Reverse (reason), Print voucher (Journal Voucher layout).

### 7.4 Bank & cash accounts (`/accounting/bank-accounts`)
Cards per account: name, kind, number, current balance, unreconciled count. Form §3.9 (creates CoA leaf under 1110/1120/1125 automatically).

### 7.5 Cash & bank transactions (replaces legacy Cash Transaction / Bank Transactions)
`/accounting/money` — unified register of `payments` + receipts with filters (account, type, date). Quick actions:
- **Receive money (other)** — OTHER_RECEIPT: from party/other, to account, counter account (income/liability), project.
- **Pay money (other)** — OTHER_PAYMENT: from account, counter account (expense/asset), project.
- **Transfer (contra)** — from account, to account, amount, charges.
Each prints Credit/Debit/Contra voucher.

### 7.6 Bank reconciliation (`/accounting/reconciliation`)
Pick bank account + statement date + statement closing balance → list of unreconciled lines up to date (date, doc, description, deposit, withdrawal) with checkboxes; optional CSV import for auto-match (amount + date ± 3 days + ref) → difference must be 0 to complete.

### 7.7 Expenses (`/accounting/expenses`)
List: EXP #, Date, Kind, Employee/Payee, Project, Description, Amount, Status.
Form (simple, mobile-friendly): Kind*, Date*, Project, Employee (default me for CLAIM), Payee, Paid from (DIRECT_PAID), Advance (FROM_ADVANCE — lists employee's open advances with balance), lines (Category*, Description, Amount*, Project, Receipt photo). Submit → Approve (PM for own projects ≤ limit, else finance) → Post.

### 7.8 Employee advances (`/accounting/advances`)
List: ADV #, Employee, Project, Purpose, Date, Amount, Settled, Refunded, Balance, Due settlement, Status (overdue highlight).
Create → disbursement payment (from cash/bank). Detail: settlements (expenses), refunds, top-ups; "Settle" opens FROM_ADVANCE expense prefilled; "Record refund".

### 7.9 Tax (`/accounting/tax`)
Tax rates CRUD; Tax payable summary (VAT output, VDS, TDS by vendor, AIT receivable by customer) for a period; "Record deposit" (P25) with challan no. attachment.

### 7.10 Opening balances (`/accounting/opening`)
Batch form: as-of date; tabs **GL accounts** (account, dr, cr), **Customers** (customer, project, ref, date, due, amount), **Vendors** (vendor, project, ref, date, due, amount), **Employee advances**, **Bank/cash**; Excel import per tab; totals and difference to Opening Balance Equity; Post (once; reversible only by finance_manager before any other posting in that period).

### 7.11 Posting rules (`/accounting/posting-rules`)
Grid by document type: role, account source, account/setting, business-line override. Test panel: pick a sample document → preview journal.

### 7.12 Recurring journals — list/form; "Run now"; log of generated entries.

---

## 8. Workflows

### 8.1 Manual journal
DRAFT → SUBMITTED → POSTED → (REVERSED). If approval not required: DRAFT → POSTED by finance_manager.

### 8.2 Period lock
Monthly: finance manager reviews (checklist: bank reconciled, advances reviewed, draft documents cleared, recurring run) → Lock. Auto-lock per setting. Unlock needs reason; audited; notifies management.

### 8.3 Year-end close
1. All 12 periods locked; period 13 open for adjustments.
2. Wizard shows TB; runs checks (balanced, no drafts, suspense = 0).
3. Generates YEAR_END journal (P27) dated year end in period 13.
4. Lock period 13; year status CLOSED; next year opening balances = BS balances (computed, no journal needed).

### 8.4 Expense
DRAFT → SUBMITTED → APPROVED → POSTED (journal) ; REJECTED (back to employee with note) ; CANCELLED (reversal if posted).

---

## 9. Business Rules

| ID | Rule |
|---|---|
| AC-BR-01 | Every posted journal balances; Σ debit = Σ credit (engine + nightly verify). |
| AC-BR-02 | Posting only to active postable (leaf) accounts. |
| AC-BR-03 | Control accounts require their party id on the line; manual journals to control accounts require `journals.post` and a party. |
| AC-BR-04 | Accounts with `requires_project` need project_id. |
| AC-BR-05 | No posting/editing in locked periods or closed years; document dates validated against period. |
| AC-BR-06 | Posted journals are immutable; only reversal. |
| AC-BR-07 | Account type of child = parent; account with balance or history cannot change type or be deleted. |
| AC-BR-08 | Cash accounts cannot go negative on any date when `cash_negative_block` (checked at post). |
| AC-BR-09 | Contra from and to accounts must differ and both be bank/cash. |
| AC-BR-10 | Expense lines above category `requires_receipt_above` need receipt attachment. |
| AC-BR-11 | An employee may have at most `max_open_advances_per_employee` open advances; new advance blocked while one is overdue (override finance_manager). |
| AC-BR-12 | Settlement + refund ≤ advance amount; excess spend creates claim payable to employee. |
| AC-BR-13 | Bank reconciliation can only complete with zero difference; reconciled lines cannot be reversed without un-reconciling (audited). |
| AC-BR-14 | Opening balances post once per account/party; batch must balance via Opening Balance Equity; OBE should be zero after full migration (warning). |
| AC-BR-15 | Year-end close requires all periods locked and TB balanced. |
| AC-BR-16 | Maker-checker: the user who submitted a manual journal/expense cannot approve it. |

---

## 10. Integration
In: all document approval/cancel events from 06, 07, 09; `ExpensePosted` and advances internal.
Out: `JournalPosted`, `JournalReversed` (→ 04 project financial refresh, 03 customer figures), `PeriodLocked`, `YearClosed`.
Provides: `JournalPoster`, `LedgerQuery` (balances by account/party/project/date), `PeriodGuard`.

## 11. Notifications
`accounting.journal_submitted` → finance_manager · `accounting.expense_submitted` → approver · `accounting.expense_rejected` → employee · `accounting.advance_overdue` (daily) → employee, accountant · `accounting.period_unlocked` → management · `accounting.verify_failed` → finance_manager, super_admin.

## 12. Reports (columns in 10)
General Ledger · Trial Balance (with opening, period movement, closing) · Profit & Loss (by period, business line, project; comparison) · Balance Sheet · Cash Flow (indirect) · Cash Book (legacy Cash Ledger: date, description, cash in, cash out, balance) · Bank Book (legacy Bank Transaction Report) · Day Book · Expense report (by category, project, employee) · Employee advance ledger & aging · Tax payable summary · Account ledger for any account/party/project · Voucher prints.

## 13. Components (indicative)

```text
Livewire: Accounting\Accounts\Tree, Accounting\Fiscal, Accounting\Journals\Index|Form|Show,
          Accounting\BankAccounts, Accounting\Money\Register|Receive|Pay|Transfer,
          Accounting\Reconciliation, Accounting\Expenses\Index|Form, Accounting\Advances\Index|Show,
          Accounting\Tax, Accounting\Opening, Accounting\PostingRules, Accounting\Recurring
Services: JournalPoster, PostingRuleResolver, PeriodGuard, LedgerQuery, BalanceCache,
          builders: InvoiceJournalBuilder, ReceiptJournalBuilder, BillJournalBuilder, …
Commands: accounting:verify, accounting:rebuild-balances, accounting:run-recurring,
          accounting:auto-lock
```

## 14. Acceptance Criteria

| ID | Criterion |
|---|---|
| AC-AC-01 | Posting an unbalanced manual journal (dr 1,000 / cr 900) is refused. |
| AC-AC-02 | Any document dated in a locked period is refused at approval with the period name. |
| AC-AC-03 | Reversing a posted invoice journal leaves AR and revenue balances as before the invoice. |
| AC-AC-04 | Site transport expense ৳25,000 for project #102 paid from Bank posts Dr Transport (p=102) / Cr Bank and appears in project cost and Bank Book. |
| AC-AC-05 | Employee advance ৳20,000; settlement with ৳17,500 receipts + ৳2,500 refund closes the advance with zero balance. |
| AC-AC-06 | Cash payment exceeding cash balance is blocked. |
| AC-AC-07 | Trial balance Σ debit = Σ credit for any date range; AR control = AR aging; AP control = AP aging; Bank = Bank book. |
| AC-AC-08 | Year-end close moves net profit to Retained Earnings and P&L accounts start the new year at zero. |
| AC-AC-09 | Changing posting rule for "Design Fees" to a new revenue account affects only invoices approved afterwards. |
| AC-AC-10 | `accounting:verify` reports zero issues on migrated data. |

## 15. Open Questions
1. Fiscal year: July–June (Bangladesh standard) — confirm.
2. Is SOC VAT-registered; is input VAT claimable?
3. Salary: process in system (P23/P24) or only record payments?
4. Petty cash custodians and limits.
5. Bank accounts list (bank, branch, number) for setup.
