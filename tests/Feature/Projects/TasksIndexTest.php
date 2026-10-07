<?php

use App\Modules\Crm\Models\Customer;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Livewire\Tasks\Index;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->engineer = staffUser('engineer');
    $this->employee = Employee::query()->findOrFail($this->engineer->employee_id);
});

$row = fn (Task $task): string => 'wire:key="task-'.$task->id.'"';

test('the list needs a task view permission', function () {
    $this->actingAs(userWithPermissions('crm.leads.view_own'));
    $this->get(route('projects.tasks.index'))->assertForbidden();

    $this->actingAs($this->engineer);
    $this->get(route('projects.tasks.index'))->assertOk();
});

test('presets replace the legacy task menus', function () use ($row) {
    $mine = Task::factory()->assignedTo($this->employee)->create();
    $assignedByMe = Task::factory()->create(['assigned_by' => $this->engineer->id]);
    $overdue = Task::factory()->assignedTo($this->employee)->create(['due_date' => today()->subDay()]);
    $done = Task::factory()->assignedTo($this->employee)->withStatus(TaskStatus::DONE)->create();
    $archived = Task::factory()->assignedTo($this->employee)->withStatus(TaskStatus::DONE)->create(['archived_at' => now()]);

    $component = Livewire::actingAs($this->engineer)->test(Index::class);

    $component->assertSeeHtml($row($mine))->assertSeeHtml($row($overdue))->assertDontSeeHtml($row($done))->assertDontSeeHtml($row($assignedByMe));
    $component->call('applyPreset', 'assigned_by_me')->assertSeeHtml($row($assignedByMe))->assertDontSeeHtml($row($mine));
    $component->call('applyPreset', 'overdue')->assertSeeHtml($row($overdue))->assertDontSeeHtml($row($mine));
    $component->call('applyPreset', 'completed')->assertSeeHtml($row($done))->assertDontSeeHtml($row($archived));
    $component->call('applyPreset', 'archived')->assertSeeHtml($row($archived))->assertDontSeeHtml($row($done));
});

test('the list shows the legacy columns without lazy loading (PRJ-AC-08)', function () {
    Model::preventLazyLoading();
    $project = Project::factory()->forCustomer(Customer::factory()->create(['name' => 'Legacy Client']))->create();
    Task::factory()->onProject($project)->assignedTo($this->employee)->create(['file_number' => 'F-2201', 'support_officer_id' => Employee::factory()->create(['full_name' => 'Support Person'])->id]);

    Livewire::actingAs(staffUser('management'))->test(Index::class)->call('applyPreset', 'all')
        ->assertSee(['File no.', 'Customer', 'Entry date', 'Project type', 'Support officer', 'Assigned by', 'Completed'])
        ->assertSee(['F-2201', 'Legacy Client', 'Support Person', $project->project_number]);

    Model::preventLazyLoading(false);
});

test('the board moves a task and refuses a bad move', function () {
    $task = Task::factory()->assignedTo($this->employee)->create();

    $component = Livewire::actingAs($this->engineer)->test(Index::class)->set('view', 'board')->assertSee($task->title);

    $component->call('moveTask', (string) $task->id, 0, (string) TaskStatus::idFor('IN_PROGRESS'));
    expect($task->fresh()->status->code)->toBe(TaskStatus::IN_PROGRESS);

    $component->call('moveTask', (string) $task->id, 0, (string) TaskStatus::idFor('TODO'))->assertDispatched('toast');
    expect($task->fresh()->status->code)->toBe(TaskStatus::IN_PROGRESS);
});

test('bulk actions reassign, reschedule, change status and archive', function () {
    $director = staffUser('management');
    $task = Task::factory()->create();
    $other = Employee::factory()->create();

    $component = Livewire::actingAs($director)->test(Index::class)->call('applyPreset', 'all');

    $component->set('selected', [(string) $task->id])->set('bulkAssigneeId', (string) $other->id)->call('bulkReassign');
    $component->set('selected', [(string) $task->id])->set('bulkDueDate', today()->addWeek()->toDateString())->call('bulkSetDueDate');
    $component->set('selected', [(string) $task->id])->set('bulkStatusId', (string) TaskStatus::idFor('DONE'))->call('bulkChangeStatus');
    $component->set('selected', [(string) $task->id])->call('bulkArchive');

    $task = $task->fresh();
    expect($task)->assignee_employee_id->toBe($other->id)
        ->and($task->due_date->toDateString())->toBe(today()->addWeek()->toDateString())
        ->and($task->status->code)->toBe(TaskStatus::DONE)
        ->and($task->archived_at)->not->toBeNull();
});

test('the overdue preset can come from the notification link', function () use ($row) {
    $overdue = Task::factory()->assignedTo($this->employee)->create(['due_date' => today()->subDays(3)]);

    Livewire::actingAs($this->engineer)->withQueryParams(['preset' => 'overdue'])->test(Index::class)->assertSeeHtml($row($overdue));
});

test('tasks export to Excel', function () {
    Task::factory()->assignedTo($this->employee)->create();

    Livewire::actingAs($this->engineer)->test(Index::class)->call('export')->assertFileDownloaded();
});
