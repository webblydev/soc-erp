<?php

use App\Modules\Estimation\Livewire\Estimates\Show;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    Notification::fake();
    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
    ProjectEmployee::factory()->create(['project_id' => $this->project->id, 'employee_id' => $this->engineer->employee_id]);
});

function shownEstimate(string $status = EstimateStatus::DRAFT): Estimate
{
    return Estimate::factory()->onProject(test()->project)->withStatus($status)->withLines(2)->create(['prepared_by' => test()->engineer->employee_id]);
}

test('the page is limited to people who can see the project', function () {
    $estimate = shownEstimate();

    $this->actingAs(staffUser('engineer'));
    $this->get(route('estimation.estimates.show', $estimate))->assertForbidden();

    $this->actingAs($this->engineer);
    $this->get(route('estimation.estimates.show', $estimate))->assertOk()->assertSee($estimate->estimate_number)->assertSee('Submit');
});

test('buttons follow the status and the permissions', function () {
    $submitted = shownEstimate(EstimateStatus::SUBMITTED);

    Livewire::actingAs($this->engineer)->test(Show::class, ['estimate' => $submitted])->assertDontSee('Approve')->assertDontSee('Edit');
    Livewire::actingAs($this->pm)->test(Show::class, ['estimate' => $submitted])->assertSee('Approve')->assertSee('Reject');
});

test('the workflow runs from the page', function () {
    $estimate = shownEstimate();

    Livewire::actingAs($this->engineer)->test(Show::class, ['estimate' => $estimate])->call('submit')->assertDispatched('toast');
    Livewire::actingAs($this->pm)->test(Show::class, ['estimate' => $estimate->fresh()])
        ->call('reject')->assertHasErrors('rejectNote')
        ->set('rejectNote', 'Check the rates')->call('reject')->assertHasNoErrors();

    expect($estimate->fresh()->status->code)->toBe(EstimateStatus::REJECTED);
});

test('revising from the page opens the new draft', function () {
    $estimate = shownEstimate(EstimateStatus::APPROVED);

    Livewire::actingAs($this->engineer)->test(Show::class, ['estimate' => $estimate])
        ->set('revisePurpose', 'More rooms')
        ->call('revise')
        ->assertRedirect(route('estimation.estimates.edit', $estimate->estimate_number.'-R1'));
});

test('the tabs render lines, history, documents and notes', function (string $tab) {
    Livewire::actingAs($this->pm)->test(Show::class, ['estimate' => shownEstimate()])->set('tab', $tab)->assertOk();
})->with(['lines', 'materials', 'history', 'documents', 'notes']);

test('a draft is deleted from the page', function () {
    $estimate = shownEstimate();

    Livewire::actingAs($this->pm)->test(Show::class, ['estimate' => $estimate])->call('delete')->assertRedirect(route('estimation.estimates.index'));

    expect($estimate->fresh()->trashed())->toBeTrue();
});
