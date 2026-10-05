# SOC ERP v2 — 07 · Purchases & Payables (Vendors, Work Orders, Vendor Bills, Vendor Payments)

**Build phase:** 6 · **Depends on:** 01, 02, 04, 05, 08 · **Used by:** 04, 05, 10
**Replaces (legacy):** Settings › Vendor Entry; Vendor Bill Entry / Record; Vendor Expense (Bill Receive / Bill Payment); Vendor Ledger; Vendor Expense Report; vendor-type account holders

---

## 1. Purpose & Scope

- Vendor master (suppliers, contractors, subcontractors, consultants, service providers, landlords, utilities).
- **Work orders** (contract with a vendor for a project scope; legacy "Work Order No." on Vendor Ledger).
- **Vendor running bills** from verified vendor-direction MB entries.
- **Vendor bills** — material/service bills with project & cost category, VAT, TDS (tax deducted at source) and retention.
- **Vendor advances** and **vendor payments** with allocation to bills.
- **Debit notes** (returns, penalties, rate corrections).

Purchase requisitions / purchase orders for stock are **future** (SOC has no inventory module in v2; materials are expensed to projects on bill).

---

## 2. Permissions

```text
purchases.vendors.view | create | update | deactivate | export
purchases.work_orders.view | create | update | submit | approve | close | cancel | print
purchases.running_bills.view | create | submit | approve | cancel | print
purchases.bills.view | create | update | submit | approve | cancel | print | backdate
purchases.payments.view | create | post | cancel | print
purchases.debit_notes.view | create | approve | cancel
```

| Permission | management | finance_manager | accountant | project_manager | engineer |
|---|---|---|---|---|---|
| vendors create/update | ✓ | ✓ | ✓ | ✓ | — |
| WO create/submit | ✓ | — | — | ✓ | — |
| WO approve | ✓ (any) | — | — | ✓ (≤ limit) | — |
| vendor RB create/submit | ✓ | — | ✓ | ✓ | ✓ (own projects) |
| vendor RB approve | ✓ | — | — | ✓ | — |
| bills create/submit | ✓ | ✓ | ✓ | ✓ | — |
| bills approve | ✓ | ✓ | — | — | — |
| payments create | ✓ | ✓ | ✓ | — | — |
| payments post | ✓ | ✓ | ✓ (≤ limit) | — | — |

---

## 3. Data Model

### 3.1 Lookups

| Table | Extra | Seed |
|---|---|---|
| `vendor_categories` | `default_expense_account_id`, `default_tds_rate_id` | SUPPLIER Material Supplier · CONTRACTOR · SUBCONTRACTOR · CONSULTANT · SERVICE Service Provider · LANDLORD · UTILITY · TRANSPORT · OTHER |
| `work_order_statuses` | — | DRAFT · SUBMITTED · APPROVED · CLOSED · CANCELLED |
| `vendor_running_bill_statuses` | — | DRAFT · SUBMITTED · APPROVED · BILLED · CANCELLED |
| `bill_statuses` | `is_open`, `is_posted` | DRAFT · SUBMITTED · APPROVED · PARTIALLY_PAID · PAID · CANCELLED |
| `vendor_payment_statuses` | — | DRAFT · POSTED · CANCELLED |
| `debit_note_reasons` | — | Return · Penalty · Rate correction · Short supply · Other |

### 3.2 `vendors`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| vendor_number | VARCHAR(40) | no | UNIQUE `V-0017` |
| vendor_category_id | BIGINT | no | |
| name | VARCHAR(200) | no | legacy `name` |
| enterprise_name | VARCHAR(200) | yes | legacy |
| contact_person | VARCHAR(150) | yes | |
| phone | VARCHAR(30) | no | legacy `contact_number` |
| email | VARCHAR(150) | yes | |
| address | TEXT | yes | |
| location_id | BIGINT | yes | |
| tin | VARCHAR(30) | yes | |
| bin | VARCHAR(30) | yes | |
| trade_license_no | VARCHAR(60) | yes | |
| bank_name / bank_branch / bank_account_name / bank_account_no / routing_no | VARCHAR | yes | for transfers |
| mobile_wallet_no | VARCHAR(30) | yes | bKash/Nagad |
| payable_account_id | BIGINT | yes | null → AP control |
| default_expense_account_id | BIGINT | yes | |
| default_tds_rate_id | BIGINT | yes | |
| payment_term_id | BIGINT | yes | |
| is_active | TINYINT(1) | no | |
| rating | TINYINT | yes | 1–5 |
| legacy_account_code | VARCHAR(40) | yes | legacy `account_id` / Acc_Code |
| notes | TEXT | yes | |
| [AUDIT] [SOFT] | | | |

