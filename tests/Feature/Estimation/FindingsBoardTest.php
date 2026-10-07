<?php

use App\Modules\Estimation\Livewire\Findings\Board;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use Livewire\Livewire;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    $this->pm = staffUser('project_manager');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
    $this->inspection = SiteInspection::factory()->onProject($this->project)->withStatus(InspectionStatus::SUBMITTED)->create();
    $this->finding = SiteInspectionFinding::factory()->forInspection($this->inspection)->create(['location' => 'Roof slab']);
});

test('the board groups visible findings by status and hides drafts', function () {
    SiteInspectionFinding::factory()->forInspection(SiteInspection::factory()->onProject($this->project)->create())->create(['location' => 'Draft finding']);
    SiteInspectionFinding::factory()->forInspection(SiteInspection::factory()->withStatus(InspectionStatus::SUBMITTED)->create())->create(['location' => 'Other project']);

    Livewire::actingAs($this->pm)->test(Board::class)
        ->assertSee('Roof slab')->assertDontSee('Draft finding')->assertDontSee('Other project');
});

test('moving a card changes the status, and a closing move asks for a note', function () {
    Livewire::actingAs($this->pm)->test(Board::class)
        ->call('moveFinding', (string) $this->finding->id, 0, (string) FindingStatus::idFor(FindingStatus::IN_PROGRESS))
        ->assertDispatched('toast')
        ->call('moveFinding', (string) $this->finding->id, 0, (string) FindingStatus::idFor(FindingStatus::RESOLVED))
        ->assertDispatched('open-sheet-finding-close')
        ->call('close')
        ->assertHasErrors('closingNote')
        ->set('closingNote', 'Fixed')
        ->call('close')
        ->assertHasNoErrors();

    expect($this->finding->fresh()->status->code)->toBe(FindingStatus::RESOLVED);
});

test('a refused move leaves the finding where it was', function () {
    $engineer = staffUser('engineer');
    ProjectEmployee::factory()->create(['project_id' => $this->project->id, 'employee_id' => $engineer->employee_id]);

    Livewire::actingAs($engineer)->test(Board::class)
        ->call('moveFinding', (string) $this->finding->id, 0, (string) FindingStatus::idFor(FindingStatus::IN_PROGRESS))
        ->assertDispatched('toast', type: 'error');

    expect($this->finding->fresh()->status->code)->toBe(FindingStatus::OPEN);
});
