<?php

use App\Modules\Estimation\Actions\ReviseEstimate;
use App\Modules\Estimation\Livewire\Estimates\Compare;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Services\EstimateComparison;
use App\Modules\Estimation\Services\EstimateTotals;
use Livewire\Livewire;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    $this->management = staffUser('management');
});

test('compare shows changed, added and removed lines and the total difference (ES-AC-03)', function () {
    $original = Estimate::factory()->approved()->create();
    $kept = EstimateLine::factory()->quantity('100', '50')->create(['estimate_id' => $original->id, 'line_no' => '1.01', 'description' => 'Brick work']);
    EstimateLine::factory()->quantity('10', '50')->create(['estimate_id' => $original->id, 'line_no' => '1.02', 'description' => 'Plaster', 'sort_order' => 1]);
    EstimateLine::factory()->quantity('5', '50')->create(['estimate_id' => $original->id, 'line_no' => '1.03', 'description' => 'Paint', 'sort_order' => 2]);
    app(EstimateTotals::class)->refresh($original);

    $revision = app(ReviseEstimate::class)->handle($this->management, $original, 'Changes');
    $revision->lines()->where('origin_line_id', $kept->id)->first()->update(['quantity' => 120, 'amount' => 6000]);
    $revision->lines()->where('description', 'Paint')->delete();
    EstimateLine::factory()->quantity('4', '250')->create(['estimate_id' => $revision->id, 'line_no' => '1.04', 'description' => 'Tiles', 'sort_order' => 3]);
    app(EstimateTotals::class)->refresh($revision);

    $result = app(EstimateComparison::class)->between($revision, $original);
    $states = collect($result['lines'])->mapWithKeys(fn (array $row) => [$row['description'] => $row['state']])->all();

    expect($result['from']->id)->toBe($original->id)
        ->and($states)->toBe(['Brick work' => 'changed', 'Plaster' => 'same', 'Tiles' => 'added', 'Paint' => 'removed'])
        ->and(collect($result['lines'])->firstWhere('description', 'Brick work')['amount_change'])->toBe('1000.00')
        ->and($result['totals']['total'])->toBe('1750.00');

    Livewire::actingAs($this->management)->test(Compare::class, ['estimate' => $revision])
        ->assertSee('Tiles')->assertSee('+1,750.00')->assertDontSee('Plaster')
        ->set('showSame', true)->assertSee('Plaster');
});
