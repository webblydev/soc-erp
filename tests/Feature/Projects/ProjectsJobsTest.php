<?php

use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Jobs\ArchiveDoneTasks;
use App\Modules\Projects\Jobs\EvaluateScheduleTriggers;
use App\Modules\Projects\Jobs\NotifyOverdueApprovals;
use App\Modules\Projects\Jobs\SendTaskAlerts;
use App\Modules\Projects\Models\PaymentSchedule;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Notifications\ApprovalOverdue;
use App\Modules\Projects\Notifications\MilestoneDue;
use App\Modules\Projects\Notifications\TaskDueTomorrow;
use App\Modules\Projects\Notifications\TasksOverdue;
use App\Modules\Projects\Services\ScheduleTriggers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();
    Cache::flush();

    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
    $this->engineerEmployee = Employee::query()->findOrFail($this->engineer->employee_id);
});

test('the trigger job makes dated milestones due', function () {
    $line = PaymentSchedule::factory()->triggeredBy('DATE')->create(['project_id' => $this->project->id, 'due_date' => today()->subDay()]);

    (new EvaluateScheduleTriggers)->handle(app(ScheduleTriggers::class));

    expect($line->fresh()->status->code)->toBe(ScheduleStatus::DUE);
    Notification::assertSentTo($this->pm, MilestoneDue::class);
});

test('task alerts send due tomorrow per task and one overdue summary per user, once a day', function () {
    $tomorrow = Task::factory()->onProject($this->project)->assignedTo($this->engineerEmployee)->create(['due_date' => today()->addDay()]);
    Task::factory()->onProject($this->project)->assignedTo($this->engineerEmployee)->count(2)->create(['due_date' => today()->subDays(2)]);
    Task::factory()->onProject($this->project)->assignedTo($this->engineerEmployee)->withStatus(TaskStatus::DONE)->create(['due_date' => today()->subDays(2)]);

    (new SendTaskAlerts)->handle();
    (new SendTaskAlerts)->handle();

    Notification::assertSentToTimes($this->engineer, TaskDueTomorrow::class, 1);
    Notification::assertSentTo($this->engineer, TasksOverdue::class, fn (TasksOverdue $n): bool => $n->assigned === 2 && $n->managed === 0);
    Notification::assertSentTo($this->pm, TasksOverdue::class, fn (TasksOverdue $n): bool => $n->assigned === 0 && $n->managed === 2);
    Notification::assertSentToTimes($this->pm, TasksOverdue::class, 1);
    expect($tomorrow->fresh())->not->toBeNull();
});

test('overdue approvals are notified once per spell', function () {
    $approval = ProjectApproval::factory()->create(['project_id' => $this->project->id, 'responsible_employee_id' => $this->engineerEmployee->id, 'expected_on' => today()->subDay()]);
    ProjectApproval::factory()->create(['project_id' => $this->project->id, 'expected_on' => today()->addDay()]);

    (new NotifyOverdueApprovals)->handle();
    (new NotifyOverdueApprovals)->handle();

    Notification::assertSentToTimes($this->engineer, ApprovalOverdue::class, 1);
    Notification::assertSentToTimes($this->pm, ApprovalOverdue::class, 1);
    expect($approval->fresh()->overdue_notified_at)->not->toBeNull();
});

test('done tasks are archived after the configured days', function () {
    $old = Task::factory()->withStatus(TaskStatus::DONE)->create(['completed_at' => now()->subDays(31)]);
    $recent = Task::factory()->withStatus(TaskStatus::DONE)->create(['completed_at' => now()->subDays(5)]);
    $open = Task::factory()->create();

    (new ArchiveDoneTasks)->handle();

    expect($old->fresh()->archived_at)->not->toBeNull()
        ->and($recent->fresh()->archived_at)->toBeNull()
        ->and($open->fresh()->archived_at)->toBeNull();
});
