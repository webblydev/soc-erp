<?php

use App\Modules\Estimation\Actions\ApproveEstimate;
use App\Modules\Estimation\Actions\DeleteEstimate;
use App\Modules\Estimation\Actions\RejectEstimate;
use App\Modules\Estimation\Actions\ReviseEstimate;
use App\Modules\Estimation\Actions\SubmitEstimate;
use App\Modules\Estimation\Events\EstimateApproved;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Notifications\EstimateAwaitingApproval;
use App\Modules\Estimation\Notifications\EstimateDecided;
use App\Modules\Foundation\Models\Setting;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Support\Facades\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    Notification::fake();
    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->management = staffUser('management');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
    ProjectEmployee::factory()->create(['project_id' => $this->project->id, 'employee_id' => $this->engineer->employee_id]);
});

function workflowEstimate(string $status = EstimateStatus::DRAFT, array $attributes = []): Estimate
{
    return Estimate::factory()->onProject(test()->project)->withStatus($status)->withLines(2)
        ->create(['prepared_by' => test()->engineer->employee_id, ...$attributes]);
}

function setApprovalLimit(int $limit): void
{
    Setting::query()->where('group', 'estimation')->where('key', 'pm_approval_limit')->update(['value' => $limit]);
    Settings::flush();
}

test('submitting moves a draft to submitted and notifies the PM', function () {
    $estimate = app(SubmitEstimate::class)->handle($this->engineer, workflowEstimate());

    expect($estimate->status->code)->toBe(EstimateStatus::SUBMITTED)
        ->and($estimate->statusHistories()->first()->status->code)->toBe(EstimateStatus::SUBMITTED);

    Notification::assertSentTo($this->pm, EstimateAwaitingApproval::class);
    Notification::assertNotSentTo($this->management, EstimateAwaitingApproval::class);
});

test('an estimate above the PM limit notifies management instead', function () {
    setApprovalLimit(5000);

    app(SubmitEstimate::class)->handle($this->engineer, workflowEstimate());

    Notification::assertSentTo($this->management, EstimateAwaitingApproval::class);
    Notification::assertNotSentTo($this->pm, EstimateAwaitingApproval::class);
});

test('an estimate without lines cannot be submitted', function () {
    $estimate = Estimate::factory()->onProject($this->project)->create(['prepared_by' => $this->engineer->employee_id]);

    expect(fn () => app(SubmitEstimate::class)->handle($this->engineer, $estimate))->toThrow(ValidationException::class);
});

test('the PM approves within the limit and the preparer hears it', function () {
    Event::fake([EstimateApproved::class]);

    $estimate = app(ApproveEstimate::class)->handle($this->pm, workflowEstimate(EstimateStatus::SUBMITTED));

    expect($estimate->fresh())
        ->status->code->toBe(EstimateStatus::APPROVED)
        ->approved_by->toBe($this->pm->id)
        ->approved_at->not->toBeNull();

    Event::assertDispatched(EstimateApproved::class);
    Notification::assertSentTo($this->engineer, EstimateDecided::class, fn (EstimateDecided $notification): bool => $notification->key() === 'estimation.estimates.approved');
});

test('the PM cannot approve above the limit but management can (ES-BR-04)', function () {
    setApprovalLimit(5000);
    $estimate = workflowEstimate(EstimateStatus::SUBMITTED);

    expect(fn () => app(ApproveEstimate::class)->handle($this->pm, $estimate))
        ->toThrow(fn (ValidationException $exception) => expect($exception->getMessage())->toContain('5,000.00'));

    app(ApproveEstimate::class)->handle($this->management, $estimate);

    expect($estimate->fresh()->status->code)->toBe(EstimateStatus::APPROVED);
});

test('engineers cannot approve', function () {
    expect(fn () => app(ApproveEstimate::class)->handle($this->engineer, workflowEstimate(EstimateStatus::SUBMITTED)))
        ->toThrow(AuthorizationException::class);
});

