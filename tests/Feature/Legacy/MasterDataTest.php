<?php

use App\Modules\Catalog\Models\Material;
use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use App\Modules\Hrm\Models\Designation;
use Database\Seeders\Catalog\CatalogSeeder;
use Database\Seeders\Catalog\MaterialSeeder;
use Database\Seeders\Foundation\CompanyProfileSeeder;
use Database\Seeders\Foundation\CurrencySeeder;
use Database\Seeders\Foundation\LocationSeeder;

test('legacy areas become locations at the right depth', function () {
    $this->seed(LocationSeeder::class);
    $this->seed(LocationSeeder::class);

    $banasree = Location::query()->where('name', 'Banasree')->with(['level', 'parent'])->sole();
    $rupganj = Location::query()->where('name', 'Rupganj')->with('level')->sole();

    expect($banasree->level->code)->toBe(LocationLevel::AREA)
        ->and($banasree->parent->name)->toBe('Rampura')
        ->and($banasree->full_path)->toBe('Dhaka › Dhaka › Rampura › Banasree')
        ->and($rupganj->level->code)->toBe(LocationLevel::THANA)
        ->and(Location::query()->where('name', 'Purbachal New Town')->exists())->toBeTrue();
});

test('every legacy area maps to a known path or none', function () {
    $areas = require database_path('seeders/Foundation/data/legacy_areas.php');

    expect($areas)->toHaveCount(50)->and($areas[2])->toBeNull();

    foreach (array_filter($areas) as $path) {
        expect(count($path))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(4);
    }
});

test('designations are the 18 v1 posts', function () {
    seedHrm();

    expect(Designation::query()->count())->toBe(18)
        ->and(Designation::query()->where('code', 'HEAD_DESIGN')->with('department')->sole()->department->code)->toBe('DESIGN')
        ->and(Designation::query()->where('code', 'JR_PROJECT_ENGINEER')->value('name'))->toBe('Jr. Project Engineer');
});

test('the 8 v1 materials are seeded once', function () {
    $this->seed(CatalogSeeder::class);
    $this->seed(MaterialSeeder::class);

    expect(Material::query()->count())->toBe(8)
        ->and(Material::query()->where('name', 'Grey Cement (OPC)')->with('unit')->sole()->unit->code)->toBe('bag')
        ->and(Material::query()->where('name', 'Local Sand (FM-1.5)')->with('category')->sole()->category->code)->toBe('SAND');
});

test('the company profile gets the v1 contact details when empty', function () {
    $this->seed([CurrencySeeder::class, CompanyProfileSeeder::class]);

    expect(CompanyProfile::current())->phone->toBe('01714678285')->email->toBe('farid.socbdltd@gmail.com');
});
