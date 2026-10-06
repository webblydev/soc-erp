# Legacy Seed Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Master/setup seeders hold the real v1 setup data, and a `LegacyDataSeeder` copies the v1 employees, users, teams, clients and follow-up notes into the dev database from a local `legacy` connection.

**Architecture:** Part A edits the existing committed seeders (locations, company profile, designations) and adds a material seeder. Part B is `database/seeders/Legacy/`: a seeder that checks the `legacy` connection, then runs small importer classes in one transaction with model events muted. A shared `LegacyContext` carries the id maps (legacy user → user, legacy employee → employee, legacy client → lead/customer) and the lookup ids. Idempotency comes from legacy keys on the target rows.

**Tech Stack:** Laravel 13 seeders, Eloquent, Pest 4 with an in-memory SQLite `legacy` connection.

**Spec:** `docs/superpowers/specs/2026-10-06-legacy-seed-design.md` (decisions L1–L12, mapping tables §3).

## Global Constraints

- No personal data in committed files: names, phones, emails, addresses, NIDs only come from the `legacy` connection at run time. Fixture rows in tests are invented.
- `LegacyDataSeeder` is not called by `DatabaseSeeder`.
- Imports run inside `DB::transaction()` with `Model::withoutEvents()` (no audit rows, notifications or observers). Numbers come from `NumberSequenceService::next()`.
- Commits: per file group, mid-length messages, **no Co-Authored-By or AI footer**.
- `vendor/bin/pint --dirty --format agent` before each PHP commit. Tests: `php artisan test --compact tests/Feature/Legacy/<File>.php`. No browser tests.
- Ask before `migrate:fresh` on the dev DB.

## Review Focus

1. **Broken legacy dates** (`0000-00-00` lead dates, `00YY` reminders, null `added_date`) must not crash or produce year-0001 rows. Pinned in Task 6 ("broken dates fall back") and Task 7 ("00YY reminders are read as 20YY").
2. **A second run** must not duplicate employees, users, leads, customers or activities. Pinned in Tasks 3, 4, 6 and 7 ("re-running adds nothing").
3. **Legacy usernames that collide** with the seeded admin (`Admin` vs `admin`) must reuse that user, not fail on the unique index. Pinned in Task 4.
4. **Two legacy users pointing at one employee** must not violate `users.employee_id` unique. Pinned in Task 4.
5. **No legacy connection configured** → the normal seed still works and the legacy seeder writes nothing. Pinned in Task 1.

---

### Task 1: Legacy connection, client reference column, seeder shell, test schema

**Files:** modify `config/database.php`, `.env.example`; create `database/migrations/crm/2026_10_09_100000_add_legacy_client_ref_to_leads_and_customers.php`, `database/seeders/Legacy/LegacyDataSeeder.php`, `tests/Feature/Legacy/LegacySchema.php` (helper functions, required from `tests/Pest.php`), `tests/Feature/Legacy/LegacyDataSeederTest.php`.

**Interfaces — produces:**
- `config('database.connections.legacy')`: MySQL settings from the `LEGACY_DB_*` env vars, falling back to the `DB_*` values; `database` = `env('LEGACY_DB_DATABASE')` (null by default).
- `LegacyDataSeeder::run()`: returns early with `$this->command?->warn(...)` when `config('database.connections.legacy.database')` is empty or the connection fails `select 1`. Otherwise it runs the importers in order (added by later tasks) inside `DB::transaction` and `Model::withoutEvents`.
- `leads.legacy_client_ref`, `customers.legacy_client_ref`: nullable unsigned int, indexed.
- Test helpers: `useLegacyDatabase(): void` (sets the connection to SQLite `:memory:`, purges it, creates the tables `tbl_department, tbl_post, tbl_employee, tbl_user, tbl_client, tbl_clientdetails, tbl_area, tbl_company` with the legacy columns the importers read) and `legacyRow(string $table, array $attributes): int` (inserts with defaults and returns the id).

- [ ] **Step 1: failing test**

```php
test('without a legacy database the seeder writes nothing', function () {
    config(['database.connections.legacy.database' => null]);

    $this->seed(LegacyDataSeeder::class);

    expect(Lead::query()->count())->toBe(0)->and(Employee::query()->count())->toBe(0);
});

test('the client reference columns exist', function () {
    expect(Schema::hasColumn('leads', 'legacy_client_ref'))->toBeTrue()
        ->and(Schema::hasColumn('customers', 'legacy_client_ref'))->toBeTrue();
});
```

