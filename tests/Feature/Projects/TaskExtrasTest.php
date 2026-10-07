<?php

use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Actions\AddTaskComment;
use App\Modules\Projects\Actions\DeleteTaskComment;
use App\Modules\Projects\Actions\LogTaskTime;
use App\Modules\Projects\Actions\ToggleChecklistItem;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Notifications\TaskMentioned;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->pm = staffUser('project_manager');
    $this->assignee = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
    $this->task = Task::factory()->onProject($this->project)->assignedTo(Employee::query()->findOrFail($this->assignee->employee_id))->create();
});

test('mentions notify users who can see the task (spec P16)', function () {
    $outsider = staffUser('engineer');

    app(AddTaskComment::class)->handle($this->assignee, $this->task, "Drawings uploaded @{$this->pm->username}, cc @{$outsider->username} @nobody");

    Notification::assertSentTo($this->pm, TaskMentioned::class);
    Notification::assertNotSentTo($outsider, TaskMentioned::class);
    expect($this->task->comments()->count())->toBe(1);
});

test('comments are deleted by their author or a task deleter', function () {
    $comment = app(AddTaskComment::class)->handle($this->assignee, $this->task, 'First draft');

    expect(fn () => app(DeleteTaskComment::class)->handle(staffUser('engineer'), $comment))->toThrow(AuthorizationException::class);

    app(DeleteTaskComment::class)->handle($this->pm, $comment);
    expect($comment->fresh()->trashed())->toBeTrue();
});

test('time logs add up to the actual hours', function () {
    app(LogTaskTime::class)->handle($this->assignee, $this->task, ['work_date' => today()->toDateString(), 'hours' => '3.5']);
    app(LogTaskTime::class)->handle($this->assignee, $this->task, ['work_date' => today()->subDay()->toDateString(), 'hours' => '2']);

    expect($this->task->fresh()->actual_hours)->toBe('5.50')
        ->and($this->task->timeLogs()->first()->employee_id)->toBe($this->assignee->employee_id);

    expectValidationError(fn () => app(LogTaskTime::class)->handle($this->assignee, $this->task, ['work_date' => today()->addDay()->toDateString(), 'hours' => '1']), 'work_date');
    expectValidationError(fn () => app(LogTaskTime::class)->handle($this->assignee, $this->task, ['work_date' => today()->toDateString(), 'hours' => '30']), 'hours');
});

test('checklist items are ticked by people on the task', function () {
    $item = $this->task->checklist()->create(['title' => 'Upload']);

    app(ToggleChecklistItem::class)->handle($this->assignee, $item, true);
    expect($item->fresh())->is_done->toBeTrue()->done_by->toBe($this->assignee->id);

    expect(fn () => app(ToggleChecklistItem::class)->handle(staffUser('engineer'), $item, false))->toThrow(AuthorizationException::class);
});
