<?php

use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Services\EstimateTotals;

test('overhead, profit and VAT chain on top of each other (spec E7)', function () {
    expect(app(EstimateTotals::class)->calculate('100000', '10', '15', '7.5'))->toBe([
        'subtotal' => '100000.00',
        'overhead_amount' => '10000.00',
        'profit_amount' => '16500.00',
        'vat_amount' => '9487.50',
        'total_amount' => '135987.50',
    ]);
});

test('empty percents add nothing', function () {
    expect(app(EstimateTotals::class)->calculate('2500.5', null, '', null))
        ->toMatchArray(['subtotal' => '2500.50', 'overhead_amount' => '0.00', 'total_amount' => '2500.50']);
});

test('a BOQ totals its work lines and leaves its material statement out', function () {
    $estimate = Estimate::factory()->ofKind(EstimateKind::BOQ)->create(['overhead_pct' => 10]);
    EstimateLine::factory()->create(['estimate_id' => $estimate->id]);
    EstimateLine::factory()->quantity('21', '650')->create(['estimate_id' => $estimate->id, 'deduction' => true, 'amount' => '-13650.00']);
    $estimate->materialLines()->create(['material_name' => 'Cement', 'unit_id' => EstimateLine::query()->value('unit_id'), 'estimated_qty' => 10, 'total_qty' => 10, 'amount' => 5000]);

    app(EstimateTotals::class)->refresh($estimate);

    expect($estimate->fresh())->subtotal->toBe('-8650.00')->overhead_amount->toBe('-865.00')->total_amount->toBe('-9515.00');
});

test('a material estimate totals its material lines', function () {
    $estimate = Estimate::factory()->ofKind(EstimateKind::MATERIAL)->create();
    $unit = EstimateLine::factory()->make()->unit_id;
    $estimate->materialLines()->create(['material_name' => 'Cement', 'unit_id' => $unit, 'estimated_qty' => 350, 'total_qty' => 350, 'rate' => 520, 'amount' => '182000.00']);
    $estimate->materialLines()->create(['material_name' => '16mm Rebar', 'unit_id' => $unit, 'estimated_qty' => 2, 'total_qty' => 2, 'rate' => 95000, 'amount' => '190000.00']);

    app(EstimateTotals::class)->refresh($estimate);

    expect($estimate->fresh()->total_amount)->toBe('372000.00');
});
