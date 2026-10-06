<?php

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\MaterialCategory;
use App\Modules\Catalog\Models\PricingBasis;
use App\Modules\Catalog\Models\ServiceCategory;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\UnitKind;
use App\Modules\Catalog\Models\WorkItemCategory;
use Database\Seeders\Catalog\CatalogSeeder;

test('the catalog seeder fills the lookups of docs/02', function () {
    $this->seed(CatalogSeeder::class);

    expect(BusinessLine::query()->count())->toBe(15)
        ->and(BusinessLine::query()->where('is_internal', true)->pluck('code')->all())->toEqualCanonicalizing(['MGT', 'MS'])
        ->and(BusinessLine::query()->where('code', 'BDRA')->value('project_prefix'))->toBe('SOC-BD&RA')
        ->and(ServiceCategory::query()->count())->toBe(8)
        ->and(WorkItemCategory::query()->count())->toBe(14)
        ->and(MaterialCategory::query()->count())->toBe(13)
        ->and(UnitKind::query()->where('is_system', true)->count())->toBe(7)
        ->and(PricingBasis::query()->pluck('code')->all())->toEqualCanonicalizing([PricingBasis::FIXED, PricingBasis::PER_UNIT, PricingBasis::PERCENT_OF_COST])
        ->and(Unit::query()->count())->toBe(19)
        ->and(Unit::query()->where('code', 'sft')->first()->kind->code)->toBe('area');
});

test('the catalog seeder is idempotent and keeps admin edits', function () {
    $this->seed(CatalogSeeder::class);
    BusinessLine::query()->where('code', 'AMZ')->update(['name' => 'Amazon Works']);

    $this->seed(CatalogSeeder::class);

    expect(BusinessLine::query()->count())->toBe(15)
        ->and(BusinessLine::query()->where('code', 'AMZ')->value('name'))->toBe('Amazon Works')
        ->and(Unit::query()->count())->toBe(19);
});
