# SOC ERP v2 — 09 · Mini HRM (Employees, Departments, Designations, Documents, Assignments)

**Build phase:** core employee master in 1–3 (needed by Projects); remaining features phase 9 · **Depends on:** 01 · **Used by:** 01 (users), 03, 04, 05, 08
**Replaces (legacy):** Settings › Employee Entry, Department Entry, Post Entry (designations)

---

## 1. Purpose & Scope

Focused employee management, not full payroll:

- Employee master with personal, job and emergency information
- Departments, designations (legacy "Post"), branches, employee types and statuses
- Reporting line (manager)
- Employee documents with expiry tracking
- Project assignments view (data owned by 04 `project_employees`)
- Employment history (promotions, transfers, salary changes)
- Exit / deactivation
- **Optional (phase 9b):** monthly salary sheet → accrual and payment journals (08 P23/P24)

---

## 2. Permissions

```text
hrm.employees.view_basic | view_full | create | update | deactivate | export
hrm.employees.view_salary | update_salary
hrm.documents.view | manage
hrm.history.manage
hrm.masters.manage           (departments, designations, types, statuses)
hrm.salary.view | prepare | approve | pay      (optional 9b)
```

| Permission | super_admin | management | hr_admin | finance_manager | accountant | project_manager | others |
|---|---|---|---|---|---|---|---|
| view_basic (name, dept, designation, phone, email) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ (directory) |
| view_full | ✓ | ✓ | ✓ | — | — | own team (no personal) | self |
| create / update / deactivate | ✓ | ✓ | ✓ | — | — | — | — |
| salary view / update | ✓ | ✓ | ✓ | view | view | — | self (view) |
| salary approve / pay | ✓ | ✓ | prepare | approve/pay | pay | — | — |

---

## 3. Data Model

### 3.1 Lookups

| Table | Extra | Seed |
|---|---|---|
| `departments` [LOOKUP] + `head_employee_id`, `parent_id` | | DESIGN Design · PROJECT_OPS Project Operation · MKT_SALES Marketing & Sales · ACCOUNTS Accounts & Finance · HR_ADMIN HR & Admin · CUSTOMER_REL Customer Relation · SUPPLY_CHAIN Supply Chain Management · LOGISTIC Logistic & Estate Management (legacy 8) |
| `designations` [LOOKUP] + `grade`, `department_id` | | Managing Director · Director · General Manager · Manager · Architect · Structural Engineer · Project Engineer · Site Engineer · Draftsman · Surveyor · Accounts Officer · HR & Admin Officer · Marketing Officer · Customer Relation Officer · Office Assistant (from legacy Posts — migrate actual list) |
| `employee_types` | | PERMANENT · PROBATION · CONTRACT · PART_TIME · INTERN · CONSULTANT |
| `employee_statuses` | `is_active_employment` | ACTIVE · ON_LEAVE · SUSPENDED · RESIGNED · TERMINATED · RETIRED |
| `genders` | | MALE · FEMALE · OTHER |
| `marital_statuses` | | SINGLE · MARRIED · DIVORCED · WIDOWED |
| `blood_groups` | | A+ A− B+ B− AB+ AB− O+ O− |
| `employee_document_types` | `has_expiry` | NID · Passport (expiry) · Photo · CV · Certificates · Appointment letter · IEB/IAB membership (expiry) · Driving licence (expiry) · Other |
| `employment_event_types` | | JOINED · CONFIRMED · PROMOTED · TRANSFERRED · SALARY_REVISED · DESIGNATION_CHANGED · RESIGNED · TERMINATED · REJOINED |
| `exit_reasons` | | Resignation · Termination · Contract end · Retirement · Other |

### 3.2 `employees`