`vendor_contacts`: `[STD], vendor_id, name, designation, phone, email, is_primary`.

### 3.3 `work_orders`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| work_order_number | VARCHAR(40) | no | UNIQUE `WO-27-0008` |
| vendor_id | BIGINT | no | |
| project_id | BIGINT | no | |
| order_date | DATE | no | |
| start_date / end_date | DATE | yes | |
| scope | TEXT | no | |
| subtotal | DECIMAL(18,2) | no | |
| vat_amount | DECIMAL(18,2) | no | |
| total_amount | DECIMAL(18,2) | no | |
| retention_pct | DECIMAL(7,4) | yes | |
| advance_amount | DECIMAL(18,2) | yes | mobilisation advance agreed |
| tds_rate_id | BIGINT | yes | |
| payment_terms_text | TEXT | yes | |
| status_id | BIGINT | no | |
| approved_by / approved_at | | yes | |
| billed_to_date | DECIMAL(18,2) | no | cached |
| [AUDIT] | | | |

`work_order_items`: `id, work_order_id, work_item_id, material_id, estimate_line_id, description, unit_id, quantity, rate, amount, cost_category_id, measured_qty (cached), billed_qty (cached)`.

`work_order_amendments`: `id, work_order_id, amendment_no, date, reason, value_change, approved_by, approved_at` (+ item changes logged).

### 3.4 `vendor_running_bills`
Same structure as `customer_running_bills` (06 §3.2) with `vendor_id`, `work_order_id` instead of customer; lines: `vendor_running_bill_lines` (work_order_item_id, contract_qty, previous_qty, this_qty, cumulative_qty, rate, this_amount, cumulative_amount, achievement_pct). Legacy *Vendor Bill Record* columns map 1:1.

### 3.5 `bills` (vendor bills)

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| bill_number | VARCHAR(40) | no | UNIQUE (system) |
| vendor_bill_no | VARCHAR(60) | yes | vendor's invoice no. (legacy `bill_number`); UNIQUE per vendor when not null |
| vendor_id | BIGINT | no | |
| project_id | BIGINT | yes | null = overhead bill (office rent, internet…) |
| work_order_id | BIGINT | yes | |
| vendor_running_bill_id | BIGINT | yes | |
| bill_date | DATE | no | |
| due_date | DATE | no | |
| subtotal | DECIMAL(18,2) | no | |
| vat_amount | DECIMAL(18,2) | no | |
| total_amount | DECIMAL(18,2) | no | |
| tds_amount | DECIMAL(18,2) | no | withheld by SOC → TDS Payable |
| vds_amount | DECIMAL(18,2) | no | VAT deducted at source (if applicable) |
| retention_amount | DECIMAL(18,2) | no | withheld → Retention Payable |
| advance_recovered | DECIMAL(18,2) | no | adjusts vendor advance |
| payable_amount | DECIMAL(18,2) | no | total − TDS − VDS − retention − advance_recovered |
| amount_paid / balance_due | DECIMAL(18,2) | no | cached |
| bill_status_id | BIGINT | no | |
| journal_entry_id | BIGINT | yes | |
| submitted/approved/cancelled by/at | | yes | |
| notes | TEXT | yes | |
| [AUDIT] | | | |

`bill_items`: `id, bill_id, work_order_item_id, material_id, description, quantity, unit_id, rate, amount, tax_rate_id, vat_amount, expense_account_id, cost_category_id, project_id, sort_order`.

### 3.6 Vendor payments
Use the common `payments` table (08 §3.8) with `payment_type = VENDOR_PAYMENT` or `VENDOR_ADVANCE`, `party_type = vendor`, and `payment_allocations` to bills. Screens live here.

### 3.7 `debit_notes`
`[STD], debit_note_number, vendor_id, project_id, bill_id, date, debit_note_reason_id, subtotal, vat_amount, total_amount, status (DRAFT/APPROVED/CANCELLED), journal_entry_id, notes, [AUDIT]` + `debit_note_items` like bill_items.

