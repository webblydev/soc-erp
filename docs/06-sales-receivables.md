# SOC ERP v2 — 06 · Sales Billing & Receivables (Running Bills, Invoices, Receipts, Credit Notes)

**Build phase:** 6 · **Depends on:** 01–05, 08 (journal engine) · **Used by:** 03, 04, 10
**Replaces (legacy):** Project Bill Entry / Record, Client Transactions (Receive), task bill/collect/due fields, Client Trans Ledger, Client Ledger, Monthly Revenue Report data

---

## 1. Purpose & Scope

- **Customer running bills** from verified MB entries (cumulative / previous / this bill).
- **Invoices** — from milestone schedule, running bill, project services, or manual; with VAT, AIT expectation, retention, discount.
- **Receipts** (money receipts) — cash, bank transfer, cheque, mobile banking; allocation to invoices; advances.
- **Credit notes** — reduce invoiced amounts (cancellation, discount after billing, scope reduction).
- **Retention release** invoices.
- Customer statement and AR aging (data; report specs in 10).

Every approved document posts a journal through the Accounting module (08 §6 posting rules). Business documents are the UI; the GL is the truth.

---

## 2. Permissions

```text
sales.running_bills.view | create | update | submit | approve | cancel | print
sales.invoices.view_own | view_all | create | update | submit | approve | cancel | print | export | override_credit | backdate
sales.receipts.view | create | update | post | cancel | print | allocate | backdate
sales.credit_notes.view | create | approve | cancel | print
sales.statements.view | send
```

| Permission | management | finance_manager | accountant | project_manager | sales_mgr/exec |
|---|---|---|---|---|---|
| running bills create/submit | ✓ | — | ✓ | ✓ | — |
| running bills approve | ✓ | ✓ | — | ✓ (own) | — |
| invoices view | all | all | all | own projects | own customers |
| invoices create/submit | ✓ | ✓ | ✓ | ✓ (own) | — |
| invoices approve (post) | ✓ | ✓ | — | — | — |
| invoices cancel | ✓ | ✓ | — | — | — |
| receipts create | ✓ | ✓ | ✓ | — | — |
| receipts post | ✓ | ✓ | ✓ (≤ limit) | — | — |
| receipts cancel | ✓ | ✓ | — | — | — |
| credit notes approve | ✓ | ✓ | — | — | — |
| backdate (period open) | ✓ | ✓ | — | — | — |

---

## 3. Data Model

### 3.1 Lookups

| Table | Extra | Seed |
|---|---|---|
| `invoice_types` | — | MILESTONE · RUNNING_BILL · SERVICE (lump) · ADVANCE · RETENTION_RELEASE · OTHER |
| `invoice_statuses` | `is_open`, `is_posted`, `is_cancelled` | DRAFT · SUBMITTED · APPROVED (posted, open) · PARTIALLY_PAID (posted, open) · PAID (posted) · CANCELLED |
| `running_bill_statuses` | — | DRAFT · SUBMITTED · APPROVED · INVOICED · CANCELLED |
| `receipt_statuses` | `is_posted` | DRAFT · POSTED · BOUNCED (cheque) · CANCELLED |
| `payment_methods` (shared with 07/08) | `requires_reference`, `requires_cheque_details`, `default_account_id` | CASH · BANK_TRANSFER · CHEQUE · PAY_ORDER · CARD · BKASH · NAGAD · ROCKET · OTHER |
| `credit_note_reasons` | — | Discount · Scope reduction · Billing error · Project cancelled · Other |

### 3.2 `customer_running_bills`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| bill_number | VARCHAR(40) | no | UNIQUE `RB-27-0003` |
| project_id | BIGINT | no | |
| customer_id | BIGINT | no | |
| bill_sequence | SMALLINT | no | 1st, 2nd … running bill of project |
| is_final | TINYINT(1) | no | |
| bill_date | DATE | no | |
| period_from / period_to | DATE | yes | |
| gross_to_date | DECIMAL(18,2) | no | cumulative value of work done |
| previous_billed | DECIMAL(18,2) | no | Σ earlier approved RBs gross |
| this_bill_gross | DECIMAL(18,2) | no | gross_to_date − previous_billed |
| retention_amount | DECIMAL(18,2) | no | this_bill_gross × retention % |
| advance_recovery | DECIMAL(18,2) | no | recovery of mobilisation advance |
| net_amount | DECIMAL(18,2) | no | |
| status_id | BIGINT | no | |
| invoice_id | BIGINT | yes | |
| approved_by / approved_at | | yes | |
| notes | TEXT | yes | |
| [AUDIT] | | | |