- [ ] **Step 2: run → FAIL. Step 3: implement. Step 4: run → PASS. Step 5: commit** (`Add the legacy database connection`, `Add legacy client references to leads and customers`, `Add the legacy data seeder shell and test schema`).

---

### Task 2: Master data from v1 (Part A)

**Files:**
- Create `database/seeders/Foundation/data/legacy_areas.php`: legacy area id → path list `[division, district, thana?, area?]`, or `null` for 2 "Others". Uses the mapping in the spec L4 and these placements:
  - 1 → Dhaka/Dhaka
  - 3 → Dhaka/Gazipur/Tongi West
  - 4 → …/Uttar Khan; 5 → …/Dakshinkhan; 6 → …/Khilkhet
  - 7 → Dakshinkhan/Kawla; 8 → Vatara/Bashundhara R/A
  - 9 → Dhaka/Narayanganj/Rupganj/Purbachal New Town
  - 10 → Vatara/Solmaid; 11 → Dhaka/Dhaka/Vatara/Sunvalley & Shodesh
  - 12 → Badda/Satarkul; 13 → Vatara; 14 → Vatara/Khilbarirtek
  - 15 → Badda/North Badda; 16 → Badda/Middle Badda; 17 → Badda/Aftabnagar
  - 18 → Rampura/Banasree; 19 → Mugda; 20 → Mugda/Green Model Town
  - 21 → Uttara West/Uttara Model Town; 22 → Turag/Bounia; 23 → Turag/Ranavola
  - 24 → Savar/Ashulia; 25 → Khilkhet/Nekaton; 26 → Mirpur
  - 27 → Gulshan/Kalachandpur; 28 → Narayanganj; 29 → Dhanmondi
  - 30 → Gulshan/Niketan; 31 → Savar; 32 → Khulna/Kushtia
  - 33 → Cantonment/Manikdi; 34 → Motijheel/Purana Paltan; 35 → Mymensingh/Mymensingh
  - 36 → Khulna/Chuadanga; 37 → Dhaka/Tangail
  - 38 → Gulshan/Gulshan-2; 39 → Gulshan/Gulshan-1; 40 → Tejgaon
  - 41 → Banani; 42 → Mohammadpur; 43 → Adabor/Shyamoli
  - 44 → Barishal/Pirojpur; 45 → Dhaka/Madaripur; 46 → Rajshahi/Pabna
  - 47 → Chattogram/Noakhali; 48 → Dhaka/Faridpur; 49 → Rajshahi/Sirajganj
  - 50 → Turag/Nolbhog
  - Bare thana names are under Dhaka/Dhaka.
- Modify `LocationSeeder` (also place every path in that file; level by depth), `CompanyProfileSeeder` (fill address / phone / email when empty: address "H-35, (Plot-1081), Jannat Cottage, Khilbarirtek, Gulshan, Vatara, Dhaka-1212", phone 01714678285, email farid.socbdltd@gmail.com — business contact data, already public on the v1 site), `HrmLookupSeeder` (designations = the 18 posts, codes per spec §3, each "Head of …" with its department), `CatalogSeeder` (call new `MaterialSeeder`).
- Create `database/seeders/Catalog/MaterialSeeder.php`: codes `MAT-0001`… for Grey Cement (OPC), Grey Cement (PCC) [CEMENT/bag], Sylhet Sand (FM-2.5), Local Sand (FM-2.0), Local Sand (FM-1.5) [SAND/cft], Single Stone Chips, Stone Chips (LC), Stone Chips (Vutu Bhanga) [STONE_AGGREGATE/cft]; `firstOrCreate` by name.
- Update the HRM tests that use removed designation codes (`SITE_ENGINEER` → `JR_PROJECT_ENGINEER`, `DRAFTSMAN` → `ARCHITECT`).
- Test: `tests/Feature/Legacy/MasterDataTest.php`.

- [ ] **Step 1: failing tests**