test('rejecting needs a note and the preparer hears it', function () {
    $estimate = workflowEstimate(EstimateStatus::SUBMITTED);

    expect(fn () => app(RejectEstimate::class)->handle($this->pm, $estimate, ' '))->toThrow(ValidationException::class);

    app(RejectEstimate::class)->handle($this->pm, $estimate, 'Rates are out of date.');

    expect($estimate->fresh())->status->code->toBe(EstimateStatus::REJECTED)->rejection_note->toBe('Rates are out of date.');
    Notification::assertSentTo($this->engineer, EstimateDecided::class, fn (EstimateDecided $notification): bool => $notification->key() === 'estimation.estimates.rejected');
});

test('revising copies everything into the next draft revision (ES-AC-02)', function () {
    $original = workflowEstimate(EstimateStatus::APPROVED, ['overhead_pct' => 10]);
    $section = $original->sections()->create(['name' => 'Substructure', 'sort_order' => 1]);
    $original->lines()->first()->update(['estimate_section_id' => $section->id]);
    $original->materialLines()->create(['material_name' => 'Cement', 'unit_id' => $original->lines()->first()->unit_id, 'estimated_qty' => 10, 'total_qty' => 10, 'amount' => 0]);

    $revision = app(ReviseEstimate::class)->handle($this->engineer, $original, 'Client added a room');

    expect($revision)
        ->estimate_number->toBe($original->estimate_number.'-R1')
        ->revision_no->toBe(1)
        ->root_estimate_id->toBe($original->id)
        ->revised_from_id->toBe($original->id)
        ->revision_purpose->toBe('Client added a room')
        ->and($revision->status->code)->toBe(EstimateStatus::DRAFT)
        ->and($revision->lines)->toHaveCount(2)
        ->and($revision->lines->pluck('origin_line_id')->all())->toBe($original->lines->pluck('id')->all())
        ->and($revision->lines->first()->section->name)->toBe('Substructure')
        ->and($revision->materialLines)->toHaveCount(1)
        ->and($revision->total_amount)->toBe('11000.00')
        ->and($original->fresh()->status->code)->toBe(EstimateStatus::APPROVED);
});

test('approving a revision supersedes the earlier approved one (ES-BR-02)', function () {
    $original = workflowEstimate(EstimateStatus::APPROVED);
    $revision = app(ReviseEstimate::class)->handle($this->engineer, $original, 'Change');
    app(SubmitEstimate::class)->handle($this->engineer, $revision);

    app(ApproveEstimate::class)->handle($this->pm, $revision->fresh());

    expect($original->fresh()->status->code)->toBe(EstimateStatus::SUPERSEDED)
        ->and($revision->fresh()->status->code)->toBe(EstimateStatus::APPROVED)
        ->and(Estimate::query()->familyOf($original)->whereHas('status', fn ($query) => $query->where('is_approved', true))->count())->toBe(1);
});

test('only the latest approved revision can be revised, once at a time', function () {
    $original = workflowEstimate(EstimateStatus::APPROVED);

    expect(fn () => app(ReviseEstimate::class)->handle($this->engineer, $original, ''))->toThrow(ValidationException::class);

    $revision = app(ReviseEstimate::class)->handle($this->engineer, $original, 'First change');

    expect(fn () => app(ReviseEstimate::class)->handle($this->engineer, $original, 'Second change'))->toThrow(ValidationException::class)
        ->and(fn () => app(ReviseEstimate::class)->handle($this->engineer, workflowEstimate(), 'Draft'))->toThrow(ValidationException::class);

    app(DeleteEstimate::class)->handle($this->pm, $revision);
    $again = app(ReviseEstimate::class)->handle($this->engineer, $original, 'Second try');

    expect($again->estimate_number)->toBe($original->estimate_number.'-R2');
});

test('only drafts and rejected estimates can be deleted, and not an original with revisions', function () {
    $approved = workflowEstimate(EstimateStatus::APPROVED);

    expect(fn () => app(DeleteEstimate::class)->handle($this->pm, $approved))->toThrow(ValidationException::class);

    $draft = workflowEstimate();
    app(DeleteEstimate::class)->handle($this->pm, $draft);

    expect($draft->fresh()->trashed())->toBeTrue();

    $original = workflowEstimate(EstimateStatus::REJECTED);
    Estimate::factory()->revisionOf($original)->create();

    expect(fn () => app(DeleteEstimate::class)->handle($this->pm, $original))->toThrow(ValidationException::class);
});
