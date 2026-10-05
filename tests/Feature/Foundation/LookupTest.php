<?php

use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\Currency;
use App\Support\Facades\Lookup;

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