```php
test('legacy areas become locations at the right depth', function () {
    seedLocations();

    $banasree = Location::query()->where('name', 'Banasree')->with('level', 'parent')->first();

    expect($banasree->level->code)->toBe(LocationLevel::AREA)->and($banasree->parent->name)->toBe('Rampura')
        ->and(Location::query()->where('name', 'Rupganj')->first()->level->code)->toBe(LocationLevel::THANA);
});

test('designations are the 18 v1 posts', function () {
    seedHrm();

    expect(Designation::query()->count())->toBe(18)
        ->and(Designation::query()->where('code', 'HEAD_DESIGN')->first()->department->code)->toBe('DESIGN');
});

test('the 8 v1 materials are seeded once', function () {
    $this->seed(CatalogSeeder::class);
    $this->seed(MaterialSeeder::class);

    expect(Material::query()->count())->toBe(8)
        ->and(Material::query()->where('name', 'Grey Cement (OPC)')->first()->unit->code)->toBe('bag');
});

test('the company profile gets the v1 contact details when empty', function () {
    $this->seed([CurrencySeeder::class, CompanyProfileSeeder::class]);

    expect(CompanyProfile::current()->phone)->toBe('01714678285');
});
```

(`seedLocations()` = seed `LocationSeeder`; add next to `seedHrm()` in `tests/Pest.php`.)

- [ ] **Step 2: FAIL. Step 3: implement. Step 4: PASS + `tests/Feature/Hrm tests/Feature/Catalog tests/Feature/Foundation` green. Step 5: commit per file.**

---

### Task 3: Map, context and employees

**Files:** create `database/seeders/Legacy/LegacyMap.php` (constants per spec §3 plus `businessLineFor(string $clientCode): ?string`, `sourceFor(?string): string`, `levelFor(?string): ?string`, `date(?string): ?string` — valid `Y-m-d` or null, fixing `00YY-` → `20YY-`, rejecting `0000-`), `database/seeders/Legacy/LegacyContext.php` (lazy lookup id caches via `idFor(class, code)`; maps `employees` / `users` / `leads` keyed by legacy id), `database/seeders/Legacy/ImportEmployees.php`; test `tests/Feature/Legacy/ImportEmployeesTest.php`.

**Interfaces:** `ImportEmployees::run(LegacyContext $context): int` (rows created) fills `$context->employees[legacyId] = employeeId` for every legacy employee (created or found by `legacy_employee_id`).

- [ ] **Step 1: failing tests** — an active and a former employee row; assert code kept, first/last name split, designation `PROJECT_ENGINEER` from post 13, department, gender, marital SINGLE from `unmarred`, former → RESIGNED with exit date and a RESIGNED event, JOINED event, an invalid phone → company phone + note, and a second run creates nothing.

```php
test('employees are copied with their job and status', function () {
    useLegacyDatabase();
    seedHrm(); seedCompany();
    legacyRow('tbl_employee', ['id' => 13, 'code' => 'E00013', 'name' => 'Md Rakib Hasan', 'post_id' => 13, 'department_id' => 2, 'gender' => 'male', 'marital_status' => 'unmarred', 'phone' => '01711000013', 'status' => 'a', 'added_date' => '2023-10-16 13:00:00']);
    legacyRow('tbl_employee', ['id' => 14, 'code' => 'E00014', 'name' => 'Sadia Islam', 'post_id' => 13, 'department_id' => 2, 'gender' => 'female', 'marital_status' => 'married', 'phone' => 'n/a', 'status' => 'd', 'added_date' => '2023-11-01 10:00:00', 'update_date' => '2024-06-30 18:00:00']);

    expect(app(ImportEmployees::class)->run(new LegacyContext))->toBe(2);

    $rakib = Employee::query()->where('employee_code', 'E00013')->first();
    $sadia = Employee::query()->where('employee_code', 'E00014')->first();

    expect($rakib)->first_name->toBe('Md Rakib')->last_name->toBe('Hasan')->legacy_employee_id->toBe(13)
        ->and($rakib->designation->code)->toBe('PROJECT_ENGINEER')->and($rakib->maritalStatus->code)->toBe('SINGLE')
        ->and($sadia->status->code)->toBe('RESIGNED')->and($sadia->exit_date->toDateString())->toBe('2024-06-30')
        ->and($sadia->phone)->toBe('01714678285')->and($sadia->notes)->toContain('n/a')
        ->and($sadia->events()->count())->toBe(2)
        ->and(app(ImportEmployees::class)->run(new LegacyContext))->toBe(0);
});
```

(`seedCompany()` seeds currencies + company profile.)

- [ ] **Steps 2–5** as usual; commit `Add the legacy mapping and context`, `Import legacy employees`.

---

### Task 4: Users

**Files:** `database/seeders/Legacy/ImportUsers.php`; test `ImportUsersTest.php`.

**Interfaces:** `ImportUsers::run(LegacyContext $context): int`; needs `$context->employees`; fills `$context->users[legacyUserId] = userId` and `$context->usernames[lower(username)] = userId`. Requires roles seeded (`seedAccessControl()`).

