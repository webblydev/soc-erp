<?php

use App\Modules\Estimation\Actions\AddFinding;
use App\Modules\Estimation\Actions\ChangeFindingStatus;
use App\Modules\Estimation\Actions\CloseInspection;
use App\Modules\Estimation\Actions\DeleteInspection;
use App\Modules\Estimation\Actions\SaveInspection;
use App\Modules\Estimation\Actions\SubmitInspection;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Estimation\Notifications\FindingAssignedToYou;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Modules\Projects\Models\ProjectStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    Notification::fake();
    Storage::fake(config('foundation.attachments.disk'));
    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->contractorEngineer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create(['site_address' => 'Satarkul, Badda']);
    foreach ([$this->engineer, $this->contractorEngineer] as $user) {
        ProjectEmployee::factory()->create(['project_id' => $this->project->id, 'employee_id' => $user->employee_id]);
    }
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function findingInput(array $overrides = []): array
{
    return [
        'location' => '1st floor roof', 'description' => 'Main rod short in slab', 'finding_severity_id' => FindingSeverity::idFor(FindingSeverity::MEDIUM),
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inspectionInput(array $overrides = []): array
{
    return [
        'inspection_type_id' => InspectionType::idFor(InspectionType::WEEKLY),
        'inspection_date' => today()->toDateString(),
        'start_time' => '09:30', 'end_time' => '12:00',
        'contractor_name' => 'Md. Hasan', 'permittee_name' => 'Md. Foysal Ahmed',
        'field_office_phone' => '01716-123456',
        'findings' => [findingInput()],
        ...$overrides,
    ];
}

function seriousFinding(array $overrides = []): array
{
    return findingInput([
        'finding_severity_id' => FindingSeverity::idFor(FindingSeverity::HIGH), 'responsible_type' => 'employee',
        'responsible_id' => test()->engineer->employee_id, 'due_date' => today()->addWeek()->toDateString(), ...$overrides,
    ]);
}

test('a new inspection gets a number, defaults and its findings', function () {
    $inspection = app(SaveInspection::class)->handle($this->engineer, inspectionInput(), project: $this->project);

    expect($inspection)
        ->inspection_number->toStartWith('SI-')
        ->project_engineer_id->toBe($this->engineer->employee_id)
        ->site_address->toBe('Satarkul, Badda')
        ->field_office_phone->toBe('01716123456')
        ->start_time->toStartWith('09:30')
        ->and($inspection->status->code)->toBe(InspectionStatus::DRAFT)
        ->and($inspection->findings)->toHaveCount(1)
        ->and($inspection->findings->first()->status->code)->toBe(FindingStatus::OPEN)
        ->and($inspection->findings->first()->project_id)->toBe($this->project->id);
});

test('the end time must follow the start time and the date cannot be in the future', function (array $overrides, string $field) {
    expect(fn () => app(SaveInspection::class)->handle($this->engineer, inspectionInput($overrides), project: $this->project))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));
})->with([
    'end before start' => [['end_time' => '08:00'], 'end_time'],
    'future date' => [['inspection_date' => today()->addDay()->toDateString()], 'inspection_date'],
]);

test('high and critical findings need a responsible and a due date (ES-BR-11)', function () {
    $input = inspectionInput(['findings' => [findingInput(['finding_severity_id' => FindingSeverity::idFor(FindingSeverity::CRITICAL)])]]);

    expect(fn () => app(SaveInspection::class)->handle($this->engineer, $input, project: $this->project))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKeys(['findings.0.responsible_type', 'findings.0.due_date']));

    $inspection = app(SaveInspection::class)->handle($this->engineer, inspectionInput(['findings' => [seriousFinding()]]), project: $this->project);

    expect($inspection->findings->first()->responsibleEmployeeId())->toBe($this->engineer->employee_id);
});

test('a customer responsibility points at the project customer', function () {
    $inspection = app(SaveInspection::class)->handle($this->engineer, inspectionInput(['findings' => [findingInput(['responsible_type' => 'customer'])]]), project: $this->project);

    expect($inspection->findings->first()->responsible_id)->toBe($this->project->customer_id);
});

test('saving a draft replaces its findings', function () {
    $inspection = app(SaveInspection::class)->handle($this->engineer, inspectionInput(['findings' => [findingInput(), findingInput(['location' => 'Stair'])]]), project: $this->project);
    $kept = $inspection->findings->first();

    $inspection = app(SaveInspection::class)->handle($this->engineer, inspectionInput(['findings' => [['id' => $kept->id, ...findingInput(['description' => 'Rod lapping'])]]]), $inspection);

    expect($inspection->findings->pluck('id')->all())->toBe([$kept->id])
        ->and($inspection->findings->first()->description)->toBe('Rod lapping');
});

test('submitting tells responsible employees about their findings', function () {
    $inspection = app(SaveInspection::class)->handle($this->pm, inspectionInput(['findings' => [seriousFinding(), findingInput()]]), project: $this->project);

    app(SubmitInspection::class)->handle($this->pm, $inspection);

    expect($inspection->fresh()->status->code)->toBe(InspectionStatus::SUBMITTED);
    Notification::assertSentTo($this->engineer, FindingAssignedToYou::class);
    Notification::assertCount(1);
});

