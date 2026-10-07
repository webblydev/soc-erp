<?php

use App\Modules\Estimation\Actions\ApproveEstimate;
use App\Modules\Estimation\Actions\DeleteMeasurement;
use App\Modules\Estimation\Actions\RecordMeasurement;
use App\Modules\Estimation\Actions\RejectMeasurements;
use App\Modules\Estimation\Actions\ReviseEstimate;
use App\Modules\Estimation\Actions\SubmitEstimate;
use App\Modules\Estimation\Actions\UnverifyMeasurement;
use App\Modules\Estimation\Actions\UpdateMeasurement;
use App\Modules\Estimation\Actions\VerifyMeasurements;
use App\Modules\Estimation\Events\MeasurementVerified;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Foundation\Models\Setting;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Modules\Projects\Models\ProjectStatus;
use App\Support\Facades\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    Notification::fake();
    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
    ProjectEmployee::factory()->create(['project_id' => $this->project->id, 'employee_id' => $this->engineer->employee_id]);
    $this->estimate = Estimate::factory()->onProject($this->project)->approved()->create();
    $this->line = EstimateLine::factory()->quantity('100', '50')->create(['estimate_id' => $this->estimate->id, 'description' => 'Brick work']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mbInput(string $quantity, array $overrides = []): array
{
    return ['estimate_line_id' => test()->line->id, 'quantity' => $quantity, 'mb_book_no' => '3', 'mb_page_no' => '14', 'location' => 'GF', ...$overrides];
}

function setMbSetting(string $key, mixed $value): void
{
    Setting::query()->where('group', 'site')->where('key', $key)->update(['value' => $value]);
    Settings::flush();
}

test('an entry against a BOQ line takes its item, unit and rate', function () {
    $entry = app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('40'));

    expect($entry)
        ->mb_number->toStartWith('MB-')
        ->description->toBe('Brick work')
        ->unit_id->toBe($this->line->unit_id)
        ->rate->toBe('50.0000')
        ->quantity->toBe('40.0000')
        ->amount->toBe('2000.00')
        ->achievement_pct->toBe('40.0000')
        ->measured_by->toBe($this->engineer->employee_id)
        ->and($entry->status->code)->toBe(MbStatus::RECORDED)
        ->and($entry->direction->code)->toBe('CUSTOMER');
});

test('the BOQ line must be on an approved estimate of the same project', function () {
    $draftLine = EstimateLine::factory()->create(['estimate_id' => Estimate::factory()->onProject($this->project)->create()->id]);
    $otherLine = EstimateLine::factory()->create(['estimate_id' => Estimate::factory()->approved()->create()->id]);

    foreach ([$draftLine, $otherLine] as $line) {
        expect(fn () => app(RecordMeasurement::class)->handle($this->pm, $this->project, mbInput('5', ['estimate_line_id' => $line->id])))
            ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('estimate_line_id'));
    }
});

test('only a project manager may change the default rate', function () {
    expect(fn () => app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('10', ['rate' => '55'])))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('rate'));

    $entry = app(RecordMeasurement::class)->handle($this->pm, $this->project, mbInput('10', ['rate' => '55']));

    expect($entry->rate)->toBe('55.0000')->and($entry->amount)->toBe('550.00');
});

test('going over the BOQ warns within the margin and blocks beyond it (ES-AC-04)', function () {
    app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('100'));

    $action = app(RecordMeasurement::class);
    $action->handle($this->engineer, $this->project, mbInput('5'));

    expect($action->warnings)->toHaveCount(1)->and($action->warnings[0])->toContain('105.0000%');

    expect(fn () => app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('10')))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors()['quantity'][0])
            ->toContain('115.0000 of 100.0000 (115.0000%)')->toContain('Previously measured: 105.0000')->toContain('this entry: 10.0000'));
});

test('rejected entries do not count towards the BOQ quantity', function () {
    $entry = app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('100'));
    app(RejectMeasurements::class)->handle($this->pm, [$entry->id], 'Wrong floor');

    $second = app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('100'));

    expect($second->achievement_pct)->toBe('100.0000');
});

test('the cumulative quantity follows the BOQ item across revisions', function () {
    app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('80'));

    $revision = app(ReviseEstimate::class)->handle($this->pm, $this->estimate, 'More work');
    app(SubmitEstimate::class)->handle($this->pm, $revision);
    app(ApproveEstimate::class)->handle($this->pm, $revision->fresh());
    $newLine = $revision->lines()->firstOrFail();

    expect(fn () => app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('40', ['estimate_line_id' => $newLine->id])))
        ->toThrow(ValidationException::class);

    $entry = app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('20', ['estimate_line_id' => $newLine->id]));

    expect($entry->achievement_pct)->toBe('100.0000');
});