| Column | Type | Null | Notes |
|---|---|---|---|
| [STD] | | | |
| employee_code | VARCHAR(40) | no | UNIQUE (legacy `code`; keep existing codes) |
| first_name | VARCHAR(80) | no | |
| last_name | VARCHAR(80) | yes | |
| full_name | VARCHAR(160) | no | stored, for search & print (legacy `name`) |
| father_name | VARCHAR(150) | yes | legacy |
| mother_name | VARCHAR(150) | yes | legacy |
| gender_id | BIGINT | yes | |
| date_of_birth | DATE | yes | |
| marital_status_id | BIGINT | yes | |
| blood_group_id | BIGINT | yes | |
| nid_number | VARCHAR(30) | yes | restricted visibility (view_full) |
| phone | VARCHAR(30) | no | |
| personal_email | VARCHAR(150) | yes | |
| official_email | VARCHAR(150) | yes | |
| present_address | TEXT | yes | |
| permanent_address | TEXT | yes | |
| emergency_contact_name | VARCHAR(150) | yes | |
| emergency_contact_relation | VARCHAR(60) | yes | |
| emergency_contact_phone | VARCHAR(30) | yes | |
| reference | VARCHAR(255) | yes | legacy |
| photo_path | VARCHAR(255) | yes | |
| department_id | BIGINT | no | |
| designation_id | BIGINT | no | |
| employee_type_id | BIGINT | no | |
| employee_status_id | BIGINT | no | |
| branch_id | BIGINT | yes | |
| manager_id | BIGINT | yes | FK employees |
| joining_date | DATE | no | |
| confirmation_date | DATE | yes | |
| exit_date | DATE | yes | |
| exit_reason_id | BIGINT | yes | |
| bank_name / bank_account_no | VARCHAR | yes | salary disbursement |
| mobile_wallet_no | VARCHAR(30) | yes | |
| gross_salary | DECIMAL(18,2) | yes | restricted (view_salary) |
| tin | VARCHAR(30) | yes | |
| notes | TEXT | yes | |
| legacy_employee_id | BIGINT | yes | |
| [AUDIT] [SOFT] | | | |

Indexes: employee_code UNIQUE, department_id, designation_id, employee_status_id, manager_id, phone, FULLTEXT(full_name).

### 3.3 `employee_documents`
`[STD], employee_id, employee_document_type_id, document_no, issue_date, expiry_date, attachment_id, notes`.

### 3.4 `employment_events`
`[STD], employee_id, employment_event_type_id, effective_date, from_department_id, to_department_id, from_designation_id, to_designation_id, from_salary, to_salary, note, approved_by` — history timeline; updating department/designation/salary on the employee form requires creating an event (effective date).

### 3.5 `employee_education` / `employee_experience` (optional)
`employee_id, institution/company, degree/position, from, to, result/notes`.

### 3.6 Salary (optional 9b)

| Table | Columns |
|---|---|
| `salary_components` [LOOKUP] | + `kind` (earning/deduction), `account_id`, `is_taxable` — Basic, House rent, Medical, Conveyance, Mobile allowance, Overtime, Advance deduction, Tax deduction, Other |
| `employee_salary_structures` | id, employee_id, effective_from, salary_component_id, amount |
| `salary_sheets` | [STD], fiscal_period_id, month, status (DRAFT · APPROVED · ACCRUED · PAID), total_gross, total_deductions, total_net, accrual_journal_entry_id |
| `salary_sheet_lines` | id, salary_sheet_id, employee_id, department_id, project_id (optional allocation), working_days, present_days, components JSON, gross, deductions, advance_recovery, net, payment_method_id, paid_payment_id |

---

## 4. Screens

### 4.1 Employee directory (`/hrm/employees`)
Cards / list: photo, name, code, designation, department, phone, email, status. Filters: department, designation, type, status, branch, manager. Export.

### 4.2 Employee form
Tabs: **Personal** (names, parents, gender, DOB, marital, blood group, NID, addresses, photo) · **Contact & Emergency** · **Job** (code — auto `EMP-{seq:4}` or manual, department*, designation*, type*, status*, branch, manager, joining date*, confirmation date) · **Bank & Salary** (restricted) · **Education / Experience** · **Documents**.
Changing job/salary fields on an existing employee opens "Employment event" dialog (type, effective date, note).

