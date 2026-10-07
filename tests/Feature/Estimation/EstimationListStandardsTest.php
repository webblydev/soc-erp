<?php

use App\Modules\Estimation\Livewire\Estimates\Editor;
use App\Modules\Estimation\Livewire\Estimates\Index as EstimatesIndex;
use App\Modules\Estimation\Livewire\Estimates\Show as EstimateShow;
use App\Modules\Estimation\Livewire\Inspections\Index as InspectionsIndex;
use App\Modules\Estimation\Livewire\Mb\Index as MbIndex;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Foundation\Livewire\Shared\DetailModal;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use Livewire\Livewire;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    $this->pm = staffUser('project_manager');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create(['project_number' => 'SOC-BD-0103']);
});

test('every list table has the mark column, the action column and detail-modal links', function (string $component, Closure $record, string $route) {
    $row = $record();

    Livewire::actingAs($this->pm)->test($component)
        ->assertSeeHtml('data-test="select-all"')
        ->assertSeeHtml('data-test="row-actions"')
        ->assertSeeHtml('wire:model="selected"')
        ->assertSeeHtml('data-detail-modal href="'.route($route, $row).'"');
})->with([
    'estimates' => [EstimatesIndex::class, fn () => Estimate::factory()->onProject(test()->project)->create(), 'estimation.estimates.show'],
    'measurement book' => [MbIndex::class, fn () => MeasurementEntry::factory()->create(['project_id' => test()->project->id]), 'site.mb.show'],
    'inspections' => [InspectionsIndex::class, fn () => SiteInspection::factory()->onProject(test()->project)->create(), 'site.inspections.show'],
]);

test('rows are deleted from the action column through the module actions', function () {
    $draft = Estimate::factory()->onProject($this->project)->create();
    $approved = Estimate::factory()->onProject($this->project)->withStatus(EstimateStatus::APPROVED)->create();
    $entry = MeasurementEntry::factory()->create(['project_id' => $this->project->id]);
    $verified = MeasurementEntry::factory()->withStatus(MbStatus::VERIFIED)->create(['project_id' => $this->project->id]);
    $inspection = SiteInspection::factory()->onProject($this->project)->create();

    Livewire::actingAs($this->pm)->test(EstimatesIndex::class)->call('deleteRecord', $draft->id)->call('deleteRecord', $approved->id);
    Livewire::actingAs($this->pm)->test(MbIndex::class)->set('selected', [(string) $entry->id, (string) $verified->id])->call('deleteSelected');
    Livewire::actingAs($this->pm)->test(InspectionsIndex::class)->call('deleteRecord', $inspection->id);

    expect($draft->fresh()->trashed())->toBeTrue()
        ->and($approved->fresh()->trashed())->toBeFalse()
        ->and($entry->fresh()->trashed())->toBeTrue()
        ->and($verified->fresh()->trashed())->toBeFalse()
        ->and($inspection->fresh()->trashed())->toBeTrue();
});

test('selected rows export on their own', function () {
    $estimate = Estimate::factory()->onProject($this->project)->create();
    Estimate::factory()->onProject($this->project)->create();

    Livewire::actingAs($this->pm)->test(EstimatesIndex::class)->set('selected', [(string) $estimate->id])->call('exportSelected')->assertFileDownloaded();
});

test('estimate, measurement and inspection pages and forms open in the detail modal', function (Closure $url) {
    Livewire::actingAs($this->pm)->test(DetailModal::class)->call('show', $url())->assertOk();
})->with([
    'estimate page' => [fn () => route('estimation.estimates.show', Estimate::factory()->onProject(test()->project)->create())],
    'new estimate' => [fn () => route('estimation.estimates.create', ['project' => 'SOC-BD-0103'])],
    'edit estimate' => [fn () => route('estimation.estimates.edit', Estimate::factory()->onProject(test()->project)->create())],
    'measurement page' => [fn () => route('site.mb.show', MeasurementEntry::factory()->create(['project_id' => test()->project->id]))],
    'inspection page' => [fn () => route('site.inspections.show', SiteInspection::factory()->onProject(test()->project)->create())],
    'new inspection' => [fn () => route('site.inspections.create', ['project' => 'SOC-BD-0103'])],
    'edit inspection' => [fn () => route('site.inspections.edit', SiteInspection::factory()->onProject(test()->project)->withStatus(InspectionStatus::DRAFT)->create())],
]);

test('the editor opened from a list saves back to the page under the modal', function () {
    $estimate = Estimate::factory()->onProject($this->project)->withLines(1)->create(['prepared_by' => $this->pm->employee_id]);

    Livewire::actingAs($this->pm)->test(Editor::class, ['estimate' => $estimate, 'returnUrl' => '/estimates'])
        ->set('title', 'Renamed')
        ->call('save')
        ->assertRedirect('/estimates');

    expect($estimate->fresh()->title)->toBe('Renamed');
});

test('the project query fills the new estimate form in the modal', function () {
    Livewire::actingAs($this->pm)->test(Editor::class, ['project' => 'SOC-BD-0103'])->assertSet('project_id', $this->project->id);
    Livewire::actingAs($this->pm)->test(EstimateShow::class, ['estimate' => Estimate::factory()->onProject($this->project)->create()])
        ->assertSeeHtml('data-detail-modal href="'.route('projects.projects.show', ['project' => $this->project, 'tab' => 'estimates']).'"');
});
