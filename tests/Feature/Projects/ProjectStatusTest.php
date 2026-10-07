<?php

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Actions\ChangeProjectStatus;
use App\Modules\Projects\Events\ProjectPhaseChanged;
use App\Modules\Projects\Events\ProjectStatusChanged;
use App\Modules\Projects\Models\HoldReason;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Models\ProjectPhase;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedProjects();
    seedAccessControl();

    $this->pm = staffUser('project_manager');
    $this->director = staffUser('management');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
});

function moveProjectTo(string $code, array $extra = [], ?User $actor = null): Project
{
    return app(ChangeProjectStatus::class)->handle($actor ?? test()->pm, test()->project->fresh(), ['project_status_id' => ProjectStatus::idFor($code), ...$extra]);
}

test('allowed moves write history and fire the event (docs/04 §6.1)', function () {
    Event::fake([ProjectStatusChanged::class, ProjectPhaseChanged::class]);

    moveProjectTo('HANDED_OVER', ['handover_date' => today()->toDateString(), 'reason' => 'Keys handed over']);

    $project = $this->project->fresh();
    expect($project->status->code)->toBe('HANDED_OVER')
        ->and($project->handover_date->toDateString())->toBe(today()->toDateString())
        ->and($project->statusHistories()->first())->reason->toBe('Keys handed over')->changed_by->toBe($this->pm->id);

    Event::assertDispatched(ProjectStatusChanged::class);
    Event::assertNotDispatched(ProjectPhaseChanged::class);
});

test('moves outside the map are refused', function () {
    expectValidationError(fn () => moveProjectTo('COMPLETED', ['actual_end_date' => today()->toDateString()]), 'project_status_id');
    expectValidationError(fn () => moveProjectTo('ENQUIRY'), 'project_status_id');
});

test('on hold needs a hold reason and clears it when resumed', function () {
    expectValidationError(fn () => moveProjectTo('ON_HOLD'), 'hold_reason_id');

    moveProjectTo('ON_HOLD', ['hold_reason_id' => HoldReason::idFor('LAND_DISPUTE')]);
    expect($this->project->fresh()->hold_reason_id)->toBe(HoldReason::idFor('LAND_DISPUTE'));

    moveProjectTo('IN_PROGRESS');
    expect($this->project->fresh()->hold_reason_id)->toBeNull();
});

test('cancelling needs a reason', function () {
    expectValidationError(fn () => moveProjectTo('CANCELLED'), 'cancel_reason');

    moveProjectTo('CANCELLED', ['cancel_reason' => 'Client withdrew']);

    expect($this->project->fresh())->cancel_reason->toBe('Client withdrew')->and($this->project->fresh()->allowsBilling())->toBeFalse();
});

test('completing is blocked by open tasks and approvals unless management overrides (PRJ-BR-08, PRJ-AC-06)', function () {
    $this->project->forceFill(['project_status_id' => ProjectStatus::idFor('HANDED_OVER')])->save();
    Task::factory()->onProject($this->project)->create();
    ProjectApproval::factory()->create(['project_id' => $this->project->id]);

    try {
        moveProjectTo('COMPLETED', ['actual_end_date' => today()->toDateString()]);
        $this->fail('Expected the completion checks to block.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['completion'])->toContain('1 task is still open.', '1 approval is not final yet.');
    }

    expectValidationError(fn () => moveProjectTo('COMPLETED', ['actual_end_date' => today()->toDateString(), 'override_reason' => 'PM says so']), 'completion');

    moveProjectTo('COMPLETED', ['actual_end_date' => today()->toDateString(), 'override_reason' => 'Approval letter lost, handed over'], $this->director);

    expect($this->project->fresh()->status->code)->toBe('COMPLETED');
});

test('completing with everything closed sets the end date and 100 %', function () {
    $this->project->forceFill(['project_status_id' => ProjectStatus::idFor('HANDED_OVER')])->save();
    Task::factory()->onProject($this->project)->withStatus(TaskStatus::DONE)->create();

    expectValidationError(fn () => moveProjectTo('COMPLETED'), 'actual_end_date');

    moveProjectTo('COMPLETED', ['actual_end_date' => today()->toDateString()]);

    expect($this->project->fresh())->completion_pct->toBe('100.00')->actual_end_date->not->toBeNull();
});

test('reopening a closed project needs the reopen permission and a reason', function () {
    $this->project->forceFill(['project_status_id' => ProjectStatus::idFor('COMPLETED'), 'actual_end_date' => today()])->save();

    expect(fn () => moveProjectTo('IN_PROGRESS', ['reason' => 'Defects']))->toThrow(AuthorizationException::class);
    expectValidationError(fn () => moveProjectTo('IN_PROGRESS', [], $this->director), 'reason');

    moveProjectTo('IN_PROGRESS', ['reason' => 'Defects found'], $this->director);

    expect($this->project->fresh())->actual_end_date->toBeNull()->and($this->project->fresh()->status->code)->toBe('IN_PROGRESS');
});

test('the phase can change alone and fires ProjectPhaseChanged', function () {
    Event::fake([ProjectStatusChanged::class, ProjectPhaseChanged::class]);

    app(ChangeProjectStatus::class)->handle($this->pm, $this->project, ['project_phase_id' => ProjectPhase::idFor('APPROVAL')]);

    expect($this->project->fresh()->phase->code)->toBe('APPROVAL');
    Event::assertDispatched(ProjectPhaseChanged::class);
    Event::assertNotDispatched(ProjectStatusChanged::class);
});

test('a change with nothing new is refused', function () {
    expectValidationError(fn () => app(ChangeProjectStatus::class)->handle($this->pm, $this->project, []), 'project_status_id');
});

test('status changes need change_status on a visible project', function () {
    app(ChangeProjectStatus::class)->handle(staffUser('engineer'), $this->project, ['project_phase_id' => ProjectPhase::idFor('APPROVAL')]);
})->throws(AuthorizationException::class);
