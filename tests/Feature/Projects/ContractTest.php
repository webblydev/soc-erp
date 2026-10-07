<?php

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Models\Customer;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Actions\ApproveAmendment;
use App\Modules\Projects\Actions\CreateProject;
use App\Modules\Projects\Actions\DeleteAmendment;
use App\Modules\Projects\Actions\SaveAmendment;
use App\Modules\Projects\Actions\SaveContract;
use App\Modules\Projects\Actions\SavePaymentSchedule;
use App\Modules\Projects\Actions\SaveProjectServices;
use App\Modules\Projects\Actions\SignContract;
use App\Modules\Projects\Actions\TerminateContract;
use App\Modules\Projects\Models\ContractStatus;
use App\Modules\Projects\Models\ProjectServiceStatus;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ScheduleTrigger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->actor = staffUser('project_manager');
    $this->line = BusinessLine::factory()->create(['project_prefix' => 'SOC-BD']);
    $this->customer = Customer::factory()->create();
    $this->pm = Employee::query()->findOrFail($this->actor->employee_id);
    $this->service = Service::factory()->create();
    $this->project = app(CreateProject::class)->handle(staffUser('management'), projectInput());
    app(SaveContract::class)->handle($this->actor, $this->project, ['agreement_date' => today()->toDateString(), 'contract_number' => 'AGR-17']);
});

function manualMilestone(string $name, string $amount): array
{
    return ['milestone_name' => $name, 'schedule_trigger_id' => ScheduleTrigger::idFor('MANUAL'), 'amount' => $amount];
}

test('a draft contract follows the contract value', function () {
    $contract = $this->project->contract()->firstOrFail();

    expect($contract->deed_amount)->toBe('500000.00')->and($contract->status->code)->toBe(ContractStatus::DRAFT);

    app(SaveProjectServices::class)->handle($this->actor, $this->project, [['service_id' => $this->service->id, 'quantity' => '1', 'rate' => '600000']]);

    expect($contract->fresh()->deed_amount)->toBe('600000.00');
});

