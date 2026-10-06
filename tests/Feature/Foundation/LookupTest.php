<?php

use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\Currency;
use App\Support\Facades\Lookup;
use App\Support\Lookups\LookupRegistry;
use Illuminate\Support\Facades\Blade;

test('options return active rows ordered by sort order then name', function () {
    Branch::factory()->create(['code' => 'B', 'name' => 'Beta', 'sort_order' => 2]);
    Branch::factory()->create(['code' => 'A', 'name' => 'Alpha', 'sort_order' => 2]);
    Branch::factory()->create(['code' => 'Z', 'name' => 'Zulu', 'sort_order' => 1]);
    Branch::factory()->create(['code' => 'X', 'name' => 'Hidden', 'is_active' => false]);

    expect(Lookup::options('branches')->pluck('code')->all())->toBe(['Z', 'A', 'B']);
});

test('options include an inactive row that an existing record still references', function () {
    $inactive = Branch::factory()->create(['code' => 'OLD', 'is_active' => false]);
    Branch::factory()->create(['code' => 'NEW']);

    expect(Lookup::options('branches', $inactive->id)->pluck('code')->all())
        ->toContain('OLD', 'NEW');
});

test('the lookup select renders active options, the held inactive one and the placeholder', function () {
    $held = Branch::factory()->create(['code' => 'OLD', 'name' => 'Old Office', 'is_active' => false]);
    Branch::factory()->create(['code' => 'DHK', 'name' => 'Dhaka Office']);
    Branch::factory()->create(['code' => 'CTG', 'name' => 'Chittagong Office', 'is_active' => false]);

    $html = Blade::render('<x-lookup-select table="branches" :include="$held" placeholder="No branch" show-code id="branch" />', ['held' => (string) $held->id]);

    expect($html)
        ->toContain('<option value="">No branch</option>')
        ->toContain('DHK — Dhaka Office')
        ->toContain('OLD — Old Office')
        ->toContain('id="branch"')
        ->not->toContain('Chittagong Office');
});

test('unregistered lookup tables are rejected', function () {
    Lookup::options('users');
})->throws(InvalidArgumentException::class);

test('fiscal year start month defaults to july and follows the company profile', function () {
    expect(CompanyProfile::fiscalYearStartMonth())->toBe(7);

    CompanyProfile::query()->create([
        'name' => 'SOC Consultant & Development Ltd',
        'base_currency_id' => Currency::factory()->create(['code' => 'BDT'])->id,
        'fiscal_year_start_month' => 1,
    ]);

    expect(CompanyProfile::fiscalYearStartMonth())->toBe(1);
});

test('the registry lists only tables the user may view', function () {
    $user = userWithPermissions('admin.branches.view');

    expect(array_keys(app(LookupRegistry::class)->visibleTo($user)))->toBe(['branches']);
});

test('table actions are checked against the table permission prefix', function () {
    $user = userWithPermissions('admin.master_data.view', 'admin.master_data.update');
    $registry = app(LookupRegistry::class);

    expect($registry->allows($user, 'currencies', 'update'))->toBeTrue()
        ->and($registry->allows($user, 'currencies', 'deactivate'))->toBeFalse()
        ->and($registry->allows($user, 'branches', 'view'))->toBeFalse();
});

test('every registered table names its model and typed extra fields', function () {
    $registry = app(LookupRegistry::class);

    foreach ($registry->all() as $table => $entry) {
        expect($registry->modelFor($table)->getTable())->toBe($table);

        foreach ($entry['extra_fields'] as $field) {
            expect($field['type'])->toBeIn(['text', 'textarea', 'number', 'bool', 'list', 'lookup', 'employee']);

            if ($field['type'] === 'lookup') {
                expect(array_key_exists($field['table'] ?? '', $registry->all()))->toBeTrue();
            }
        }
    }

    expect($registry->modelFor('branches'))->toBeInstanceOf(Branch::class);
});