`customer_running_bill_lines`: `id, running_bill_id, work_item_id, estimate_line_id, description, unit_id, contract_qty, previous_qty, this_qty, cumulative_qty, rate, this_amount, cumulative_amount, achievement_pct` — built from MB entries (each MB entry links to a line).

### 3.3 `invoices`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| invoice_number | VARCHAR(40) | no | UNIQUE (assigned on save) |
| invoice_type_id | BIGINT | no | |
| customer_id | BIGINT | no | |
| project_id | BIGINT | yes | required unless type OTHER |
| business_line_id | BIGINT | no | from project |
| running_bill_id | BIGINT | yes | |
| payment_schedule_id | BIGINT | yes | |
| invoice_date | DATE | no | |
| due_date | DATE | no | invoice_date + payment term days |
| reference | VARCHAR(100) | yes | customer PO / work order ref |
| subtotal | DECIMAL(18,2) | no | Σ line amounts |
| discount_amount | DECIMAL(18,2) | no | header discount |
| vat_amount | DECIMAL(18,2) | no | Σ line VAT |
| retention_amount | DECIMAL(18,2) | no | withheld by customer (Dr Retention Receivable) |
| advance_adjusted | DECIMAL(18,2) | no | customer advance applied at invoicing |
| total_amount | DECIMAL(18,2) | no | subtotal − discount + VAT |
| receivable_amount | DECIMAL(18,2) | no | total − retention − advance_adjusted (what AR carries) |
| expected_ait_amount | DECIMAL(18,2) | no | info: customer may deduct AIT at source |
| amount_paid | DECIMAL(18,2) | no | cached Σ allocations + credit notes |
| balance_due | DECIMAL(18,2) | no | cached receivable − paid |
| invoice_status_id | BIGINT | no | |
| journal_entry_id | BIGINT | yes | |
| submitted_by/at, approved_by/at, cancelled_by/at | | yes | |
| cancel_reason | TEXT | yes | |
| terms | TEXT | yes | print terms |
| notes | TEXT | yes | internal |
| legacy_ref | VARCHAR(60) | yes | |
| [AUDIT] | | | |

Indexes: customer_id, project_id, invoice_status_id, invoice_date, due_date.

### 3.4 `invoice_items`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT | no | |
| invoice_id | BIGINT | no | |
| project_service_id | BIGINT | yes | |
| service_id | BIGINT | yes | |
| description | TEXT | no | |
| quantity | DECIMAL(18,4) | no | |
| unit_id | BIGINT | yes | |
| unit_price | DECIMAL(18,4) | no | |
| discount_amount | DECIMAL(18,2) | no | |
| amount | DECIMAL(18,2) | no | round(qty × price) − discount |
| tax_rate_id | BIGINT | yes | VAT |
| vat_amount | DECIMAL(18,2) | no | |
| revenue_account_id | BIGINT | no | resolved per 02 §5 |
| project_id | BIGINT | yes | |
| sort_order | SMALLINT | no | |

### 3.5 `receipts`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| receipt_number | VARCHAR(40) | no | UNIQUE `RCV-27-00041` |
| money_receipt_no | VARCHAR(40) | yes | printed MR book no. (legacy `receipt_no`); UNIQUE when not null |
| customer_id | BIGINT | no | |
| project_id | BIGINT | yes | |
| receipt_date | DATE | no | |
| payment_method_id | BIGINT | no | |
| deposit_account_id | BIGINT | no | FK accounts (cash/bank; for cheques = "Cheques in hand" or bank) |
| amount_received | DECIMAL(18,2) | no | money actually received |
| ait_deducted | DECIMAL(18,2) | no | default 0 (customer withheld tax; certificate) |
| vat_deducted | DECIMAL(18,2) | no | default 0 (VDS) |
| total_settled | DECIMAL(18,2) | no | received + AIT + VDS |
| cheque_no | VARCHAR(40) | yes | |
| cheque_date | DATE | yes | |
| cheque_bank | VARCHAR(100) | yes | |
| transaction_ref | VARCHAR(100) | yes | bank/bKash trx id |
| received_by_employee_id | BIGINT | yes | collector |
| unallocated_amount | DECIMAL(18,2) | no | cached |
| receipt_status_id | BIGINT | no | |
| journal_entry_id | BIGINT | yes | |
| bounce_journal_entry_id | BIGINT | yes | |
| notes | TEXT | yes | |
| [AUDIT] | | | |