test('signing is refused when the schedule does not total the deed (PRJ-AC-02)', function () {
    app(SavePaymentSchedule::class)->handle($this->actor, $this->project, [manualMilestone('Advance on signing', '2,00,000'), manualMilestone('After design approval', '2,95,000')]);

    try {
        app(SignContract::class)->handle($this->actor, $this->project->fresh());
        $this->fail('Expected the schedule check to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['schedule'][0])->toContain('৳ 4,95,000.00')->toContain('৳ 5,00,000.00')->toContain('৳ 5,000.00');
    }

    expect($this->project->contract()->first()->status->code)->toBe(ContractStatus::DRAFT);
});

test('signing locks the services and makes an enquiry contracted (PRJ-BR-04, PRJ-BR-05)', function () {
    app(SavePaymentSchedule::class)->handle($this->actor, $this->project, [manualMilestone('Advance', '200000'), ['milestone_name' => 'Rest', 'schedule_trigger_id' => ScheduleTrigger::idFor('MANUAL'), 'percent' => '60']]);
    app(SignContract::class)->handle($this->actor, $this->project->fresh());

    $project = $this->project->fresh();
    expect($project->contract->status->code)->toBe(ContractStatus::SIGNED)
        ->and($project->status->code)->toBe(ProjectStatus::CONTRACTED)
        ->and($project->schedules()->pluck('amount')->all())->toBe(['200000.00', '300000.00'])
        ->and($project->schedules()->first()->percent)->toBe('40.0000');

    expectValidationError(fn () => app(SaveProjectServices::class)->handle($this->actor, $project, [['service_id' => $this->service->id, 'quantity' => '1', 'rate' => '1']]), 'services');
    expectValidationError(fn () => app(SavePaymentSchedule::class)->handle($this->actor, $project, [manualMilestone('All', '400000')]), 'schedule');
    expectValidationError(fn () => app(SignContract::class)->handle($this->actor, $project), 'contract');
});

test('only the terms of a signed contract can change', function () {
    app(SavePaymentSchedule::class)->handle($this->actor, $this->project, [manualMilestone('All', '500000')]);
    app(SignContract::class)->handle($this->actor, $this->project->fresh());

    app(SaveContract::class)->handle($this->actor, $this->project->fresh(), ['agreement_date' => '2020-01-01', 'contract_number' => 'CHANGED', 'terms' => 'Payment within 7 days']);

    expect($this->project->contract()->first())->contract_number->toBe('AGR-17')->terms->toBe('Payment within 7 days');
});

test('an approved amendment adding ৳50,000 updates the contract value (PRJ-AC-03)', function () {
    app(SavePaymentSchedule::class)->handle($this->actor, $this->project, [manualMilestone('All', '500000')]);
    app(SignContract::class)->handle($this->actor, $this->project->fresh());
    $project = $this->project->fresh();
    $existing = $project->services()->firstOrFail();

    $amendment = app(SaveAmendment::class)->handle($this->actor, $project, [
        'amendment_date' => today()->toDateString(), 'reason' => 'Extra floor drawings',
        'services' => [
            ['id' => $existing->id, 'service_id' => $existing->service_id, 'quantity' => '1', 'rate' => '500000'],
            ['service_id' => Service::factory()->create()->id, 'quantity' => '1', 'rate' => '50000'],
        ],
    ]);

    expect($project->fresh()->contract_value)->toBe('500000.00');
    expectValidationError(fn () => app(SaveAmendment::class)->handle($this->actor, $project, ['amendment_date' => today()->toDateString(), 'reason' => 'Second', 'services' => []]), 'amendment');

    app(ApproveAmendment::class)->handle($this->actor, $amendment);

    $project = $project->fresh();
    expect($project->contract_value)->toBe('550000.00')
        ->and($project->contract->deed_amount)->toBe('550000.00')
        ->and($project->contract->status->code)->toBe(ContractStatus::AMENDED)
        ->and($amendment->fresh())->value_change->toBe('50000.00')->new_deed_amount->toBe('550000.00')->approved_by->toBe($this->actor->id)
        ->and($project->schedules()->pluck('amount')->all())->toBe(['500000.00', '50000.00'])
        ->and($project->services()->count())->toBe(2);
});

test('lines left out of an amendment are cancelled, not removed', function () {
    app(SavePaymentSchedule::class)->handle($this->actor, $this->project, [manualMilestone('All', '500000')]);
    app(SignContract::class)->handle($this->actor, $this->project->fresh());
    $project = $this->project->fresh();

    $amendment = app(SaveAmendment::class)->handle($this->actor, $project, [
        'amendment_date' => today()->toDateString(), 'reason' => 'Scope cut',
        'services' => [['service_id' => Service::factory()->create()->id, 'quantity' => '1', 'rate' => '300000']],
    ]);
    app(ApproveAmendment::class)->handle($this->actor, $amendment);

    $project = $project->fresh();
    expect($project->contract_value)->toBe('300000.00')
        ->and($project->services()->with('status')->get()->map(fn ($line) => $line->status->code)->all())->toBe([ProjectServiceStatus::NOT_STARTED, ProjectServiceStatus::CANCELLED])
        ->and($amendment->fresh()->value_change)->toBe('-200000.00');
});

test('draft amendments can be deleted, approved ones cannot', function () {
    app(SavePaymentSchedule::class)->handle($this->actor, $this->project, [manualMilestone('All', '500000')]);
    app(SignContract::class)->handle($this->actor, $this->project->fresh());
    $input = ['amendment_date' => today()->toDateString(), 'reason' => 'x', 'services' => [['id' => $this->project->services()->first()->id, 'service_id' => $this->service->id, 'quantity' => '1', 'rate' => '500000']]];

    $draft = app(SaveAmendment::class)->handle($this->actor, $this->project->fresh(), $input);
    app(DeleteAmendment::class)->handle($this->actor, $draft);
    expect($draft->fresh()->trashed())->toBeTrue();

    $next = app(SaveAmendment::class)->handle($this->actor, $this->project->fresh(), $input);
    expect($next->amendment_no)->toBe(2);
    app(ApproveAmendment::class)->handle($this->actor, $next);

    expectValidationError(fn () => app(DeleteAmendment::class)->handle($this->actor, $next->fresh()), 'amendment');
});

test('a contract can be terminated with a reason', function () {
    expectValidationError(fn () => app(TerminateContract::class)->handle($this->actor, $this->project, []), 'reason');

    app(TerminateContract::class)->handle($this->actor, $this->project, ['reason' => 'Client cancelled']);

    expect($this->project->contract()->first())->termination_reason->toBe('Client cancelled')->terminated_at->not->toBeNull();
    expectValidationError(fn () => app(SaveContract::class)->handle($this->actor, $this->project->fresh(), ['agreement_date' => today()->toDateString()]), 'contract');
});

test('engineers cannot manage contracts', function () {
    app(SaveContract::class)->handle(staffUser('engineer'), $this->project, ['agreement_date' => today()->toDateString()]);
})->throws(AuthorizationException::class);
