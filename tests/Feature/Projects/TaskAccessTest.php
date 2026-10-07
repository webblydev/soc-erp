<?php

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
});

test('an engineer sees only own tasks (PRJ-AC-05)', function () {
    $engineer = staffUser('engineer');
    $project = Project::factory()->create(['supervisor_id' => $engineer->employee_id]);
    $assigned = Task::factory()->onProject($project)->assignedTo(Employee::query()->findOrFail($engineer->employee_id))->create();
    $reviewing = Task::factory()->create(['reviewer_employee_id' => $engineer->employee_id]);
    $watched = Task::factory()->create();
    DB::table('task_watchers')->insert(['task_id' => $watched->id, 'user_id' => $engineer->id]);
    Task::factory()->onProject($project)->create();

    expect(Task::query()->visibleTo($engineer)->pluck('id')->sort()->values()->all())->toBe([$assigned->id, $reviewing->id, $watched->id]);
});

test('a project manager sees every task of their projects plus own tasks', function () {
    $pm = staffUser('project_manager');
    $project = Project::factory()->managedBy(Employee::query()->findOrFail($pm->employee_id))->create();
    $onProject = Task::factory()->onProject($project)->create();
    $mine = Task::factory()->create(['assigned_by' => $pm->id]);
    Task::factory()->create();

    expect(Task::query()->visibleTo($pm)->pluck('id')->sort()->values()->all())->toBe([$onProject->id, $mine->id]);
});

test('management sees all tasks, others with no task permission none', function () {
    Task::factory()->count(2)->create();

    expect(Task::query()->visibleTo(staffUser('management'))->count())->toBe(2)
        ->and(Task::query()->visibleTo(User::factory()->create())->count())->toBe(0);
});

test('status changes are limited to the people on the task (PRJ-BR-12)', function () {
    $pm = staffUser('project_manager');
    $assignee = staffUser('engineer');
    $reviewer = staffUser('engineer');
    $bystander = staffUser('engineer');
    $project = Project::factory()->managedBy(Employee::query()->findOrFail($pm->employee_id))->create();
    $task = Task::factory()->onProject($project)->assignedTo(Employee::query()->findOrFail($assignee->employee_id))
        ->create(['reviewer_employee_id' => $reviewer->employee_id]);
    DB::table('task_watchers')->insert(['task_id' => $task->id, 'user_id' => $bystander->id]);

    expect($assignee->can('changeStatus', $task))->toBeTrue()
        ->and($assignee->can('review', $task))->toBeFalse()
        ->and($reviewer->can('review', $task))->toBeTrue()
        ->and($pm->can('review', $task))->toBeTrue()
        ->and($bystander->can('view', $task))->toBeTrue()
        ->and($bystander->can('changeStatus', $task))->toBeFalse()
        ->and($assignee->can('update', $task))->toBeTrue()
        ->and($assignee->can('delete', $task))->toBeFalse();
});
