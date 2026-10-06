<?php

use App\Modules\Catalog\Actions\SaveMaterial;
use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\MaterialCategory;
use App\Modules\Catalog\Models\Unit;

function materialInput(array $overrides = []): array
{
    return [
        'code' => ' cem-opc ',
        'name' => 'OPC cement',
        'material_category_id' => MaterialCategory::factory()->create()->id,
        'unit_id' => Unit::factory()->create()->id,
        'standard_rate' => '1,550.25',
        'is_active' => true,
        ...$overrides,
    ];
}

test('a material is created with an upper-case code and its rate', function () {
    $material = app(SaveMaterial::class)->handle(materialInput());

    expect($material->code)->toBe('CEM-OPC')
        ->and($material->standard_rate)->toBe('1550.2500');
});

test('material codes are unique case-insensitively (CT-BR-05)', function () {
    Material::factory()->create(['code' => 'CEM-OPC']);

    expectValidationError(fn () => app(SaveMaterial::class)->handle(materialInput()), 'code');
});

test('category and unit are required and active', function () {
    expectValidationError(fn () => app(SaveMaterial::class)->handle(materialInput(['material_category_id' => ''])), 'material_category_id');
    expectValidationError(fn () => app(SaveMaterial::class)->handle(materialInput(['unit_id' => Unit::factory()->create(['is_active' => false])->id])), 'unit_id');
});

test('the rate cannot be negative and the name is at most 200 characters', function () {
    expectValidationError(fn () => app(SaveMaterial::class)->handle(materialInput(['standard_rate' => '-1'])), 'standard_rate');
    expectValidationError(fn () => app(SaveMaterial::class)->handle(materialInput(['name' => str_repeat('a', 201)])), 'name');
});

test('a deleted material keeps its code reserved', function () {
    Material::factory()->create(['code' => 'CEM-OPC'])->delete();

    expectValidationError(fn () => app(SaveMaterial::class)->handle(materialInput()), 'code');
});
