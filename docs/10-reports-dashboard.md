# SOC ERP v2 — 10 · Reports & Dashboard

**Build phase:** 8 (some operational reports ship with their module) · **Depends on:** all modules
**Replaces (legacy):** Dashboard; Client Management › Report; Account Ledger menu (12 reports); Task record reports; Project Operation records

---

## 1. Principles

1. **One source of truth:** financial figures come from posted `journal_entry_lines`; operational figures from module tables. No report reads typed balances.
2. **Every report:** filter panel, on-screen table with totals, drill-down to source document, export to Excel and PDF, print (A4 landscape where wide), saved filters per user.
3. **Permissions:** `reports.{group}.{report}` plus the module data scope (a sales executive's CRM reports only include own leads; PM's project reports only own projects).
4. **Performance:** reports over 50k lines run as queued jobs producing a downloadable file; on-screen pagination for detail reports; summary reports use `account_balances` cache where possible.
5. **Dates:** default filter = current month; fiscal-year presets (This FY, Last FY, This quarter).
6. **Money format:** BD grouping, negatives in brackets, zero shown as "–".

Report engine: each report is a class implementing `ReportDefinition` (filters(), query(), columns(), totals(), drill()) rendered by a shared Livewire `ReportViewer` and shared exporters.

---

## 2. Dashboards

Role-based dashboards; widgets respect data scope. Each widget links to its report.

### 2.1 Management dashboard
| Widget | Definition |
|---|---|
| KPI tiles | Revenue this month / FY (GL revenue) · Collections this month (receipts) · Receivable total · Overdue receivable · Payable total · Cash & bank balance · Gross margin % FY |
| Revenue vs collection | 12-month bars: GL revenue vs receipts |
| Revenue by business line | FY, bar |
| Pipeline | open lead value by stage (funnel) |
| Leads this month by source | bar |
| Projects by status | count |
| Top 10 receivables | customer, amount, oldest days |
| Approvals pending | count by authority, overdue count |
| Overdue tasks | count by department |
| Cash position | each bank/cash account balance |

### 2.2 Sales dashboard (manager / executive)
Today's follow-ups · Overdue follow-ups · My/Team open leads by stage · New leads this month vs last · Won this month (count, value) · Conversion rate (90 days) · Stale leads · Team leaderboard (won value, activities).

### 2.3 Project manager dashboard
My projects (status, phase, margin, receivable) · Overdue tasks · Approvals pending · Milestones due · MB awaiting verification · Open HIGH findings · Budget overrun alerts.

### 2.4 Accounts dashboard
Cash & bank balances · Receipts today · Payments today · Invoices to approve · Bills to approve · Bills due this week · Overdue AR · Advances overdue settlement · Unreconciled bank items · Period lock status.

### 2.5 Employee (default) dashboard
My tasks (today, overdue) · My follow-ups (if sales) · My open advances · Announcements (optional).

Legacy dashboard tiles (Total Client, Total Employee, Total Users, Total Task, Pending Task, Completed Task, Prospect List, Reminder) are all covered: Total customers, employees, users, tasks by status, open leads, follow-ups.

---

## 3. Report Catalogue

Notation — **F:** filters · **C:** columns · **T:** totals · **Formula** where non-obvious. "(legacy X)" = replaces that legacy report.

### 3.1 CRM

