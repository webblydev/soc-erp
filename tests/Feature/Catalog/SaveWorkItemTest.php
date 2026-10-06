<?php

use App\Modules\Catalog\Actions\SaveWorkItem;
use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Catalog\Models\WorkItemCategory;

function workItemInput(array $overrides = []): array
{
    return [
        'code' => ' ew-01 ',
        'name' => 'Earth work in excavation',
        'work_item_category_id' => WorkItemCategory::factory()->create()->id,
        'unit_id' => Unit::factory()->create()->id,
        'measurement_formula' => 'nos_l_w_h',
        'standard_rate' => '145.50',
        'specification' => '',
        'is_active' => true,
        ...$overrides,
    ];
}

test('a work item is created with an upper-case code and its formula', function () {
    $item = app(SaveWorkItem::class)->handle(workItemInput());

    expect($item->code)->toBe('EW-01')
        ->and($item->measurement_formula)->toBe(MeasurementFormula::NosLWH)
        ->and($item->standard_rate)->toBe('145.5000')
        ->and($item->specification)->toBeNull();
});

test('work item codes are unique case-insensitively (CT-BR-05)', function () {
    WorkItem::factory()->create(['code' => 'EW-01']);

    expectValidationError(fn () => app(SaveWorkItem::class)->handle(workItemInput()), 'code');
});

test('the formula must be one of the enum values', function () {
    expectValidationError(fn () => app(SaveWorkItem::class)->handle(workItemInput(['measurement_formula' => 'cubic'])), 'measurement_formula');
    expectValidationError(fn () => app(SaveWorkItem::class)->handle(workItemInput(['measurement_formula' => ''])), 'measurement_formula');
});

test('category and unit are required and active, and the rate is not negative', function () {
    expectValidationError(fn () => app(SaveWorkItem::class)->handle(workItemInput(['work_item_category_id' => ''])), 'work_item_category_id');
    expectValidationError(fn () => app(SaveWorkItem::class)->handle(workItemInput(['unit_id' => Unit::factory()->create(['is_active' => false])->id])), 'unit_id');
    expectValidationError(fn () => app(SaveWorkItem::class)->handle(workItemInput(['standard_rate' => '-0.01'])), 'standard_rate');
});
