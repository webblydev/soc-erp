<?php

use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Livewire\Tasks\Form;
use App\Modules\Projects\Livewire\Tasks\Show;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
    $this->task = Task::factory()->onProject($this->project)->assignedTo(Employee::query()->findOrFail($this->engineer->employee_id))->create();
});

test('the form creates a task on the project from the query string', function () {
    $component = Livewire::actingAs($this->pm)->withQueryParams(['project' => $this->project->project_number])->test(Form::class)
        ->assertSet('project_id', $this->project->id)
        ->set('title', 'Structural design')
        ->set('task_type_id', TaskType::idFor('STRUCTURAL_CALC'))
        ->call('addChecklistItem')
        ->set('checklist.0.title', 'Load calculation')
        ->call('save')
        ->assertHasNoErrors();

    $task = Task::query()->where('title', 'Structural design')->firstOrFail();
    expect($task->project_id)->toBe($this->project->id)->and($task->checklist()->count())->toBe(1);
    $component->assertRedirect(route('projects.tasks.show', $task));
});

test('the form edits a task', function () {
    Livewire::actingAs($this->pm)->test(Form::class, ['task' => $this->task])
        ->set('title', 'Renamed task')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->task->fresh()->title)->toBe('Renamed task');
});

test('the detail page renders every tab without lazy loading', function (string $tab) {
    Model::preventLazyLoading();

    $this->actingAs(staffUser('engineer'))->get(route('projects.tasks.show', $this->task))->assertForbidden();

    Livewire::actingAs($this->engineer)->test(Show::class, ['task' => $this->task])->set('tab', $tab)->assertOk()->assertSee($this->task->task_number);

    Model::preventLazyLoading(false);
})->with(['details', 'comments', 'time', 'documents', 'history']);

test('the assignee starts, blocks with a reason and completes with confirmation', function () {
    $this->task->checklist()->create(['title' => 'Upload drawing']);
    $component = Livewire::actingAs($this->engineer)->test(Show::class, ['task' => $this->task]);

    $component->call('moveTo', TaskStatus::idFor('IN_PROGRESS'));
    expect($this->task->fresh()->status->code)->toBe(TaskStatus::IN_PROGRESS);

    $component->call('moveTo', TaskStatus::idFor('BLOCKED'))->assertDispatched('open-sheet-task-block')
        ->call('block')->assertHasErrors('blockedReason')
        ->set('blockedReason', 'Waiting for client')->call('block')->assertHasNoErrors();
    expect($this->task->fresh()->status->code)->toBe(TaskStatus::BLOCKED);

    $component->call('moveTo', TaskStatus::idFor('IN_PROGRESS'))
        ->call('moveTo', TaskStatus::idFor('DONE'))->assertDispatched('open-sheet-task-confirm-complete');
    expect($this->task->fresh()->status->code)->toBe(TaskStatus::IN_PROGRESS);

    $component->call('confirmComplete');
    expect($this->task->fresh()->status->code)->toBe(TaskStatus::DONE);
});

test('the review buttons are hidden from the assignee', function () {
    $this->task->forceFill(['task_status_id' => TaskStatus::idFor('REVIEW')])->save();

    Livewire::actingAs($this->engineer)->test(Show::class, ['task' => $this->task])
        ->assertDontSee('Reject review')->assertSee('Cancel');

    Livewire::actingAs($this->pm)->test(Show::class, ['task' => $this->task])
        ->assertSee('Reject review')->assertSee('Complete');
});

test('comments and time are added from the detail page', function () {
    Livewire::actingAs($this->engineer)->test(Show::class, ['task' => $this->task])
        ->set('tab', 'comments')
        ->set('comment', 'Drawings uploaded')
        ->call('addComment')
        ->assertSee('Drawings uploaded')
        ->set('timeForm.hours', '2.5')
        ->call('logTime')
        ->assertHasNoErrors();

    expect($this->task->fresh()->actual_hours)->toBe('2.50');
});