| # | Report | Spec |
|---|---|---|
| R-CRM-01 | Lead register (legacy Client Report) | F: date, status, source, business line, service, level, team, owner, location, converted · C: Lead #, Date, Name, Company, Phone, Location, Level, Source, Services, Business line, Owner, Team, Status, Expected value, Last activity, Next follow-up · T: count, Σ expected value |
| R-CRM-02 | Lead source analysis | F: date, team, business line · C: Source, Leads, Qualified+, Won, Lost, Open, Conversion % (won/closed), Won value · Formula: conversion % = won ÷ (won + lost) |
| R-CRM-03 | Pipeline | F: as-of, team, owner, business line · C: Stage, Count, Σ expected value, Weighted value (Σ value × probability) , Avg days in stage |
| R-CRM-04 | Conversion funnel | F: lead date range · C: stage reached counts (from status history), drop-off % |
| R-CRM-05 | Salesperson performance | F: date, team · C: Salesperson, New leads, Activities (by type), Follow-ups on time %, Won count, Won value, Lost count, Conversion %, Avg days to win |
| R-CRM-06 | Follow-up compliance | F: date, owner · C: Owner, Scheduled, Done on time, Done late, Missed (overdue), Compliance % |
| R-CRM-07 | Lost leads | F: date, reason, source, owner · C: Lead, Source, Owner, Lost date, Reason, Note, Expected value |
| R-CRM-08 | Area-wise leads | F: date, location level · C: Location, Leads, Won, Won value (tree roll-up) |
| R-CRM-09 | Lead aging | F: as-of · C: buckets 0–7, 8–30, 31–60, 61–90, 90+ days by stage |
| R-CRM-10 | Customer list with balances | F: type, status, manager, has outstanding · C: Customer #, Name, Phone, Projects, Contracted, Invoiced, Received, Outstanding, Overdue |
| R-CRM-11 | Customer acquisition | F: FY · C: Month, New customers, by source, by business line |
| R-CRM-12 | Service demand | F: date · C: Service, Leads requesting, Won, Won value |

### 3.2 Projects & Site

| # | Report | Spec |
|---|---|---|
| R-PRJ-01 | Project register | F: status, phase, business line, type, PM, customer, start date · C: Project #, Name, Customer, BL, Type, Status, Phase, PM, Start, Expected end, Handover, Contract value, Completion % |
| R-PRJ-02 | Contract vs billed vs collected (legacy Monthly Revenue Report) | F: month (for "this month" column), customer, BL · C: Sl, Customer #, Customer, Project #, Project, Address, Task/File no., Total deed amount, Start date, Handover date, Total billed, Total received, Received this month, Receivable, Unbilled contract, Remarks · T: all money columns |
| R-PRJ-03 | Project profitability | F: date range (cost/revenue), status, BL, PM · C: Project, Contract, Revenue (GL), Direct cost (GL) by category (Material, Labour, Subcontract, Transport, Approval fee, Other), Total cost, Gross profit, Margin %, Allocated overhead (optional, by revenue share), Net profit |
| R-PRJ-04 | Budget vs actual | F: project, category · C: Category / line, Budget, Committed (open WO balance), Actual, Remaining (budget − actual − committed), Variance %, Status flag (> 90 % amber, > 100 % red) |
| R-PRJ-05 | Project ledger (legacy Project Ledger) | F: project, date range · C: Date, Doc type, Doc #, Voucher/Client, Description, Bill (invoiced), Cash in (received), Cash out (costs paid), Balance · Opening & closing rows |
| R-PRJ-06 | Project cost detail (legacy Project Expense Report) | F: project, type, category, date · C: Date, Voucher no., Supplier/Vendor/Contractor/Employee, Project type, Project, Bill/Invoice no., Category, Account, Amount |
| R-PRJ-07 | Task register (legacy Task Record) | F: status, assignee, project, type, priority, due range · C: Task #, File no., Customer Id, Entry date, Project, Project type, Title, Supervisor, Deadline, Assigned by, Assignee, Status, Completed date/by |
| R-PRJ-08 | Overdue tasks | F: department, assignee, project · C: Task, Project, Assignee, Due, Days overdue, Priority |
| R-PRJ-09 | Task productivity | F: date, department · C: Employee, Assigned, Completed, Completed on time %, Avg days to complete, Hours logged |
| R-PRJ-10 | Approval tracker | F: authority, type, status, responsible · C: Project, Authority, Type, Ref no., Submitted, Expected, Approved, Days taken / pending, Status |
| R-PRJ-11 | Approval duration analysis | F: FY · C: Authority, Type, Count approved, Avg days, Min, Max, Rejected count |
| R-PRJ-12 | Team allocation | F: as-of, department · C: Employee, Designation, Active projects (list), Roles, Σ allocation % |
| R-PRJ-13 | Milestones due | F: due range, PM · C: Project, Milestone, Trigger, Due date, Amount, Status, Invoice |
| R-SITE-01 | Estimate register / abstract | per estimate print & list |
| R-SITE-02 | Material estimate statement (legacy Material Estimate Record) | C: Project, Work, Address, Date, Material, Unit, Est. qty, Wastage, Total qty, Purpose |
| R-SITE-03 | Work estimate record (legacy) | C: Project, Work, Address, Date, Description, Level, Location, Measurement (nos×L×W×H), Unit, Qty |
| R-SITE-04 | MB register | F: project, direction, vendor/WO, status, date · C: MB #, Book/page, Date, Item, Description, Location, Qty, Unit, Rate, Amount, Achievement %, Status, Bill # |
| R-SITE-05 | Work progress | F: project · C: BOQ line, BOQ qty, Measured qty, %, Billed qty, Remaining |
| R-SITE-06 | Inspection register (legacy Project Visit Record) | C: Inspection #, Date, Project, Project engineer, Contractor, Permittee, Location, Description, Finding, Regarding, Status |
| R-SITE-07 | Open findings aging | F: project, severity, responsible · C: Finding, Project, Severity, Responsible, Due, Days open/overdue |