test('an entry without a BOQ line needs a rate and a quantity', function () {
    expect(fn () => app(RecordMeasurement::class)->handle($this->engineer, $this->project, ['description' => 'Extra work', 'unit_id' => unitId('sft'), 'quantity' => '12']))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('rate'));

    $entry = app(RecordMeasurement::class)->handle($this->engineer, $this->project, [
        'description' => 'Extra work', 'unit_id' => unitId('sft'), 'measurement_formula' => 'nos_l_w', 'nos' => '2', 'length' => '12', 'width' => '6', 'rate' => '45',
    ]);

    expect($entry)->quantity->toBe('144.0000')->amount->toBe('6480.00')->achievement_pct->toBeNull();
});

test('nobody verifies their own entry (ES-AC-05)', function () {
    $entry = app(RecordMeasurement::class)->handle($this->pm, $this->project, mbInput('10'));
    $management = staffUser('management');

    expect(fn () => app(VerifyMeasurements::class)->handle($this->pm, [$entry->id]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors()['entries'][0])->toContain('another person'));

    Event::fake([MeasurementVerified::class]);
    app(VerifyMeasurements::class)->handle($management, [$entry->id]);

    expect($entry->fresh())->verified_by->toBe($management->id)->and($entry->fresh()->status->code)->toBe(MbStatus::VERIFIED);
    Event::assertDispatched(MeasurementVerified::class);
});

test('the PM verifies an engineer\'s entries in bulk, all or none', function () {
    $first = app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('10'));
    $second = app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('10'));
    $own = app(RecordMeasurement::class)->handle($this->pm, $this->project, mbInput('10'));

    expect(fn () => app(VerifyMeasurements::class)->handle($this->pm, [$first->id, $own->id]))->toThrow(ValidationException::class)
        ->and($first->fresh()->status->code)->toBe(MbStatus::RECORDED)
        ->and(app(VerifyMeasurements::class)->handle($this->pm, [$first->id, $second->id]))->toBe(2);
});

test('with verification off a new entry is saved verified', function () {
    setMbSetting('mb_requires_verification', false);

    $entry = app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('10'));

    expect($entry->status->code)->toBe(MbStatus::VERIFIED)->and($entry->verified_by)->toBe($this->engineer->id);
});

test('a rejected entry is edited back to recorded', function () {
    $entry = app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('10'));
    app(RejectMeasurements::class)->handle($this->pm, [$entry->id], 'Check the length');

    $entry = app(UpdateMeasurement::class)->handle($this->engineer, $entry->fresh(), mbInput('12'));

    expect($entry->status->code)->toBe(MbStatus::RECORDED)
        ->and($entry->rejection_reason)->toBeNull()
        ->and($entry->quantity)->toBe('12.0000');
});

test('verified and billed entries are locked', function () {
    $entry = MeasurementEntry::factory()->forLine($this->line)->withStatus(MbStatus::VERIFIED)->create(['running_bill_line_id' => 7]);

    expect(fn () => app(UpdateMeasurement::class)->handle($this->pm, $entry, mbInput('5')))->toThrow(ValidationException::class)
        ->and(fn () => app(DeleteMeasurement::class)->handle($this->pm, $entry))->toThrow(ValidationException::class)
        ->and(fn () => app(UnverifyMeasurement::class)->handle($this->pm, $entry))->toThrow(ValidationException::class);

    $entry->forceFill(['running_bill_line_id' => null])->save();
    app(UnverifyMeasurement::class)->handle($this->pm, $entry);

    expect($entry->fresh()->status->code)->toBe(MbStatus::RECORDED);
});

test('a recorded entry can be deleted', function () {
    $entry = app(RecordMeasurement::class)->handle($this->engineer, $this->project, mbInput('10'));

    app(DeleteMeasurement::class)->handle($this->engineer, $entry);

    expect($entry->fresh()->trashed())->toBeTrue();
});

test('closed projects take no new measurements (ES-BR-13)', function () {
    $project = Project::factory()->withStatus(ProjectStatus::COMPLETED)->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();

    expect(fn () => app(RecordMeasurement::class)->handle($this->pm, $project, mbInput('5')))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('project'));
});
