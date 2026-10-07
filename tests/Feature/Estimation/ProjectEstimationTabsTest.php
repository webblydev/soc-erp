<?php

use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Projects\Livewire\Projects\Show;
use App\Modules\Projects\Models\Project;
use Livewire\Livewire;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
});

test('the project page shows the Estimates & Budget tab to estimate viewers', function () {
    $project = Project::factory()->create(['budget_cost' => 83000]);
    Estimate::factory()->onProject($project)->create(['title' => 'Ground floor works']);

    Livewire::actingAs(staffUser('management'))->test(Show::class, ['project' => $project])
        ->assertSee('Estimates &amp; Budget', false)
        ->assertSee('83,000.00')
        ->set('tab', 'estimates')
        ->assertSee('Ground floor works')
        ->assertSee('Open budget');
});

test('users without estimate access do not get the tab', function () {
    $project = Project::factory()->create();

    Livewire::actingAs(userWithPermissions('projects.projects.view_all'))->test(Show::class, ['project' => $project])
        ->assertDontSee('Estimates &amp; Budget', false)
        ->set('tab', 'estimates')
        ->assertSet('tab', 'overview');
});

test('the Site tab shows BOQ progress, recent measurements and inspections', function () {
    $project = Project::factory()->create();
    $line = EstimateLine::factory()->quantity('100', '50')->create([
        'estimate_id' => Estimate::factory()->onProject($project)->approved()->create()->id, 'description' => 'Brick work',
    ]);
    MeasurementEntry::factory()->forLine($line)->create(['quantity' => 40]);
    $inspection = SiteInspection::factory()->onProject($project)->create();
    SiteInspectionFinding::factory()->forInspection($inspection)->create();

    Livewire::actingAs(staffUser('management'))->test(Show::class, ['project' => $project])
        ->assertSee('Open findings')
        ->set('tab', 'site')
        ->assertSee('40 / 100')
        ->assertSee($inspection->inspection_number)
        ->assertSee('1 open finding');
});