### 3.3 Sales & Receivables

| # | Report | Spec |
|---|---|---|
| R-SL-01 | Invoice register | F: date, customer, project, BL, type, status · C: Invoice #, Date, Customer, Project, Type, Subtotal, VAT, Total, Retention, Receivable, Paid, Balance, Due, Status |
| R-SL-02 | Collection report | F: date, method, account, collector, customer · C: Receipt #, MR no., Date, Customer, Project, Method, Account, Cheque/Ref, Received, AIT, VDS, Allocated invoices |
| R-SL-03 | AR aging | F: as-of, customer, project, BL, manager · C: Customer (expand to invoices), Current (not due), 1–30, 31–60, 61–90, 91–180, 180+, Total, Unapplied advance, Net · Basis: due date · Must equal AR control |
| R-SL-04 | Customer statement (legacy Client Ledger / Client Trans Ledger) | F: customer, project (optional), date · C: Date, Customer Id, Doc type, Doc/Tr number, Project, Task/File no., Description, Bill amount (Dr), Received (Cr), Balance |
| R-SL-05 | Unbilled contract | C: Project, Contract, Billed, Unbilled, Next milestone |
| R-SL-06 | Retention held (receivable) | C: Customer, Project, Retention held, Released, Balance, DLP end |
| R-SL-07 | Cheques register | F: status (in hand / deposited / bounced) · C: Receipt, Customer, Cheque no., Date, Bank, Amount, Status |
| R-SL-08 | AIT certificates pending | C: Customer, Receipt, AIT amount, Certificate received (Y/N) |
| R-SL-09 | Revenue by service / BL | F: date · C: Business line, Service, Revenue (GL), Invoices count |

### 3.4 Purchases & Payables

| # | Report | Spec |
|---|---|---|
| R-PU-01 | Vendor ledger (legacy Vendor Ledger) | F: vendor, project, date · C: Date, Work order no., Project, Bill no., Voucher no., Cash/Cheque, Bill amount, Cumulative bill, Paid amount, Cumulative paid, Due |
| R-PU-02 | Vendor statement | standard Dr/Cr/Balance |
| R-PU-03 | AP aging | buckets like AR; must equal AP control |
| R-PU-04 | Vendor expense report (legacy) | F: vendor, type, date · C: Trx id, Voucher no., MR no., Type, Date, Vendor, Bill no., Description, Billed, Paid |
| R-PU-05 | Work order register & progress | C: WO #, Vendor, Project, Total, Measured, Billed, Paid, Retention, Balance to bill, Status |
| R-PU-06 | Bills register | like R-SL-01 for bills |
| R-PU-07 | Payments register | C: PV #, Date, Vendor/Payee, Project, Method, Account, Cheque/Ref, Amount, Allocated bills |
| R-PU-08 | TDS deducted | F: period, vendor · C: Vendor, TIN, Bill, Date, Base amount, Rate, TDS · T: by vendor |
| R-PU-09 | Spend analysis | F: date · pivot Vendor × Project × Category |

### 3.5 Accounting

