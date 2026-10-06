<?php

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\ServiceCategory;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;

test('an active row passes and an inactive or unknown one fails', function () {
    $active = ServiceCategory::factory()->create();
    $inactive = ServiceCategory::factory()->create(['is_active' => false]);

    $passes = fn (mixed $value): bool => Validator::make(['id' => $value], ['id' => [new ActiveLookup('service_categories')]])->passes();

    expect($passes($active->id))->toBeTrue()
        ->and($passes($inactive->id))->toBeFalse()
        ->and($passes(999))->toBeFalse();
});

test('the current value passes even when it is inactive now (CM-BR-03)', function () {
    $inactive = ServiceCategory::factory()->create(['is_active' => false]);

    expect(Validator::make(['id' => (string) $inactive->id], ['id' => [new ActiveLookup('service_categories', $inactive->id)]])->passes())->toBeTrue();
});

test('an extra constraint narrows the allowed rows', function () {
    $internal = BusinessLine::factory()->internal()->create();
    $rule = new ActiveLookup('business_lines', null, fn (Builder $query) => $query->where('is_internal', false));

    expect(Validator::make(['id' => $internal->id], ['id' => [$rule]])->passes())->toBeFalse();
});
