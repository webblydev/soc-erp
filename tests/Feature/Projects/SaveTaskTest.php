<?php

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Projects\Actions\CreateTask;
use App\Modules\Projects\Actions\DeleteTask;
use App\Modules\Projects\Actions\UpdateTask;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskPriority;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskType;
use App\Modules\Projects\Notifications\TaskAssigned;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create(['supervisor_id' => $this->engineer->employee_id]);
});

function taskInput(array $overrides = []): array
{
    return ['project_id' => test()->project->id, 'task_type_id' => TaskType::idFor('DESIGN'), 'title' => 'Architectural concept', 'task_priority_id' => TaskPriority::idFor('NORMAL'), 'start_date' => today()->toDateString(), 'due_date' => today()->addWeek()->toDateString(), ...$overrides];
}

test('a task gets a number, TODO, NORMAL and the type default hours', function () {
    $task = app(CreateTask::class)->handle($this->pm, taskInput(['checklist' => [['title' => 'Sketch'], ['title' => ' ']], 'watcher_ids' => [$this->engineer->id]]));

    expect($task->task_number)->toStartWith('T-')
        ->and($task->status->code)->toBe(TaskStatus::TODO)
        ->and($task->priority->code)->toBe('NORMAL')
        ->and($task->estimated_hours)->toBe('16.00')
        ->and($task->assigned_by)->toBe($this->pm->id)
        ->and($task->checklist()->pluck('title')->all())->toBe(['Sketch'])
        ->and($task->watchers()->pluck('users.id')->all())->toBe([$this->engineer->id]);
});

test('the due date cannot be before the start date (PRJ-BR-11)', function () {
    expectValidationError(fn () => app(CreateTask::class)->handle($this->pm, taskInput(['due_date' => today()->subDay()->toDateString()])), 'due_date');
});

test('sub-tasks go one level deep on the same project (PRJ-BR-11)', function () {
    $parent = app(CreateTask::class)->handle($this->pm, taskInput());
    $child = app(CreateTask::class)->handle($this->pm, taskInput(['parent_id' => $parent->id]));

    expectValidationError(fn () => app(CreateTask::class)->handle($this->pm, taskInput(['parent_id' => $child->id])), 'parent_id');
    expectValidationError(fn () => app(CreateTask::class)->handle($this->pm, taskInput(['project_id' => null, 'parent_id' => $parent->id])), 'parent_id');
    expectValidationError(fn () => app(UpdateTask::class)->handle($this->pm, $parent, taskInput(['parent_id' => Task::factory()->onProject($this->project)->create()->id])), 'parent_id');
});

test('assigning others needs the assign permission; the assignee is told (spec P14)', function () {
    $other = staffUser('engineer');

    expectValidationError(fn () => app(CreateTask::class)->handle($this->engineer, taskInput(['assignee_employee_id' => $other->employee_id])), 'assignee_employee_id');

    $own = app(CreateTask::class)->handle($this->engineer, taskInput(['assignee_employee_id' => $this->engineer->employee_id]));
    expect($own->assignee_employee_id)->toBe($this->engineer->employee_id);
    Notification::assertNotSentTo($this->engineer, TaskAssigned::class);

    app(CreateTask::class)->handle($this->pm, taskInput(['assignee_employee_id' => $other->employee_id]));
    Notification::assertSentTo($other, TaskAssigned::class);
});

test('assignees must be active employees (HR-BR-06)', function () {
    $left = Employee::factory()->withStatus(EmployeeStatus::RESIGNED)->create();

    expectValidationError(fn () => app(CreateTask::class)->handle($this->pm, taskInput(['assignee_employee_id' => $left->id])), 'assignee_employee_id');
});

test('engineers add tasks only to their own projects', function () {
    $foreign = Project::factory()->create();

    expectValidationError(fn () => app(CreateTask::class)->handle($this->engineer, taskInput(['project_id' => $foreign->id])), 'project_id');

    $general = app(CreateTask::class)->handle($this->engineer, taskInput(['project_id' => null]));
    expect($general->project_id)->toBeNull()->and($this->engineer->can('view', $general))->toBeTrue();
});

test('closed projects take no new tasks', function () {
    $this->project->forceFill(['project_status_id' => ProjectStatus::idFor('CANCELLED')])->save();

    expectValidationError(fn () => app(CreateTask::class)->handle($this->pm, taskInput()), 'project_id');
});

test('updating keeps the checklist done flags and tells a new assignee', function () {
    $task = app(CreateTask::class)->handle($this->pm, taskInput(['checklist' => [['title' => 'Sketch'], ['title' => 'Model']]]));
    $sketch = $task->checklist()->firstOrFail();
    $sketch->forceFill(['is_done' => true])->save();

    app(UpdateTask::class)->handle($this->pm, $task, taskInput([
        'title' => 'Concept v2', 'assignee_employee_id' => $this->engineer->employee_id,
        'checklist' => [['id' => $sketch->id, 'title' => 'Sketch (rev)'], ['title' => '3D view']],
    ]));

    expect($task->fresh()->title)->toBe('Concept v2')
        ->and($task->checklist()->get(['title', 'is_done'])->toArray())->toBe([['title' => 'Sketch (rev)', 'is_done' => true], ['title' => '3D view', 'is_done' => false]]);
    Notification::assertSentTo($this->engineer, TaskAssigned::class);
});

test('deleting a task soft-deletes its sub-tasks', function () {
    $parent = app(CreateTask::class)->handle($this->pm, taskInput());
    $child = app(CreateTask::class)->handle($this->pm, taskInput(['parent_id' => $parent->id]));

    app(DeleteTask::class)->handle($this->pm, $parent);

    expect($parent->fresh()->trashed())->toBeTrue()->and($child->fresh()->trashed())->toBeTrue();
    expect(fn () => app(DeleteTask::class)->handle($this->engineer, app(CreateTask::class)->handle($this->pm, taskInput())))->toThrow(AuthorizationException::class);
});
