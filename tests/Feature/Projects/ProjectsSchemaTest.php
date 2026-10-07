<?php

use App\Modules\Crm\Models\Lead;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectContract;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use Illuminate\Database\QueryException;

test('a project belongs to its customer, business line, type, status and manager', function () {
    $manager = Employee::factory()->create();
    $project = Project::factory()->managedBy($manager)->create();

    expect($project->manager->is($manager))->toBeTrue()
        ->and($project->customer)->not->toBeNull()
        ->and($project->businessLine)->not->toBeNull()
        ->and($project->status->code)->toBe(ProjectStatus::IN_PROGRESS)
        ->and($project->getRouteKeyName())->toBe('project_number');
});

test('a project has team members, a contract and tasks', function () {
    $project = Project::factory()->create();
    $member = ProjectEmployee::factory()->create(['project_id' => $project->id]);
    $contract = ProjectContract::factory()->create(['project_id' => $project->id]);
    $task = Task::factory()->onProject($project)->create();
    $subtask = Task::factory()->onProject($project)->create(['parent_id' => $task->id]);

    expect($project->activeTeam->pluck('id')->all())->toBe([$member->id])
        ->and($project->contract->is($contract))->toBeTrue()
        ->and($project->hasSignedContract())->toBeFalse()
        ->and($project->tasks()->count())->toBe(2)
        ->and($task->subtasks->pluck('id')->all())->toBe([$subtask->id])
        ->and($task->getRouteKeyName())->toBe('task_number');
});

test('open and overdue task scopes follow the status flags and due date', function () {
    $overdue = Task::factory()->create(['due_date' => today()->subDay()]);
    Task::factory()->withStatus(TaskStatus::DONE)->create(['due_date' => today()->subDay()]);
    Task::factory()->create(['due_date' => today()]);

    expect(Task::query()->overdue()->pluck('id')->all())->toBe([$overdue->id])
        ->and($overdue->isOverdue())->toBeTrue();
});

test('internal projects have no customer and are not billable', function () {
    $project = Project::factory()->internal()->create();

    expect($project->customer_id)->toBeNull()
        ->and($project->isInternal())->toBeTrue()
        ->and($project->allowsBilling())->toBeFalse();
});

test('a lead points at the project it was converted into', function () {
    $project = Project::factory()->create();
    $lead = Lead::factory()->create(['converted_project_id' => $project->id]);

    expect($lead->fresh()->converted_project_id)->toBe($project->id)
        ->and(fn () => Lead::factory()->create(['converted_project_id' => 999999]))->toThrow(QueryException::class);
});
