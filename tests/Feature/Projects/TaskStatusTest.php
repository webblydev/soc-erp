<?php

use App\Models\User;
use App\Modules\Foundation\Models\Setting;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Actions\ArchiveTask;
use App\Modules\Projects\Actions\ChangeTaskStatus;
use App\Modules\Projects\Events\TaskCompleted;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Notifications\TaskReviewRequested;
use App\Support\Facades\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->pm = staffUser('project_manager');
    $this->assignee = staffUser('engineer');
    $this->reviewer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
    $this->task = Task::factory()->onProject($this->project)->assignedTo(Employee::query()->findOrFail($this->assignee->employee_id))
        ->create(['reviewer_employee_id' => $this->reviewer->employee_id]);
});

function moveTaskTo(string $code, array $input = [], ?User $actor = null): array
{
    return app(ChangeTaskStatus::class)->handle($actor ?? test()->assignee, test()->task->fresh(), TaskStatus::idFor($code), $input);
}

test('the assignee starts and submits; the reviewer is asked (docs/04 §6.2)', function () {
    moveTaskTo('IN_PROGRESS');
    moveTaskTo('REVIEW');

    expect($this->task->fresh()->status->code)->toBe(TaskStatus::REVIEW);
    Notification::assertSentTo($this->reviewer, TaskReviewRequested::class);
});

test('only the reviewer or PM completes or rejects a reviewed task (spec P15)', function () {
    $this->task->forceFill(['task_status_id' => TaskStatus::idFor('REVIEW')])->save();

    expect(fn () => moveTaskTo('DONE'))->toThrow(AuthorizationException::class)
        ->and(fn () => moveTaskTo('IN_PROGRESS'))->toThrow(AuthorizationException::class);

    moveTaskTo('IN_PROGRESS', [], $this->reviewer);
    expect($this->task->fresh()->status->code)->toBe(TaskStatus::IN_PROGRESS);

    $this->task->forceFill(['task_status_id' => TaskStatus::idFor('REVIEW')])->save();
    moveTaskTo('DONE', [], $this->pm);
    expect($this->task->fresh()->status->code)->toBe(TaskStatus::DONE);
});

test('completing sets the completion fields and fires TaskCompleted; reopening clears them', function () {
    Event::fake([TaskCompleted::class]);

    moveTaskTo('DONE');
    $task = $this->task->fresh();
    expect($task)->completed_by->toBe($this->assignee->id)->progress_pct->toBe(100)->and($task->completed_at)->not->toBeNull();
    Event::assertDispatched(TaskCompleted::class);

    moveTaskTo('IN_PROGRESS');
    expect($this->task->fresh())->completed_at->toBeNull()->completed_by->toBeNull();
});

test('invalid moves are refused', function () {
    expectValidationError(fn () => moveTaskTo('REVIEW'), 'task_status_id');
    expectValidationError(fn () => moveTaskTo('TODO'), 'task_status_id');
});

test('blocking needs a reason, unblocking clears it', function () {
    expectValidationError(fn () => moveTaskTo('BLOCKED'), 'blocked_reason');

    moveTaskTo('BLOCKED', ['blocked_reason' => 'Waiting for soil report']);
    expect($this->task->fresh()->blocked_reason)->toBe('Waiting for soil report');

    moveTaskTo('IN_PROGRESS');
    expect($this->task->fresh()->blocked_reason)->toBeNull();
});

test('open checklist items need confirmation, or block when configured (PRJ-BR-13)', function () {
    $this->task->checklist()->create(['title' => 'Upload drawing']);

    expectValidationError(fn () => moveTaskTo('DONE'), 'confirm_open_checklist');
    moveTaskTo('DONE', ['confirm_open_checklist' => true]);
    expect($this->task->fresh()->status->code)->toBe(TaskStatus::DONE);

    moveTaskTo('IN_PROGRESS');
    Setting::query()->where('group', 'projects')->where('key', 'block_complete_with_open_checklist')->update(['value' => true]);
    Settings::flush();

    expectValidationError(fn () => moveTaskTo('DONE', ['confirm_open_checklist' => true]), 'checklist');
});

test('starting a task with unfinished predecessors warns but proceeds', function () {
    $first = Task::factory()->onProject($this->project)->create(['title' => 'Site survey']);
    $this->task->predecessors()->attach($first->id);

    $warnings = moveTaskTo('IN_PROGRESS');

    expect($warnings)->toHaveCount(1)->and($warnings[0])->toContain('Site survey')
        ->and($this->task->fresh()->status->code)->toBe(TaskStatus::IN_PROGRESS);
});

test('people not on the task cannot change its status (PRJ-BR-12)', function () {
    moveTaskTo('IN_PROGRESS', [], staffUser('engineer'));
})->throws(AuthorizationException::class);

test('completion % follows the tasks when completion_from_tasks is on', function () {
    Setting::query()->where('group', 'projects')->where('key', 'completion_from_tasks')->update(['value' => true]);
    Settings::flush();
    Task::factory()->onProject($this->project)->create();
    Task::factory()->onProject($this->project)->withStatus('CANCELLED')->create();

    moveTaskTo('DONE');

    expect($this->project->fresh()->completion_pct)->toBe('50.00');
});

test('only finished tasks are archived', function () {
    expectValidationError(fn () => app(ArchiveTask::class)->handle($this->pm, $this->task), 'task');

    moveTaskTo('DONE');
    app(ArchiveTask::class)->handle($this->pm, $this->task->fresh());

    expect($this->task->fresh()->archived_at)->not->toBeNull();
});