### 4.3 Employee profile
Header: photo, name, code, designation, department, status, linked user (link/create user button → 01).
Tabs: Overview · Projects (current & past from `project_employees`, roles, allocation) · Tasks (open/overdue from 04) · Advances (08: open advances & balance) · Documents (expiry badges) · Employment history · Salary (restricted) · History (audit).

### 4.4 Exit / deactivate wizard
Exit date*, reason*, note → checks: open tasks (reassign list), PM of projects (reassign), open advances (must settle or note), linked user (auto-deactivate) → confirm → status RESIGNED/TERMINATED, `EmployeeDeactivated` event.

### 4.5 Masters
Departments (tree with head), Designations (with department/grade), Employee types/statuses — via Master Data (01) with extra fields.

### 4.6 Document expiry (`/hrm/documents/expiring`)
Documents expiring in next 30/60/90 days and expired.

### 4.7 Org chart
Tree from `manager_id`, filter by department.

### 4.8 Salary sheet (optional)
Generate month → grid per employee (components editable before approval, advance recovery pulls open employee advances) → Approve → Accrue (journal P23) → Pay (bulk payments by method; P24) → payslip PDFs.

---

## 5. Business Rules

| ID | Rule |
|---|---|
| HR-BR-01 | Employee code unique; legacy codes preserved. |
| HR-BR-02 | Phone required and normalised; NID unique when present. |
| HR-BR-03 | Manager cannot be self or create a cycle. |
| HR-BR-04 | Joining date ≤ today + 60 days; exit date ≥ joining date. |
| HR-BR-05 | Department/designation/salary changes on existing employees must have an employment event with effective date. |
| HR-BR-06 | Employees with status not `is_active_employment` cannot be assigned to projects, tasks or new advances. |
| HR-BR-07 | Exit blocked while employee has open advances with balance > 0, unless finance_manager override. |
| HR-BR-08 | Salary and NID fields hidden unless user has `view_salary` / `view_full`. |
| HR-BR-09 | Employees are never hard-deleted once referenced (projects, tasks, journals). |
| HR-BR-10 | Salary sheet: one per month; approved sheet locked; net = gross − deductions − advance recovery ≥ 0. |

---

## 6. Integration
Out: `EmployeeCreated` (user creation prompt), `EmployeeDeactivated` (01 user off, 04 reassignment flags), `SalarySheetAccrued/Paid` (08 journals).
In: 04 project assignments & tasks (read), 08 advances (read).

## 7. Notifications
`hrm.document_expiring` (30 days) → HR, employee · `hrm.probation_ending` (15 days) → HR, manager · `hrm.birthday` (optional) · `hrm.exit_checklist` → HR, PM.

## 8. Reports
Employee list (by department/designation/status) · Headcount & joiners/leavers by month · Employee project allocation · Document expiry · Employee advance summary (from 08) · Salary register (optional).

## 9. Components (indicative)

```text
Livewire: Hrm\Employees\Index|Form|Show, Hrm\Employees\Exit, Hrm\Documents\Expiring,
          Hrm\OrgChart, Hrm\Salary\Sheet (optional)
Actions:  SaveEmployee, RecordEmploymentEvent, ExitEmployee, LinkUser,
          GenerateSalarySheet, ApproveSalarySheet, AccrueSalary, PaySalary
```

## 10. Acceptance Criteria

| ID | Criterion |
|---|---|
| HR-AC-01 | All 42 legacy employees migrate with codes, department and designation. |
| HR-AC-02 | Promoting an employee records an event and shows it in history with effective date. |
| HR-AC-03 | Exiting an employee who is PM of 3 projects lists them for reassignment and deactivates the linked user. |
| HR-AC-04 | A project manager viewing an employee sees job info but not NID or salary. |
| HR-AC-05 | Passport expiring in 20 days appears in the expiry list and notifies HR. |

## 11. Open Questions
1. Is payroll (salary sheet) in scope for v2 or later?
2. Attendance / leave needed? (Not in v2 scope.)
3. Final list of designations (legacy "Post" table) and grades.
