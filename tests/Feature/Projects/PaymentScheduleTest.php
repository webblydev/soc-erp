<?php

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Actions\ChangeProjectStatus;
use App\Modules\Projects\Actions\MarkMilestoneDue;
use App\Modules\Projects\Actions\SavePaymentSchedule;
use App\Modules\Projects\Events\TaskCompleted;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\PaymentSchedule;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Models\ProjectPhase;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Models\ScheduleTrigger;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Notifications\MilestoneDue;
use App\Modules\Projects\Services\ScheduleTriggers;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->pm = staffUser('project_manager');
    $this->accountant = User::factory()->create();
    $this->accountant->syncRoles(['accountant']);
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create(['contract_value' => 500000]);
});

function scheduleCode(PaymentSchedule $line): string
{
    return $line->fresh()->status->code;
}

test('percent and amount stay in sync against the contract value', function () {
    app(SavePaymentSchedule::class)->handle($this->pm, $this->project, [
        ['milestone_name' => 'Advance', 'schedule_trigger_id' => ScheduleTrigger::idFor('MANUAL'), 'percent' => '12.5'],
        ['milestone_name' => 'Rest', 'schedule_trigger_id' => ScheduleTrigger::idFor('MANUAL'), 'amount' => '437500', 'percent' => '99'],
    ]);

    expect($this->project->schedules()->get(['amount', 'percent'])->toArray())->toBe([
        ['amount' => '62500.00', 'percent' => '12.5000'],
        ['amount' => '437500.00', 'percent' => '87.5000'],
    ]);
});

test('trigger references must belong to the project', function () {
    $foreignTask = Task::factory()->create();

    expectValidationError(fn () => app(SavePaymentSchedule::class)->handle($this->pm, $this->project, [
        ['milestone_name' => 'On task', 'schedule_trigger_id' => ScheduleTrigger::idFor('TASK'), 'trigger_ref_id' => $foreignTask->id, 'amount' => '1000'],
    ]), 'schedule.0.trigger_ref_id');
    expectValidationError(fn () => app(SavePaymentSchedule::class)->handle($this->pm, $this->project, [
        ['milestone_name' => 'On date', 'schedule_trigger_id' => ScheduleTrigger::idFor('DATE'), 'amount' => '1000'],
    ]), 'schedule.0.due_date');
});

test('a DATE milestone in the past becomes due on save and notifies PM and accounts', function () {
    app(SavePaymentSchedule::class)->handle($this->pm, $this->project, [
        ['milestone_name' => 'Advance', 'schedule_trigger_id' => ScheduleTrigger::idFor('DATE'), 'due_date' => today()->toDateString(), 'amount' => '100000'],
        ['milestone_name' => 'Later', 'schedule_trigger_id' => ScheduleTrigger::idFor('DATE'), 'due_date' => today()->addMonth()->toDateString(), 'amount' => '400000'],
    ]);

    $lines = $this->project->schedules()->get();
    expect(scheduleCode($lines[0]))->toBe(ScheduleStatus::DUE)->and(scheduleCode($lines[1]))->toBe(ScheduleStatus::PENDING);
    Notification::assertSentTo([$this->pm, $this->accountant], MilestoneDue::class);
});

test('a PHASE milestone becomes due when the project reaches the phase', function () {
    $line = PaymentSchedule::factory()->triggeredBy('PHASE', ProjectPhase::idFor('CONSTRUCTION'))->create(['project_id' => $this->project->id]);

    app(ChangeProjectStatus::class)->handle($this->pm, $this->project, ['project_phase_id' => ProjectPhase::idFor('APPROVAL')]);
    expect(scheduleCode($line))->toBe(ScheduleStatus::PENDING);

    app(ChangeProjectStatus::class)->handle($this->pm, $this->project->fresh(), ['project_phase_id' => ProjectPhase::idFor('FINISHING')]);
    expect(scheduleCode($line))->toBe(ScheduleStatus::DUE);
});

test('APPROVAL and TASK milestones follow their approval and task', function () {
    $approval = ProjectApproval::factory()->create(['project_id' => $this->project->id]);
    $task = Task::factory()->onProject($this->project)->create();
    $onApproval = PaymentSchedule::factory()->triggeredBy('APPROVAL', $approval->id)->create(['project_id' => $this->project->id]);
    $onTask = PaymentSchedule::factory()->triggeredBy('TASK', $task->id)->create(['project_id' => $this->project->id]);

    $approval->forceFill(['approval_status_id' => ApprovalStatus::idFor('APPROVED')])->save();
    app(ScheduleTriggers::class)->evaluate($this->project->fresh());
    expect(scheduleCode($onApproval))->toBe(ScheduleStatus::DUE)->and(scheduleCode($onTask))->toBe(ScheduleStatus::PENDING);

    $task->forceFill(['task_status_id' => TaskStatus::idFor('DONE')])->save();
    TaskCompleted::dispatch($task->fresh());
    expect(scheduleCode($onTask))->toBe(ScheduleStatus::DUE);
});

test('a manual milestone is marked due by hand', function () {
    $line = PaymentSchedule::factory()->create(['project_id' => $this->project->id]);

    app(MarkMilestoneDue::class)->handle($this->pm, $line);

    expect(scheduleCode($line))->toBe(ScheduleStatus::DUE)->and($line->fresh()->due_at)->not->toBeNull();
    expectValidationError(fn () => app(MarkMilestoneDue::class)->handle($this->pm, $line->fresh()), 'schedule');
});

test('milestones of closed projects are not triggered', function () {
    $this->project->forceFill(['project_status_id' => ProjectStatus::idFor('CANCELLED')])->save();
    $line = PaymentSchedule::factory()->triggeredBy('DATE')->create(['project_id' => $this->project->id, 'due_date' => today()->subDay()]);

    app(ScheduleTriggers::class)->evaluate($this->project->fresh());

    expect(scheduleCode($line))->toBe(ScheduleStatus::PENDING);
});
