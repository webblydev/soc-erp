<?php

use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateLine;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    $this->management = staffUser('management');
});

test('the estimate prints as a measurement sheet and an abstract of cost', function () {
    $estimate = Estimate::factory()->create(['title' => 'Ground floor works']);
    EstimateLine::factory()->create(['estimate_id' => $estimate->id, 'description' => 'Brick work in foundation']);

    $this->actingAs($this->management)
        ->get(route('estimation.estimates.print', ['estimate' => $estimate, 'layout' => 'measurement']))
        ->assertOk()->assertSee('Measurement sheet')->assertSee('Brick work in foundation');

    $this->get(route('estimation.estimates.print', ['estimate' => $estimate, 'layout' => 'abstract']))
        ->assertOk()->assertSee('Abstract of cost')->assertSee('5,000.00');
});

test('a material estimate prints its material statement', function () {
    $estimate = Estimate::factory()->ofKind(EstimateKind::MATERIAL)->create();
    $estimate->materialLines()->create(['material_name' => '16mm Rebar', 'unit_id' => unitId('ton'), 'estimated_qty' => 2, 'total_qty' => 2]);

    $this->actingAs($this->management)->get(route('estimation.estimates.print', $estimate))
        ->assertOk()->assertSee('Material statement')->assertSee('16mm Rebar');
});

test('printing and exporting need their permissions', function () {
    $estimate = Estimate::factory()->create();
    $viewer = userWithPermissions('estimation.estimates.view', 'projects.projects.view_all');

    $this->actingAs($viewer)->get(route('estimation.estimates.print', $estimate))->assertForbidden();
    $this->actingAs($viewer)->get(route('estimation.estimates.export', $estimate))->assertForbidden();
    $this->actingAs($this->management)->get(route('estimation.estimates.export', $estimate))->assertOk();
});
