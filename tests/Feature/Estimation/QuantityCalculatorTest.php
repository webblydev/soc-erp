<?php

use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Estimation\Services\QuantityCalculator;

test('brick work of nos 2, L 20, W 0.833, H 10 is 333.20 cft (ES-AC-01)', function () {
    expect(QuantityCalculator::quantity(MeasurementFormula::NosLWH, '2', '20', '0.833', '10'))->toBe('333.2000');
});

test('each formula multiplies only the dimensions it names', function (MeasurementFormula $formula, string $expected) {
    expect(QuantityCalculator::quantity($formula, '3', '12.5', '4', '2', '7.25'))->toBe($expected);
})->with([
    'nos × L × W × H' => [MeasurementFormula::NosLWH, '300.0000'],
    'nos × L × W' => [MeasurementFormula::NosLW, '150.0000'],
    'nos × L' => [MeasurementFormula::NosL, '37.5000'],
    'nos' => [MeasurementFormula::Nos, '3.0000'],
    'manual' => [MeasurementFormula::Manual, '7.2500'],
]);

test('an empty nos counts as one and an empty dimension as zero', function () {
    expect(QuantityCalculator::quantity(MeasurementFormula::NosLW, null, '12', '6', null))->toBe('72.0000')
        ->and(QuantityCalculator::quantity(MeasurementFormula::NosLWH, '1', '12', '6', null))->toBe('0.0000');
});

test('quantities round half-up to four places and amounts to two', function () {
    expect(QuantityCalculator::quantity(MeasurementFormula::NosLW, '1', '0.33335', '1', null))->toBe('0.3334')
        ->and(QuantityCalculator::amount('333.2000', '12.345'))->toBe('4113.35')
        ->and(QuantityCalculator::amount('10', null))->toBe('0.00');
});

test('a deduction line has a negative amount', function () {
    expect(QuantityCalculator::amount('21.0000', '650', deduction: true))->toBe('-13650.00');
});

test('material totals add the wastage percent', function () {
    expect(QuantityCalculator::withWastage('350', '5'))->toBe('367.5000')
        ->and(QuantityCalculator::withWastage('2.5', null))->toBe('2.5000');
});