| # | Report | Spec |
|---|---|---|
| R-AC-01 | General ledger | F: account(s), date, party, project, BL · C: Date, JV #, Source doc, Voucher no., Narration/Line description, Party, Project, Debit, Credit, Running balance · Opening/closing per account |
| R-AC-02 | Trial balance | F: date range, level (summary/detail), include zero · C: Code, Account, Opening Dr, Opening Cr, Period Dr, Period Cr, Closing Dr, Closing Cr · T: equal Dr/Cr check banner |
| R-AC-03 | Profit & Loss | F: period, compare (prev period / prev year), BL, project · Layout: Operating revenue; Direct cost; **Gross profit**; Operating expenses; **Operating profit**; Other income; Finance cost; **Net profit** · columns per period, variance |
| R-AC-04 | Balance sheet | F: as-of, compare · Assets (current, fixed), Liabilities, Equity (incl. current year profit) · check: A = L + E |
| R-AC-05 | Cash flow (indirect) | F: period · Net profit → adjustments (depreciation) → working-capital changes (AR, AP, advances, retention) → operating; investing (fixed assets); financing (loans, capital, drawings) → net change = Δ cash & bank |
| R-AC-06 | Cash book (legacy Cash Ledger / Cash View / Cash Transaction Report) | F: cash account, date, type (all/received/paid) · C: Date, Voucher no., MR no., Type, Party/Account, Description, Received (in), Paid (out), Balance |
| R-AC-07 | Bank book (legacy Bank Transactions Report) | F: bank account, date, type (deposit/withdraw) · C: Sl, Date, Description, Account name, Account no., Bank, Cheque/Ref, Note, Deposit, Withdraw, Balance, Reconciled ✓ |
| R-AC-08 | Day book | all journals for a date range, grouped by JV |
| R-AC-09 | Expense report | F: date, category, project, employee, BL · C: Date, EXP #, Category, Description, Employee/Payee, Project, Amount · pivot by category × month |
| R-AC-10 | Employee advance ledger & aging | C: Employee, Advance #, Date, Amount, Settled, Refunded, Balance, Due date, Days overdue |
| R-AC-11 | Tax payable summary | F: period · C: VAT output, VAT input (if claimable), VDS, TDS, AIT receivable, Deposits, Net payable |
| R-AC-12 | Monthly management summary (legacy Monthly Report) | F: month · Sections: revenue by BL, collections, costs by category, net profit, cash movement, receivable & payable movement, top 5 projects by margin, new leads/won |
| R-AC-13 | Voucher prints | Debit voucher, Credit voucher, Contra voucher, Journal voucher (per document) |

### 3.6 HRM
Employee list · Headcount by department · Joiners/leavers · Document expiry · Salary register (optional).

---

## 4. Reconciliation Checks (automated, shown on Accounts dashboard)

| Check | Expectation |
|---|---|
| TB balanced | Σ Dr = Σ Cr |
| AR control vs AR aging total | equal |
| AP control vs AP aging total | equal |
| Advances control vs advance ledger | equal |
| Each bank account GL vs bank book | equal |
| Project ledger Σ revenue/cost vs P&L by project | equal |
| Opening Balance Equity | 0 after migration sign-off |

Nightly job `reports:reconcile` stores results; failures notify finance_manager.

---

## 5. Components (indicative)

```text
Support:  ReportDefinition (interface), ReportViewer (Livewire), FilterSet, ExcelExporter,
          PdfExporter, QueuedReportJob, SavedReportFilter (table: user_id, report_key, name, filters JSON)
Dashboards: Dashboard\Management, Dashboard\Sales, Dashboard\Project, Dashboard\Accounts, Dashboard\Employee
            Widgets as small Livewire components with 5-minute cache
```

## 6. Acceptance Criteria

| ID | Criterion |
|---|---|
| RP-AC-01 | Every legacy report has a v2 equivalent with at least the legacy columns (cross-check list in plan v2 §2). |
| RP-AC-02 | All reconciliation checks pass on UAT data after migration. |
| RP-AC-03 | A sales executive's Lead Source report includes only their leads. |
| RP-AC-04 | Exporting a 30,000-line GL completes as a queued file and notifies the user. |
| RP-AC-05 | Clicking any figure in P&L drills to GL lines, then to the source document. |
| RP-AC-06 | Dashboard KPI "Receivable" equals AR aging total. |

## 7. Open Questions
1. Which 5–10 reports does management use weekly (prioritise for phase 8)?
2. Overhead allocation method for net project profit (by revenue share, by hours, or none)?
3. Any bank-mandated formats (e.g. for BLE) to include as reports?
