<?php

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Foundation\Models\Setting;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Support\Facades\Settings;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
});

test('an engineer sees only the projects they are on (PRJ-AC-05, PRJ-BR-18)', function () {
    $engineer = staffUser('engineer');
    $first = Project::factory()->create();
    $second = Project::factory()->create(['supervisor_id' => $engineer->employee_id]);
    $other = Project::factory()->create();
    ProjectEmployee::factory()->create(['project_id' => $first->id, 'employee_id' => $engineer->employee_id]);
    ProjectEmployee::factory()->create(['project_id' => $other->id, 'employee_id' => $engineer->employee_id, 'is_active' => false, 'released_on' => today()]);

    expect(Project::query()->visibleTo($engineer)->pluck('id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and($engineer->can('view', $first))->toBeTrue()
        ->and($engineer->can('view', $other))->toBeFalse()
        ->and($engineer->can('update', $first))->toBeFalse();
});

test('management and accountants see every project', function (string $role) {
    $user = staffUser($role);
    Project::factory()->count(2)->create();

    expect(Project::query()->visibleTo($user)->count())->toBe(2);
})->with(['management', 'accountant']);

test('a project manager sees own projects unless pm_can_view_all is on', function () {
    $pm = staffUser('project_manager');
    $own = Project::factory()->managedBy(Employee::query()->findOrFail($pm->employee_id))->create();
    Project::factory()->create();

    expect(Project::query()->visibleTo($pm)->pluck('id')->all())->toBe([$own->id]);

    Setting::query()->where('group', 'projects')->where('key', 'pm_can_view_all')->update(['value' => true]);
    Settings::flush();

    expect(Project::query()->visibleTo($pm)->count())->toBe(2);
});

test('sales users see the projects of their own customers', function () {
    $seller = staffUser('sales_executive');
    $customer = Customer::factory()->create(['account_manager_user_id' => $seller->id]);
    $theirs = Project::factory()->forCustomer($customer)->create();
    Project::factory()->create();

    expect(Project::query()->visibleTo($seller)->pluck('id')->all())->toBe([$theirs->id]);
});

test('a user without project permissions sees nothing', function () {
    Project::factory()->create();

    expect(Project::query()->visibleTo(User::factory()->create())->count())->toBe(0);
});

test('only the PM or a view_all user manages the team (spec P13)', function () {
    $pm = staffUser('project_manager');
    $supervisor = staffUser('project_manager');
    $project = Project::factory()->managedBy(Employee::query()->findOrFail($pm->employee_id))->create(['supervisor_id' => $supervisor->employee_id]);

    expect($pm->can('manageTeam', $project))->toBeTrue()
        ->and($supervisor->can('view', $project))->toBeTrue()
        ->and($supervisor->can('manageTeam', $project))->toBeFalse()
        ->and(staffUser('management')->can('manageTeam', $project))->toBeTrue();
});

test('approvals follow the project visibility', function () {
    $engineer = staffUser('engineer');
    $project = Project::factory()->create(['supervisor_id' => $engineer->employee_id]);
    $mine = ProjectApproval::factory()->create(['project_id' => $project->id]);
    $other = ProjectApproval::factory()->create();

    expect(ProjectApproval::query()->visibleTo($engineer)->pluck('id')->all())->toBe([$mine->id])
        ->and($engineer->can('update', $mine))->toBeTrue()
        ->and($engineer->can('view', $other))->toBeFalse()
        ->and(staffUser('sales_executive')->can('update', $mine))->toBeFalse();
});