### 3.6 `receipt_allocations`

`id, receipt_id, invoice_id, amount DECIMAL(18,2), allocated_at, allocated_by, reversed_at` — UNIQUE active (receipt_id, invoice_id).

### 3.7 `credit_notes`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| credit_note_number | VARCHAR(40) | no | UNIQUE |
| customer_id | BIGINT | no | |
| project_id | BIGINT | yes | |
| invoice_id | BIGINT | yes | applied to |
| credit_note_date | DATE | no | |
| credit_note_reason_id | BIGINT | no | |
| subtotal / vat_amount / total_amount | DECIMAL(18,2) | no | |
| status | — | — | FK `credit_note_statuses`: DRAFT · APPROVED · CANCELLED |
| journal_entry_id | BIGINT | yes | |
| notes | TEXT | yes | |
| [AUDIT] | | | |

`credit_note_items`: same shape as invoice_items.

---

## 4. Settings (group `sales`)

| Key | Default |
|---|---|
| sales.invoice_requires_submit_approve | true (maker-checker) |
| sales.receipt_post_limit_accountant | 500,000 |
| sales.default_payment_term | NET15 |
| sales.default_invoice_terms | text |
| sales.cheque_clearing_mode | `direct_to_bank` (`direct_to_bank` or `cheques_in_hand`) |
| sales.allow_over_receipt | true (excess becomes advance) |
| sales.amount_in_words_locale | en_BD |

---

## 5. Screens

### 5.1 Running bills (`/sales/running-bills`)
List: RB #, Project, Customer, Seq, Date, This bill gross, Retention, Net, Status, Invoice.
Create: pick project → system lists VERIFIED customer-direction MB entries not yet billed, grouped by work item / BOQ line → select → grid shows contract qty, previous qty, this qty, cumulative, rate, amounts, achievement % (legacy *Project Bill Record* columns: Item code, MB page, Floor/Location, Description, Unit, Qty, Rate, Amount, Achievement) → retention % (from contract), advance recovery → Submit → Approve → **Generate invoice** (type RUNNING_BILL).

### 5.2 Invoices — List (`/sales/invoices`)
Columns: Invoice #, Date, Customer, Project, Type, Total, Retention, Receivable, Paid, Balance, Due date, Days overdue, Status.
Filters: status, customer, project, business line, type, date, due/overdue, created by.
Bulk: print, export, send statement.

### 5.3 Invoice — Form
Header: Type*, Customer*, Project* (filters by customer; shows contract value, invoiced to date, unbilled), Invoice date*, Payment term → Due date, Reference, Terms.

Create-from helpers (buttons): **From milestone** (pick DUE schedule line → one line at milestone amount, revenue split by project services proportionally or chosen service) · **From running bill** · **From project services** (pick services & qty/percent to bill; shows remaining unbilled per service) · **Advance** (customer advance invoice) · **Retention release** (shows retention held).

Lines grid: Service, Description, Qty, Unit, Price, Discount, Amount, VAT rate, VAT, Revenue account (finance roles can change).

