<?php

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Actions\AddApprovalEvent;
use App\Modules\Projects\Actions\SaveApproval;
use App\Modules\Projects\Actions\ToggleApprovalChecklistItem;
use App\Modules\Projects\Events\ApprovalStatusChanged;
use App\Modules\Projects\Models\ApprovalAuthority;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\ApprovalType;
use App\Modules\Projects\Models\PaymentSchedule;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Notifications\ApprovalStatusUpdated;
use App\Modules\Projects\Notifications\MilestoneDue;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();
    Storage::fake(config('foundation.attachments.disk'));

    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->accountManager = staffUser('sales_executive');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))
        ->forCustomer(Customer::factory()->create(['account_manager_user_id' => $this->accountManager->id]))
        ->create(['supervisor_id' => $this->engineer->employee_id]);
});

function newRajukApproval(array $overrides = []): ProjectApproval
{
    return app(SaveApproval::class)->handle(test()->engineer, test()->project, [
        'approval_authority_id' => ApprovalAuthority::idFor('RAJUK'), 'approval_type_id' => ApprovalType::idFor('BP'),
        'reference_no' => 'RAJUK/BP/2026/117', 'responsible_employee_id' => test()->engineer->employee_id, ...$overrides,
    ]);
}

function approvalEvent(ProjectApproval $approval, string $code, ?UploadedFile $file = null, ?User $actor = null): void
{
    app(AddApprovalEvent::class)->handle($actor ?? test()->engineer, $approval->fresh(), ['approval_status_id' => ApprovalStatus::idFor($code), 'event_date' => today()->toDateString(), 'note' => $code], $file);
}

test('a new approval starts preparing with the type checklist', function () {
    $approval = newRajukApproval();

    expect($approval->status->code)->toBe(ApprovalStatus::PREPARING)
        ->and($approval->checklist()->pluck('title')->all())->toContain('Land deed', 'Soil report')
        ->and($approval->checklist()->count())->toBe(9);
});

test('the expected date defaults to submitted + typical days (PRJ-BR-15)', function () {
    $approval = newRajukApproval(['submitted_on' => '2026-10-01']);

    expect($approval->expected_on->toDateString())->toBe('2026-12-30');
});

test('events move the status and fill the submitted date', function () {
    Event::fake([ApprovalStatusChanged::class]);
    $approval = newRajukApproval();

    approvalEvent($approval, 'SUBMITTED');

    $approval = $approval->fresh();
    expect($approval->status->code)->toBe(ApprovalStatus::SUBMITTED)
        ->and($approval->submitted_on->toDateString())->toBe(today()->toDateString())
        ->and($approval->expected_on->toDateString())->toBe(today()->addDays(90)->toDateString())
        ->and($approval->events()->count())->toBe(1);
    Event::assertDispatched(ApprovalStatusChanged::class);
    Notification::assertSentTo([$this->pm, $this->accountManager], ApprovalStatusUpdated::class);
});

test('APPROVED needs the approval letter (PRJ-BR-16) and is final', function () {
    $approval = newRajukApproval();
    approvalEvent($approval, 'SUBMITTED');

    expectValidationError(fn () => approvalEvent($approval, 'APPROVED'), 'file');

    approvalEvent($approval, 'APPROVED', UploadedFile::fake()->create('letter.pdf', 50, 'application/pdf'));

    $approval = $approval->fresh();
    expect($approval->status->code)->toBe(ApprovalStatus::APPROVED)
        ->and($approval->approved_on->toDateString())->toBe(today()->toDateString())
        ->and($approval->events()->first()->attachment_id)->not->toBeNull()
        ->and($approval->attachments()->count())->toBe(1);

    expectValidationError(fn () => approvalEvent($approval, 'QUERY'), 'approval_status_id');
});

test('approving the RAJUK permit makes its milestone due and tells PM and accounts (PRJ-AC-04)', function () {
    $accountant = User::factory()->create();
    $accountant->syncRoles(['accountant']);
    $approval = newRajukApproval();
    $milestone = PaymentSchedule::factory()->triggeredBy('APPROVAL', $approval->id)->create(['project_id' => $this->project->id, 'milestone_name' => 'After approval']);

    approvalEvent($approval, 'SUBMITTED');
    expect($milestone->fresh()->status->code)->toBe(ScheduleStatus::PENDING);

    approvalEvent($approval, 'APPROVED', UploadedFile::fake()->create('letter.pdf', 50, 'application/pdf'));

    expect($milestone->fresh()->status->code)->toBe(ScheduleStatus::DUE);
    Notification::assertSentTo([$this->pm, $accountant], MilestoneDue::class);
});

test('overdue means pending past the expected date', function () {
    $late = newRajukApproval(['submitted_on' => today()->subDays(100)->toDateString()]);
    newRajukApproval(['submitted_on' => today()->toDateString()]);

    expect($this->project->approvals()->overdue()->pluck('id')->all())->toBe([$late->id])
        ->and($late->isOverdue())->toBeTrue()
        ->and($late->daysElapsed())->toBe(100);
});

test('the checklist is ticked on visible approvals only', function () {
    $approval = newRajukApproval();
    $item = $approval->checklist()->firstOrFail();

    app(ToggleApprovalChecklistItem::class)->handle($this->engineer, $approval, $item, true);
    expect($item->fresh())->is_done->toBeTrue()->done_at->not->toBeNull();

    expect(fn () => app(ToggleApprovalChecklistItem::class)->handle(staffUser('engineer'), $approval, $item, false))
        ->toThrow(AuthorizationException::class);
});

test('sales users can view but not manage approvals', function () {
    app(SaveApproval::class)->handle($this->accountManager, $this->project, ['approval_authority_id' => ApprovalAuthority::idFor('RAJUK'), 'approval_type_id' => ApprovalType::idFor('BP')]);
})->throws(AuthorizationException::class);