---

## 4. Settings (group `purchases`)

| Key | Default |
|---|---|
| purchases.wo_pm_approval_limit | 1,000,000 |
| purchases.payment_post_limit_accountant | 200,000 |
| purchases.require_wo_for_contractor_bills | true |
| purchases.bill_requires_submit_approve | true |
| purchases.three_way_check | true (bill qty ≤ measured/WO qty) |
| purchases.default_tds_by_category | from vendor_categories |

---

## 5. Screens

### 5.1 Vendors — List / Form / Detail
List: Vendor #, Name, Enterprise, Category, Phone, Open WOs, Billed (FY), Paid (FY), Payable, Advance balance, Active.
Form: fields §3.2 grouped Identity · Contact · Tax · Bank · Accounting defaults.
Detail tabs: Overview · Work orders · Bills · Payments · Advances · **Ledger** (legacy *Vendor Ledger*: Date, Work order no., Project, Bill no., Voucher no., Cash/Cheque, Bill amount, Total bill, Paid, Total paid, Due) · Documents · History.

### 5.2 Work orders
List: WO #, Date, Vendor, Project, Scope, Total, Measured, Billed, Paid, Retention held, Status.
Form: Vendor*, Project*, Order date*, Start/End, Scope*, Items grid (work item / material / description, unit, qty, rate, amount, cost category; "Pull from BOQ" picks estimate lines), VAT, Retention %, Advance, TDS rate, Payment terms → Submit → Approve → Print WO (letterhead, terms, signatures).
Detail: items with measured / billed qty progress bars; amendments; linked MB, running bills, bills, payments.

### 5.3 Vendor running bills
Create from verified vendor MB entries for a WO (same UX as 06 §5.1) → approve → **Generate vendor bill**.

### 5.4 Bills — List
Columns: Bill #, Vendor bill no., Date, Vendor, Project, WO, Total, TDS, Retention, Payable, Paid, Balance, Due, Status.
Filters: vendor, project, category, status, due/overdue, overhead vs project.

### 5.5 Bill — Form
Vendor* (defaults TDS, expense account, terms), Project (optional — overhead if blank), Work order (filtered by vendor+project), Bill date*, Vendor bill no., Due date.
Lines: material/work item, description, qty, unit, rate, amount, VAT, expense account, cost category, project (defaults header).
Footer: Subtotal, VAT, Total, TDS (rate → amount, editable), VDS, Retention (from WO %), Advance recovery (shows vendor advance balance), **Payable**.
Warnings: over WO value; duplicate vendor bill no.; budget exceeded for category (05).
Submit → Approve (journal).

### 5.6 Vendor payments
List: PV #, Date, Vendor, Project, Method, Account, Amount, Allocated, Status.
Form: Vendor* → open bills list (bill, project, balance, due) + advance option; Date*, Method*, From account*, Amount*, Cheque no./date, Trx ref, Notes; allocation grid (auto oldest first); type = Payment or **Advance** (no allocation; Dr Vendor Advances).
Post → journal; Print payment voucher (debit voucher — legacy "Db. Voucher No").

### 5.7 Debit notes — form like bill (negative effect), linked to bill.

---

## 6. Workflows

```text
Work order: DRAFT → SUBMITTED → APPROVED → CLOSED (all billed / manual) ; → CANCELLED (only if nothing billed)
Vendor RB:  DRAFT → SUBMITTED → APPROVED → BILLED (bill generated)
Bill:       DRAFT → SUBMITTED → APPROVED (journal) → PARTIALLY_PAID → PAID ; APPROVED → CANCELLED (no payments)
Payment:    DRAFT → POSTED (journal) → CANCELLED (reversal)
```

---

## 7. Business Rules

