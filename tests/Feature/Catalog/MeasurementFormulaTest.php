<?php

use App\Modules\Catalog\Enums\MeasurementFormula;

test('a formula is read from its value or its label', function () {
    expect(MeasurementFormula::fromInput('nos_l_w_h'))->toBe(MeasurementFormula::NosLWH)
        ->and(MeasurementFormula::fromInput(' NOS × L × W '))->toBe(MeasurementFormula::NosLW)
        ->and(MeasurementFormula::fromInput('manual'))->toBe(MeasurementFormula::Manual)
        ->and(MeasurementFormula::fromInput('cubic'))->toBeNull();
});

test('each formula lists the dimensions it multiplies', function () {
    expect(MeasurementFormula::NosLWH->dimensions())->toBe(['nos', 'length', 'width', 'height'])
        ->and(MeasurementFormula::Manual->dimensions())->toBe([]);
});
