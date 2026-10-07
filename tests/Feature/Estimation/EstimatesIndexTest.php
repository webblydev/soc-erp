<?php

use App\Modules\Estimation\Livewire\Estimates\Index;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use Livewire\Livewire;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    $this->engineer = staffUser('engineer');
    $this->project = Project::factory()->create(['project_number' => 'SOC-BD-0103']);
    ProjectEmployee::factory()->create(['project_id' => $this->project->id, 'employee_id' => $this->engineer->employee_id]);
});

test('the list needs estimation.estimates.view', function () {
    $this->actingAs(staffUser('hr_admin'));
    $this->get(route('estimation.estimates.index'))->assertForbidden();

    $this->actingAs($this->engineer);
    $this->get(route('estimation.estimates.index'))->assertOk()->assertSee('New estimate');
});

test('an engineer sees only the estimates of their projects, latest revision first', function () {
    $original = Estimate::factory()->onProject($this->project)->approved()->create(['title' => 'Ground floor works']);
    $revision = Estimate::factory()->revisionOf($original)->create(['title' => 'Ground floor works R1']);
    Estimate::factory()->create(['title' => 'Someone else\'s estimate']);

    Livewire::actingAs($this->engineer)->test(Index::class)
        ->assertSee($revision->estimate_number)
        ->assertDontSee($original->estimate_number.'<')
        ->assertDontSee('Someone else')
        ->set('filters.revisions', 'all')
        ->assertSee($original->estimate_number);
});

test('the list filters by kind and project and searches by project number', function () {
    $boq = Estimate::factory()->onProject($this->project)->create(['title' => 'Brick work estimate']);
    $material = Estimate::factory()->onProject($this->project)->ofKind(EstimateKind::MATERIAL)->create(['title' => 'Rod estimate']);
    $management = staffUser('management');
    $other = Estimate::factory()->create(['title' => 'Other project estimate']);

    Livewire::actingAs($management)->test(Index::class)
        ->set('filters.kind', (string) EstimateKind::idFor(EstimateKind::MATERIAL))
        ->assertSee('Rod estimate')->assertDontSee('Brick work estimate')
        ->set('filters.kind', '')
        ->set('search', 'soc-bd-0103')
        ->assertSee('Brick work estimate')->assertDontSee('Other project estimate')
        ->set('search', '')
        ->set('filters.project', $other->project->project_number)
        ->assertSee('Other project estimate')->assertDontSee('Rod estimate');

    expect($boq->exists && $material->exists)->toBeTrue();
});

test('accountants export the filtered estimates, engineers cannot', function () {
    Estimate::factory()->onProject($this->project)->create();

    Livewire::actingAs(staffUser('accountant'))->test(Index::class)->call('export')->assertFileDownloaded();
    Livewire::actingAs($this->engineer)->test(Index::class)->call('export')->assertForbidden();
});