| ID | Rule |
|---|---|
| PU-BR-01 | Vendor requires name, category, phone; duplicate check by phone/TIN. |
| PU-BR-02 | Contractor/subcontractor bills on a project require a WO when `require_wo_for_contractor_bills`. |
| PU-BR-03 | WO approval above `wo_pm_approval_limit` needs management. |
| PU-BR-04 | Σ bills against a WO ≤ WO total (+ approved amendments); 3-way check: billed qty ≤ measured qty ≤ WO qty (+ allowance). |
| PU-BR-05 | Vendor bill no. unique per vendor. |
| PU-BR-06 | Project bills require project `allows_costing`. |
| PU-BR-07 | Approved bills immutable; corrections via debit note or cancel (no payments). |
| PU-BR-08 | Payment allocation ≤ bill balance; same vendor only. |
| PU-BR-09 | Payment from cash account cannot exceed cash account balance at payment date (block; finance_manager override). |
| PU-BR-10 | Advance recovery on bill ≤ vendor advance balance (for that WO if WO-linked). |
| PU-BR-11 | TDS amount defaults from rate × (subtotal); editable with reason; TDS payable carries vendor_id for certificates. |
| PU-BR-12 | Retention payable released only through a retention release payment after defect liability (approval required). |
| PU-BR-13 | Accountant posting limit `payment_post_limit_accountant`. |

---

## 8. Posting (authoritative table in 08 §6)

| Document | Debit | Credit |
|---|---|---|
| Vendor bill | Expense / Direct cost per line (project, cost category); VAT input (if claimable) else included in cost | AP (vendor, project) = payable; TDS Payable; VDS/VAT Payable; Retention Payable; Vendor Advances = advance_recovered |
| Vendor payment | AP (vendor) | Cash / Bank |
| Vendor advance | Vendor Advances (vendor, project) | Cash / Bank |
| Debit note | AP (vendor) | Expense / Direct cost |
| Retention release | Retention Payable | Cash / Bank |

---

## 9. Integration
Out: `WorkOrderApproved` (05 committed cost), `VendorBillApproved`, `VendorPaymentPosted`, `DebitNoteApproved` → 08 journals, 04 financials, 05 budget actuals.
In: `MeasurementVerified` (vendor direction) → available for vendor RB.

## 10. Notifications
`purchases.wo_submitted` → approver · `purchases.bill_submitted` → finance approvers · `purchases.bill_due` (3 days before) → accountant · `purchases.budget_exceeded` → PM, management.

## 11. Reports
Vendor ledger (legacy layout) · Vendor statement · AP aging · Vendor expense report (legacy columns: Transaction id, Voucher no., MR no., Type, Date, Vendor, Bill no., Description, Received, Paid) · WO register & progress · Bills register · Payments register · TDS deducted (for certificates/returns) · Retention payable · Vendor-wise spend by project/category.

## 12. Components (indicative)

```text
Livewire: Purchases\Vendors\Index|Form|Show, Purchases\WorkOrders\Index|Form|Show,
          Purchases\RunningBills\Index|Form, Purchases\Bills\Index|Form|Show,
          Purchases\Payments\Index|Form|Show, Purchases\DebitNotes\Index|Form
Actions:  SaveVendor, SaveWorkOrder, ApproveWorkOrder, AmendWorkOrder, CloseWorkOrder,
          BuildVendorRunningBill, ApproveVendorRunningBill, SaveBill, ApproveBill, CancelBill,
          SaveVendorPayment, PostVendorPayment, CancelVendorPayment, ApproveDebitNote
```

## 13. Acceptance Criteria

| ID | Criterion |
|---|---|
| PU-AC-01 | WO ৳10,00,000 to a contractor; running bill from MB for ৳3,00,000 with 5 % retention and TDS 5 % → bill payable ৳2,70,000; journal Dr Subcontract (project) 3,00,000 / Cr AP 2,70,000, Cr Retention Payable 15,000, Cr TDS Payable 15,000. |
| PU-AC-02 | Second bill that would take WO billed above ৳10,00,000 is blocked. |
| PU-AC-03 | Vendor advance ৳1,00,000 then bill recovering ৳50,000 leaves advance balance ৳50,000 on vendor detail. |
| PU-AC-04 | Vendor ledger shows WO no., bill no., voucher no., cash/cheque and running due like legacy. |
| PU-AC-05 | AP aging total equals AP control in trial balance. |
| PU-AC-06 | Office rent bill without project posts to 6200 Office Rent and does not appear in any project cost. |

## 14. Open Questions
1. TDS rates SOC applies per vendor type; does SOC issue TDS certificates?
2. Are material purchases ever stocked (store) or always delivered to site and expensed?
3. Approval limits for WOs and payments.
