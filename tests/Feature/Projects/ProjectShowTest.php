<?php

use App\Modules\Catalog\Models\Service;
use App\Modules\Foundation\Livewire\Shared\DetailModal;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Livewire\Projects\AmendmentForm;
use App\Modules\Projects\Livewire\Projects\ContractTab;
use App\Modules\Projects\Livewire\Projects\Show;
use App\Modules\Projects\Livewire\Projects\TasksTab;
use App\Modules\Projects\Livewire\Projects\TeamTab;
use App\Modules\Projects\Models\HoldReason;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectContract;
use App\Modules\Projects\Models\ProjectRole;
use App\Modules\Projects\Models\ProjectServiceStatus;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ScheduleTrigger;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->pm = staffUser('project_manager');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create(['contract_value' => 500000]);
    $this->project->services()->create(['service_id' => Service::factory()->create()->id, 'quantity' => 1, 'rate' => 500000, 'amount' => 500000, 'project_service_status_id' => ProjectServiceStatus::idFor('NOT_STARTED')]);
});

test('the page shows for visible projects only and renders every tab', function (string $tab) {
    Model::preventLazyLoading();

    $this->actingAs(staffUser('engineer'))->get(route('projects.projects.show', $this->project))->assertForbidden();

    Livewire::actingAs($this->pm)->test(Show::class, ['project' => $this->project])
        ->set('tab', $tab)
        ->assertOk()
        ->assertSee($this->project->project_number);

    Model::preventLazyLoading(false);
})->with(['overview', 'contract', 'team', 'tasks', 'approvals', 'activities', 'documents', 'notes', 'history']);

test('the status sheet changes status and shows field errors under the form', function () {
    Livewire::actingAs($this->pm)->test(Show::class, ['project' => $this->project])
        ->set('statusForm.project_status_id', ProjectStatus::idFor('ON_HOLD'))
        ->call('changeStatus')
        ->assertHasErrors('statusForm.hold_reason_id')
        ->set('statusForm.hold_reason_id', HoldReason::idFor('CLIENT_INSTRUCTION'))
        ->call('changeStatus')
        ->assertHasNoErrors();

    expect($this->project->fresh()->status->code)->toBe(ProjectStatus::ON_HOLD);
});

test('the delivery status of a service is set from the overview', function () {
    $line = $this->project->services()->firstOrFail();

    Livewire::actingAs($this->pm)->test(Show::class, ['project' => $this->project])->call('setServiceStatus', $line->id, 'DELIVERED');

    expect($line->fresh()->status->code)->toBe('DELIVERED');
});

test('the contract tab creates, schedules and signs a contract', function () {
    $component = Livewire::actingAs($this->pm)->test(ContractTab::class, ['project' => $this->project])
        ->set('contractForm.contract_number', 'AGR-9')
        ->call('saveContract')
        ->assertHasNoErrors()
        ->call('sign')
        ->assertHasErrors('schedule');

    $component->call('editSchedule')
        ->set('scheduleLines.0.milestone_name', 'Advance')
        ->set('scheduleLines.0.schedule_trigger_id', ScheduleTrigger::idFor('MANUAL'))
        ->set('scheduleLines.0.amount', '1,00,000')
        ->call('addScheduleLine')
        ->set('scheduleLines.1.milestone_name', 'After approval')
        ->set('scheduleLines.1.percent', '80')
        ->call('saveSchedule')
        ->assertHasNoErrors()
        ->call('sign')
        ->assertHasNoErrors();

    expect($this->project->fresh()->contract->status->code)->toBe('SIGNED')
        ->and($this->project->schedules()->sum('amount'))->toEqual(500000);
});

test('schedule errors land on the schedule lines', function () {
    Livewire::actingAs($this->pm)->test(ContractTab::class, ['project' => $this->project])
        ->call('editSchedule')
        ->set('scheduleLines.0.milestone_name', '')
        ->call('saveSchedule')
        ->assertHasErrors(['scheduleLines.0.milestone_name', 'scheduleLines.0.amount']);
});

test('the amendment form saves a draft from the current services', function () {
    ProjectContract::factory()->signed()->create(['project_id' => $this->project->id, 'deed_amount' => 500000]);

    Livewire::actingAs($this->pm)->test(AmendmentForm::class, ['project' => $this->project])
        ->assertCount('services', 1)
        ->set('reason', 'Extra floor')
        ->call('addService')
        ->set('services.1.service_id', Service::factory()->create()->id)
        ->set('services.1.rate', '50000')
        ->assertSee('5,50,000.00')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->project->contract->amendments()->first()->lines()->count())->toBe(2);
});

test('the team tab adds and releases members', function () {
    $employee = Employee::factory()->create();

    $component = Livewire::actingAs($this->pm)->test(TeamTab::class, ['project' => $this->project])
        ->set('memberForm.employee_id', $employee->id)
        ->set('memberForm.project_role_id', ProjectRole::idFor('ARCHITECT'))
        ->call('addMember')
        ->assertHasNoErrors();

    $member = $this->project->activeTeam()->where('employee_id', $employee->id)->firstOrFail();
    $component->call('confirmRelease', $member->id)->call('release')->assertHasNoErrors();

    expect($member->fresh()->is_active)->toBeFalse();
});

test('the tasks tab moves a task on the board and refuses bad moves', function () {
    $task = Task::factory()->onProject($this->project)->assignedTo(Employee::query()->findOrFail($this->pm->employee_id))->create();

    $component = Livewire::actingAs($this->pm)->test(TasksTab::class, ['project' => $this->project])->set('view', 'board');

    $component->call('moveTask', (string) $task->id, 0, (string) TaskStatus::idFor('IN_PROGRESS'));
    expect($task->fresh()->status->code)->toBe(TaskStatus::IN_PROGRESS);

    $component->call('moveTask', (string) $task->id, 0, (string) TaskStatus::idFor('TODO'))->assertDispatched('toast');
    expect($task->fresh()->status->code)->toBe(TaskStatus::IN_PROGRESS);
});

test('the tasks tab applies a template and shows the timeline', function () {
    Livewire::actingAs($this->pm)->test(TasksTab::class, ['project' => $this->project])
        ->set('templateId', TaskTemplate::query()->firstOrFail()->id)
        ->call('applyTemplate')
        ->assertHasNoErrors()
        ->set('view', 'timeline')
        ->assertSee('Site survey');

    expect($this->project->tasks()->count())->toBe(12);
});

test('the project sheet prints', function () {
    $this->actingAs($this->pm)->get(route('projects.projects.print', $this->project))->assertOk()->assertSee($this->project->project_number)->assertSee('Contract value');
});

test('the project page opens in the detail modal with its tab', function () {
    Livewire::actingAs($this->pm)->test(DetailModal::class)
        ->call('show', route('projects.projects.show', [$this->project, 'tab' => 'team']))
        ->assertOk()
        ->assertSeeLivewire(Show::class)
        ->assertSeeLivewire(TeamTab::class)
        ->assertSeeInOrder([$this->project->project_number, $this->project->name, 'Project']);
});
