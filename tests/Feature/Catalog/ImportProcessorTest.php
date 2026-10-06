<?php

use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Imports\MaterialImport;
use App\Modules\Catalog\Imports\WorkItemImport;
use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\MaterialCategory;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Catalog\Models\WorkItemCategory;
use App\Support\Imports\ImportProcessor;
use App\Support\Imports\ImportRowStatus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->concrete = WorkItemCategory::factory()->create(['code' => 'CONCRETE', 'name' => 'Concrete']);
    $this->cum = Unit::factory()->create(['code' => 'cum', 'symbol' => 'm3', 'name' => 'Cubic metre']);
});

/**
 * @param  list<string>  $lines
 */
function importCsv(array $lines, string $heading = 'code,name,category,unit,formula,rate'): string
{
    Storage::disk('private')->put('imports/test.csv', implode("\n", [$heading, ...$lines]));

    return 'imports/test.csv';
}

function previewRows(string $path): array
{
    return app(ImportProcessor::class)->preview(app(WorkItemImport::class), 'private', $path);
}

test('the preview marks rows as new, update or error', function () {
    WorkItem::factory()->create(['code' => 'RCC-01']);

    $rows = previewRows(importCsv([
        'rcc-02,RCC in beam,concrete,M3,nos_l_w_h,9500',
        'RCC-01,RCC in slab,Concrete,cum,"Nos × L × W",9000',
        'RCC-03,RCC in column,Steel,cum,nos,1',
    ]));

    expect(array_map(fn ($row) => $row->status, $rows))->toBe([ImportRowStatus::New, ImportRowStatus::Update, ImportRowStatus::Error])
        ->and($rows[0]->number)->toBe(2)
        ->and($rows[2]->errors)->toBe(['Unknown or inactive category "Steel".']);
});

test('blank cells keep an existing row\'s values and a dash clears the rate', function () {
    $item = WorkItem::factory()->create(['code' => 'RCC-01', 'name' => 'Kept', 'measurement_formula' => MeasurementFormula::NosL, 'standard_rate' => '10']);

    $outcome = app(ImportProcessor::class)->run(app(WorkItemImport::class), 'private', importCsv(['RCC-01,,,,,-']));

    expect($outcome->updated)->toBe(1)
        ->and($item->fresh()->name)->toBe('Kept')
        ->and($item->fresh()->measurement_formula)->toBe(MeasurementFormula::NosL)
        ->and($item->fresh()->standard_rate)->toBeNull();
});

test('a code repeated in the file is an error on the later row', function () {
    $rows = previewRows(importCsv([
        'RCC-01,RCC in slab,CONCRETE,cum,nos_l_w_h,1',
        'rcc-01,RCC again,CONCRETE,cum,nos_l_w_h,2',
    ]));

    expect($rows[0]->status)->toBe(ImportRowStatus::New)
        ->and($rows[1]->status)->toBe(ImportRowStatus::Error)
        ->and($rows[1]->errors)->toContain('Code RCC-01 is also on row 2.');
});

test('200 rows with 3 bad ones create 197 and list the 3 (CT-AC-02)', function () {
    $lines = [];

    foreach (range(1, 200) as $n) {
        $lines[] = match ($n) {
            10 => "WI-{$n},Item {$n},CONCRETE,furlong,nos,5",
            50 => "WI-{$n},Item {$n},CONCRETE,cum,nos,-5",
            150 => "WI-{$n},,CONCRETE,cum,nos,5",
            default => "WI-{$n},Item {$n},CONCRETE,cum,nos,5",
        };
    }

    $outcome = app(ImportProcessor::class)->run(app(WorkItemImport::class), 'private', importCsv($lines));

    expect($outcome->created)->toBe(197)
        ->and($outcome->updated)->toBe(0)
        ->and(array_map(fn ($row) => $row->number, $outcome->failed))->toBe([11, 51, 151])
        ->and(WorkItem::query()->count())->toBe(197);
});

test('confirm judges rows by the state at confirm time, not the preview', function () {
    $path = importCsv(['RCC-01,RCC in slab,CONCRETE,cum,nos,1']);
    expect(previewRows($path)[0]->status)->toBe(ImportRowStatus::New);

    $this->concrete->update(['is_active' => false]);
    $outcome = app(ImportProcessor::class)->run(app(WorkItemImport::class), 'private', $path);

    expect($outcome->created)->toBe(0)->and($outcome->failed)->toHaveCount(1);
});

test('materials import by category name and unit code', function () {
    MaterialCategory::factory()->create(['code' => 'CEMENT', 'name' => 'Cement']);
    Storage::disk('private')->put('imports/m.csv', "code,name,category,unit,rate\nCEM-OPC,OPC cement,cement,cum,550");

    $outcome = app(ImportProcessor::class)->run(app(MaterialImport::class), 'private', 'imports/m.csv');

    expect($outcome->created)->toBe(1)
        ->and(Material::query()->where('code', 'CEM-OPC')->value('standard_rate'))->toBe('550.0000');
});

test('importing the code of a deleted work item restores it', function () {
    $item = WorkItem::factory()->create(['code' => 'RCC-01', 'name' => 'Old name']);
    $item->delete();

    $outcome = app(ImportProcessor::class)->run(app(WorkItemImport::class), 'private', importCsv(['RCC-01,RCC in slab,CONCRETE,cum,nos,1']));

    expect($outcome->failed)->toBeEmpty()
        ->and($item->fresh())->trashed()->toBeFalse()->name->toBe('RCC in slab')
        ->and(WorkItem::withTrashed()->where('code', 'RCC-01')->count())->toBe(1);
});