- [ ] **Step 1: failing tests**: an admin (`Admin`, type a) maps to the existing `admin` user without changes; a team manager (t) → sales_manager; a user (u) in department 1 → engineer, in department 3 → sales_executive; two users on one employee → only the first linked; inactive (`d`) → inactive; `Hash::check('password', …)`; re-run creates nothing.
- [ ] **Steps 2–5**; commit `Import legacy users`.

---

### Task 5: Sales teams

**Files:** `database/seeders/Legacy/ImportSalesTeams.php`; test `ImportSalesTeamsTest.php`.

**Interfaces:** `ImportSalesTeams::run(LegacyContext $context): int`; needs `$context->users`; fills `$context->teamOfUser[userId] = salesTeamId`. Membership needs the users' first lead date, which is read from `tbl_client` (`min(add_time)` per `add_by`) — users without clients are not members.

- [ ] **Step 1: failing test**: users in letters a and b with clients → "Team A" (manager = its type-t user) and "Team B", members joined on their first client date, a user with no clients not a member; re-run creates nothing.
- [ ] **Steps 2–5**; commit `Import legacy sales teams`.

---

### Task 6: Clients → leads, services, customers

**Files:** `database/seeders/Legacy/ImportClients.php`; test `ImportClientsTest.php`.

**Interfaces:** `ImportClients::run(LegacyContext $context): int`; needs users, teams, the area map (`legacy_areas.php` resolved to location ids through `full_path`), and fills `$context->leads[legacyClientId] = ['lead' => id, 'customer' => ?id, 'owner' => userId, 'open' => bool]`.

- [ ] **Step 1: failing tests**
  - a pending client with a comment → CONTACTED, source `F` → REFERENCE, level Middle → MID, business line CON from `SOC-CON-00012`, services BD and BDRA from requirement `1,2`, location Banasree from area 18, owner from `add_by`, team from the owner, `lead_date` from `add_time` when `date` is `0000-00-00` ("broken dates fall back");
  - a pending client with nothing → NEW;
  - a sold client → WON lead + customer (`C-000001`, type COMPANY when `org_name`, account manager, `source_lead_id`, `lead_source_id`, `acquired_by_user_id`), lead `converted_customer_id`;
  - a deleted client skipped;
  - numbers `L-000001…` in legacy id order;
  - re-run creates nothing.
- [ ] **Steps 2–5**; commit `Import legacy clients as leads and customers`.

---

### Task 7: Activities

**Files:** `database/seeders/Legacy/ImportClientActivities.php`; test `ImportClientActivitiesTest.php`.

**Interfaces:** `ImportClientActivities::run(LegacyContext $context): int`; needs `$context->leads`, `$context->usernames`; calls `LeadFollowUps::refresh($lead)` per touched lead. Idempotency: skips a lead that already has activities.

- [ ] **Step 1: failing tests**: two detail notes (one by `robin` → that user, one by `soc` → the lead owner) become completed NOTE activities with `completed_at` = `added_date`; the comment becomes "Legacy comments"; reminder `00YY-` one year ahead → open FOLLOW_UP at 10:00 ("00YY reminders are read as 20YY"); a past reminder → none; a WON lead's notes go on the customer; `next_follow_up_at` / `last_activity_at` set; re-run creates nothing.
- [ ] **Steps 2–5**; commit `Import legacy follow-up notes as activities`.

---

### Task 8: Wire up, run on the dev database, wrap up

- [ ] Wire all importers into `LegacyDataSeeder` (order: employees, users, teams, clients, activities), print a count per entity, and add a test that runs the whole seeder on a small fixture (one row of each) and checks the counts.
- [ ] Re-seed the master data on the dev DB (`db:seed` of LocationSeeder, CompanyProfileSeeder, HrmLookupSeeder, MaterialSeeder: all additive), set `LEGACY_DB_DATABASE=soc_legacy` in `.env`, run `LegacyDataSeeder`, and compare counts with the legacy tables (42 employees, 30 users minus reused, 1,386 leads, 643 customers, ~4,692 + comment notes). Run it a second time and confirm nothing is added.
- [ ] Pint; run `tests/Feature/Legacy tests/Feature/Hrm tests/Feature/Crm tests/Feature/Catalog tests/Feature/Foundation`, then the full suite with `php -d memory_limit=1G vendor/bin/pest`.
- [ ] Spec §6 deviations + status Done; memory update; commit.