Footer: Subtotal, Header discount, VAT, Total, Retention (– %), Advance adjusted (pick from customer's unallocated advance), **Receivable**, Expected AIT (info), Amount in words.

Credit check banner if customer credit limit exceeded.

### 5.4 Invoice — Detail
Header actions by status: DRAFT (Edit, Submit, Delete) · SUBMITTED (Approve, Return to draft) · APPROVED/PARTIAL (Receive payment, Credit note, Print, Email/WhatsApp PDF, Cancel) · PAID (Print, Credit note).
Tabs: Lines · Payments (allocations, credit notes) · Journal (posted entry lines) · Documents · History.

### 5.5 Receipts — List (`/sales/receipts`)
Columns: Receipt #, MR no., Date, Customer, Project, Method, Account, Received, AIT, VDS, Allocated, Unallocated, Status, Collector.

### 5.6 Receipt — Form
Customer* → shows open invoices (number, date, project, balance, overdue) and unallocated advances.
Fields: Date*, Project, Method*, Deposit account* (filtered: cash accounts for CASH, bank accounts for transfer/cheque/card, wallet for bKash/Nagad), Amount received*, AIT deducted, VDS deducted, MR no., Cheque no./date/bank (if method requires), Trx ref, Collector, Notes.
Allocation grid: auto-allocate oldest first (button) or manual amounts per invoice; remaining → unallocated advance.
Post → journal; Print money receipt (A5 / half-page, with amount in words, method details, signature).

Cheque bounce (action on posted cheque receipt): date, bank charges → reversal journal, allocations reversed, invoice balances restored, receipt → BOUNCED, notify account manager.

### 5.7 Allocate advance (screen on customer)
Unallocated receipts ↔ open invoices matching grid; saving allocations posts a reclass journal Dr Customer Advances / Cr AR (08 §6, P04), because unallocated receipt amounts are always held in Customer Advances.

### 5.8 Credit notes (`/sales/credit-notes`)
Form: Customer*, Project, Invoice (optional; limits amount to invoice balance), Date*, Reason*, lines (copy from invoice), notes → Approve → journal; applied to invoice balance.

### 5.9 Customer statement (from customer detail, also `/sales/statements`)
Date range; opening balance; lines (date, document, project, description, debit, credit, running balance); closing; aging buckets footer. PDF/Excel; send by email.

---

## 6. Workflows

### 6.1 Invoice
```text
DRAFT → SUBMITTED → APPROVED (journal posted) → PARTIALLY_PAID → PAID
  ↑          │                 │                       │
  └─ return ─┘                 └──────── CANCELLED (reversal journal; only if no allocations — else unallocate first or use credit note)
```
If `invoice_requires_submit_approve` = false, Approve directly from DRAFT (still needs `approve` permission).

### 6.2 Receipt
```text
DRAFT → POSTED (journal) → CANCELLED (reversal) | BOUNCED (cheque; reversal + charges)
```

### 6.3 Payment schedule linkage
Invoice from schedule line → schedule INVOICED. When invoice becomes PAID → schedule PAID.

---

## 7. Business Rules

| ID | Rule |
|---|---|
| SL-BR-01 | Invoice needs ≥ 1 line, total > 0, customer ACTIVE (not BLOCKED), project `allows_billing`. |
| SL-BR-02 | Invoice date must be in an open fiscal period; backdating beyond `today − 3 days` needs `backdate`. |
| SL-BR-03 | Σ invoiced per project service (net of credit notes) cannot exceed the service contract amount unless an amendment exists or user has `override_contract` (logged). |
| SL-BR-04 | Approving an invoice posts the journal in the same DB transaction; failure rolls back approval. |
| SL-BR-05 | Approved invoices are immutable; corrections via credit note or cancel. |
| SL-BR-06 | Cancel allowed only when no active allocations/credit notes; creates reversal journal dated cancel date (or original date if period open and user chooses). |
| SL-BR-07 | Receipt total_settled = received + AIT + VDS; Σ allocations ≤ total_settled. |
| SL-BR-08 | Allocation to an invoice ≤ its balance_due; only same customer. |
| SL-BR-09 | Cheque/transfer methods require reference fields per payment method flags. |
| SL-BR-10 | MR no. unique; warn if gap/out-of-sequence vs last MR used. |
| SL-BR-11 | Accountant can post receipts ≤ `receipt_post_limit_accountant`; above needs finance_manager. |
| SL-BR-12 | Invoice status auto-derived: balance_due = 0 → PAID; 0 < paid < receivable → PARTIALLY_PAID. |
| SL-BR-13 | Running bill this_qty per line = cumulative MB qty − previous billed qty; cannot be negative unless final-bill adjustment by PM. |
| SL-BR-14 | Only one DRAFT/SUBMITTED running bill per project at a time. |
| SL-BR-15 | Credit note total ≤ linked invoice balance + paid (if refund needed → customer refund payment via 08). |
| SL-BR-16 | Retention release invoice ≤ retention held for the project. |
| SL-BR-17 | Customer credit limit check at approval (CRM-BR-15). |
| SL-BR-18 | Amount in words generated in English BD style ("Taka Five Lac Twenty Thousand Only"). |

---

## 8. Posting (summary — authoritative table in 08 §6)

| Document | Debit | Credit |
|---|---|---|
| Invoice | AR (customer, project) = receivable_amount; Retention Receivable = retention; Customer Advances = advance_adjusted | Revenue (per line, project); VAT Payable |
| Receipt | Cash/Bank = received; AIT Receivable = AIT; VDS Receivable/VAT Payable adj. = VDS | AR (customer, project) = allocated; Customer Advances = unallocated |
| Credit note | Revenue (per line); VAT Payable | AR (customer, project) |
| Cheque bounce | AR / Customer Advances (reverse); Bank charges expense | Bank |
| Retention release invoice | AR | Retention Receivable |

---

## 9. Integration
Out: `InvoiceApproved`, `InvoiceCancelled`, `ReceiptPosted`, `ReceiptBounced`, `CreditNoteApproved` → 08 journal, 04 financial snapshot & schedule, 03 customer figures.
In: `MeasurementVerified` (05), `MilestoneDue` (04), period lock (08).

## 10. Notifications
`sales.invoice_submitted` → approvers · `sales.invoice_approved` → PM, account manager · `sales.invoice_overdue` (daily, 1/7/30 days) → account manager, accountant · `sales.receipt_posted` (> threshold) → management · `sales.cheque_bounced` → account manager, finance manager.

## 11. Reports
Invoice register · Receipt register / collection report (by method, collector, account) · AR aging (0–30, 31–60, 61–90, 91–180, 180+) · Customer statement · Project billing summary · Monthly revenue (legacy columns) · Unbilled contract · Retention held · Cheques in hand / bounced · AIT certificates pending.

## 12. Components (indicative)

```text
Livewire: Sales\RunningBills\Index|Form|Show, Sales\Invoices\Index|Form|Show,
          Sales\Receipts\Index|Form|Show, Sales\Allocate, Sales\CreditNotes\Index|Form,
          Sales\Statement
Actions:  BuildRunningBill, ApproveRunningBill, CreateInvoiceFromMilestone|RunningBill|Services,
          SaveInvoice, SubmitInvoice, ApproveInvoice, CancelInvoice, SaveReceipt, PostReceipt,
          AllocateReceipt, BounceCheque, CancelReceipt, ApproveCreditNote
Services: InvoiceTotals, AmountInWords, CustomerBalance
```

## 13. Acceptance Criteria

| ID | Criterion |
|---|---|
| SL-AC-01 | Milestone "After design approval 30 %" on a ৳5,00,000 deed creates a ৳1,50,000 invoice; approving posts Dr AR 1,50,000 / Cr Design Fees 1,50,000 with project tag. |
| SL-AC-02 | Receipt ৳1,40,000 + AIT ৳10,000 against that invoice marks it PAID; journal Dr Bank 1,40,000, Dr AIT Receivable 10,000, Cr AR 1,50,000. |
| SL-AC-03 | Receipt ৳2,00,000 with only ৳1,50,000 open invoices leaves ৳50,000 unallocated advance shown on customer. |
| SL-AC-04 | Cheque bounce restores invoice to APPROVED with full balance and posts bank charges. |
| SL-AC-05 | Cancelling an invoice with allocations is refused with instructions. |
| SL-AC-06 | Running bill #2 shows previous qty from bill #1 and bills only the difference. |
| SL-AC-07 | AR aging total equals AR control account balance in trial balance. |
| SL-AC-08 | Money receipt print shows MR no., amount in words, method, cheque details. |

## 14. Open Questions
1. Is SOC VAT-registered and which services are VAT-able at what rate? Do corporate customers deduct VDS?
2. Should invoices be emailed / WhatsApp'd from the system?
3. Physical MR books — continue using printed numbers or system numbers only?
4. Are advances usually taken before contract (needs ADVANCE invoice) or simply received as receipts?