test('a submitted inspection keeps its findings and takes new ones through AddFinding', function () {
    $inspection = app(SaveInspection::class)->handle($this->pm, inspectionInput(), project: $this->project);
    app(SubmitInspection::class)->handle($this->pm, $inspection);

    $inspection = app(SaveInspection::class)->handle($this->pm, inspectionInput(['weather' => 'Rain', 'findings' => []]), $inspection->fresh());

    expect($inspection->weather)->toBe('Rain')->and($inspection->findings)->toHaveCount(1);

    app(AddFinding::class)->handle($this->pm, $inspection, seriousFinding());

    expect($inspection->findings()->count())->toBe(2);
    Notification::assertSentTo($this->engineer, FindingAssignedToYou::class);
});

test('an inspection cannot close with open high or critical findings (ES-BR-12)', function () {
    $inspection = app(SaveInspection::class)->handle($this->pm, inspectionInput(['findings' => [seriousFinding(), findingInput()]]), project: $this->project);
    app(SubmitInspection::class)->handle($this->pm, $inspection);

    expect(fn () => app(CloseInspection::class)->handle($this->pm, $inspection->fresh()))->toThrow(ValidationException::class);

    $serious = $inspection->findings()->serious()->firstOrFail();
    app(ChangeFindingStatus::class)->handle($this->engineer, $serious, FindingStatus::RESOLVED, 'Extra rods placed');
    app(CloseInspection::class)->handle($this->pm, $inspection->fresh());

    expect($inspection->fresh()->status->code)->toBe(InspectionStatus::CLOSED);
});

test('closing the last open finding closes the inspection', function () {
    $inspection = app(SaveInspection::class)->handle($this->pm, inspectionInput(['findings' => [seriousFinding()]]), project: $this->project);
    app(SubmitInspection::class)->handle($this->pm, $inspection);
    $finding = $inspection->findings()->firstOrFail();

    app(ChangeFindingStatus::class)->handle($this->engineer, $finding, FindingStatus::IN_PROGRESS);
    app(ChangeFindingStatus::class)->handle($this->engineer, $finding->fresh(), FindingStatus::RESOLVED, 'Fixed', UploadedFile::fake()->image('after.jpg'));

    expect($finding->fresh())
        ->closed_on->toDateString()->toBe(today()->toDateString())
        ->closed_by->toBe($this->engineer->id)
        ->closure_note->toBe('Fixed')
        ->and($finding->attachments()->first()->title)->toBe('After: 1st floor roof')
        ->and($inspection->fresh()->status->code)->toBe(InspectionStatus::CLOSED);
});

test('only the responsible employee, the PM or management move a finding; only leads reopen it', function () {
    $inspection = app(SaveInspection::class)->handle($this->pm, inspectionInput(['findings' => [seriousFinding()]]), project: $this->project);
    app(SubmitInspection::class)->handle($this->pm, $inspection);
    $finding = $inspection->findings()->firstOrFail();

    expect(fn () => app(ChangeFindingStatus::class)->handle($this->contractorEngineer, $finding, FindingStatus::IN_PROGRESS))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeFindingStatus::class)->handle($this->engineer, $finding, FindingStatus::RESOLVED))->toThrow(ValidationException::class);

    app(ChangeFindingStatus::class)->handle($this->engineer, $finding, FindingStatus::ACCEPTED, 'Client accepts as is');

    expect(fn () => app(ChangeFindingStatus::class)->handle($this->engineer, $finding->fresh(), FindingStatus::OPEN))->toThrow(AuthorizationException::class);

    app(ChangeFindingStatus::class)->handle($this->pm, $finding->fresh(), FindingStatus::OPEN);

    expect($finding->fresh())->closed_on->toBeNull()->closure_note->toBeNull()
        ->and($finding->fresh()->status->code)->toBe(FindingStatus::OPEN);
});

test('findings of a draft inspection are not followed up yet', function () {
    $finding = SiteInspectionFinding::factory()->forInspection(SiteInspection::factory()->onProject($this->project)->create())->create();

    expect(fn () => app(ChangeFindingStatus::class)->handle($this->pm, $finding, FindingStatus::IN_PROGRESS))->toThrow(ValidationException::class);
});

test('a completed project takes only handover and snag inspections (ES-BR-13)', function () {
    $project = Project::factory()->withStatus(ProjectStatus::COMPLETED)->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();

    expect(fn () => app(SaveInspection::class)->handle($this->pm, inspectionInput(), project: $project))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('inspection_type_id'));

    $snag = app(SaveInspection::class)->handle($this->pm, inspectionInput(['inspection_type_id' => InspectionType::idFor(InspectionType::SNAG)]), project: $project);

    expect($snag->exists)->toBeTrue();
});

test('only a draft inspection can be deleted', function () {
    $inspection = app(SaveInspection::class)->handle($this->pm, inspectionInput(), project: $this->project);
    app(SubmitInspection::class)->handle($this->pm, $inspection);

    expect(fn () => app(DeleteInspection::class)->handle($this->pm, $inspection->fresh()))->toThrow(ValidationException::class);

    $draft = app(SaveInspection::class)->handle($this->pm, inspectionInput(), project: $this->project);
    app(DeleteInspection::class)->handle($this->pm, $draft);

    expect($draft->fresh()->trashed())->toBeTrue();
});
