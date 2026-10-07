<?php

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Projects\Actions\AddTeamMember;
use App\Modules\Projects\Actions\ReleaseTeamMember;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectRole;
use App\Modules\Projects\Notifications\TeamMemberAdded;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->pm = staffUser('project_manager');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
});

test('a PM adds a member and the member is told (spec P13)', function () {
    $engineer = staffUser('engineer');

    $member = app(AddTeamMember::class)->handle($this->pm, $this->project, [
        'employee_id' => $engineer->employee_id, 'project_role_id' => ProjectRole::idFor('SITE_ENGINEER'),
        'allocation_pct' => '50', 'assigned_on' => today()->toDateString(),
    ]);

    expect($member)->is_active->toBeTrue()->allocation_pct->toBe('50.00');
    Notification::assertSentTo($engineer, TeamMemberAdded::class);
});

test('the same employee cannot hold the same active role twice', function () {
    $employee = Employee::factory()->create();
    $input = ['employee_id' => $employee->id, 'project_role_id' => ProjectRole::idFor('ARCHITECT'), 'assigned_on' => today()->toDateString()];

    app(AddTeamMember::class)->handle($this->pm, $this->project, $input);

    expectValidationError(fn () => app(AddTeamMember::class)->handle($this->pm, $this->project, $input), 'employee_id');
});

test('former employees cannot join a team (HR-BR-06)', function () {
    $left = Employee::factory()->withStatus(EmployeeStatus::RESIGNED)->create();

    expectValidationError(fn () => app(AddTeamMember::class)->handle($this->pm, $this->project, [
        'employee_id' => $left->id, 'project_role_id' => ProjectRole::idFor('ARCHITECT'), 'assigned_on' => today()->toDateString(),
    ]), 'employee_id');
});

test('releasing ends the membership and frees the role', function () {
    $employee = Employee::factory()->create();
    $input = ['employee_id' => $employee->id, 'project_role_id' => ProjectRole::idFor('ARCHITECT'), 'assigned_on' => today()->subWeek()->toDateString()];
    $member = app(AddTeamMember::class)->handle($this->pm, $this->project, $input);

    expectValidationError(fn () => app(ReleaseTeamMember::class)->handle($this->pm, $member, ['released_on' => today()->subMonth()->toDateString()]), 'released_on');

    app(ReleaseTeamMember::class)->handle($this->pm, $member, ['released_on' => today()->toDateString()]);

    expect($member->fresh())->is_active->toBeFalse()->released_on->toDateString()->toBe(today()->toDateString())
        ->and(app(AddTeamMember::class)->handle($this->pm, $this->project, $input)->is_active)->toBeTrue();
});

test('another PM cannot manage this team', function () {
    app(AddTeamMember::class)->handle(staffUser('project_manager'), $this->project, [
        'employee_id' => Employee::factory()->create()->id, 'project_role_id' => ProjectRole::idFor('ARCHITECT'), 'assigned_on' => today()->toDateString(),
    ]);
})->throws(AuthorizationException::class);
