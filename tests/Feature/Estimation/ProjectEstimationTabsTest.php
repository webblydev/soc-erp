<?php

use App\Modules\Estimation\Models\Estimate;
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
