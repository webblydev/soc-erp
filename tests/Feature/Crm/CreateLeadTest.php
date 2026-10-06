<?php

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Actions\CreateLead;
use App\Modules\Crm\Actions\UpdateLead;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Foundation\Models\AuditLog;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedCrm();
    Notification::fake();
    $this->actor = userWithPermissions('crm.leads.view_own', 'crm.leads.create', 'crm.leads.update', 'crm.activities.view_own', 'crm.activities.create');
    $this->design = Service::factory()->create();
    $this->survey = Service::factory()->create();
});

test('a lead is created with a number, NEW status, services, history and an owner', function () {
    $lead = app(CreateLead::class)->handle($this->actor, leadInput());

    expect($lead->lead_number)->toBe('L-000001')
        ->and($lead->phone)->toBe('01711000000')
        ->and($lead->lead_status_id)->toBe(LeadStatus::idFor('NEW'))
        ->and($lead->expected_value)->toBe('170000.00')
        ->and($lead->services()->count())->toBe(2)
        ->and($lead->statusHistories()->first())->from_status_id->toBeNull()->to_status_id->toBe(LeadStatus::idFor('NEW'))
        ->and($lead->assigned_to)->toBe($this->actor->id)
        ->and($lead->assignmentHistories()->count())->toBe(1);
});

test('a manual expected value is kept', function () {
    expect(app(CreateLead::class)->handle($this->actor, leadInput(['expected_value' => '500000', 'expected_value_manual' => true]))->expected_value)->toBe('500000.00');
});

test('the first follow-up is logged and sets next_follow_up_at', function () {
    $lead = app(CreateLead::class)->handle($this->actor, leadInput(['follow_up' => ['activity_type_id' => ActivityType::idFor('CALL'), 'scheduled_at' => now()->addDay()->format('Y-m-d H:i')]]));

    expect($lead->fresh()->next_follow_up_at)->not->toBeNull()->and($lead->activities()->count())->toBe(1);
});

test('CRM-BR-01, 02 and 04 are enforced', function () {
    expectValidationError(fn () => app(CreateLead::class)->handle($this->actor, leadInput(['services' => []])), 'services');
    expectValidationError(fn () => app(CreateLead::class)->handle($this->actor, leadInput(['lead_date' => today()->addDay()->toDateString()])), 'lead_date');
    expectValidationError(fn () => app(CreateLead::class)->handle($this->actor, leadInput(['phone' => '0123'])), 'phone');
    expectValidationError(fn () => app(CreateLead::class)->handle($this->actor, leadInput(['lead_source_id' => LeadSource::idFor('REFERENCE')])), 'referrer_name');

    expect(app(CreateLead::class)->handle($this->actor, leadInput(['lead_source_id' => LeadSource::idFor('REFERENCE'), 'referrer_type' => 'other', 'referrer_name' => 'Uncle Karim']))->referrer_name)->toBe('Uncle Karim');
});

test('internal business lines and inactive services are refused (spec R19)', function () {
    $internal = BusinessLine::factory()->create(['is_internal' => true]);
    $retired = Service::factory()->create(['is_active' => false]);

    expectValidationError(fn () => app(CreateLead::class)->handle($this->actor, leadInput(['business_line_id' => $internal->id])), 'business_line_id');
    expectValidationError(fn () => app(CreateLead::class)->handle($this->actor, leadInput(['services' => [['service_id' => $retired->id]]])), 'services.0.service_id');
});

test('a duplicate phone needs a reason, which is audited (CRM-BR-03)', function () {
    $existing = Lead::factory()->create(['whatsapp' => '01711000000']);

    expectValidationError(fn () => app(CreateLead::class)->handle($this->actor, leadInput()), 'duplicates');

    $lead = app(CreateLead::class)->handle($this->actor, leadInput(['duplicate_reason' => 'Different project']));

    expect(AuditLog::query()->where('auditable_type', 'lead')->where('auditable_id', $lead->id)->where('event', 'duplicate_override')->first()->new_values)
        ->toMatchArray(['reason' => 'Different project', 'matches' => [$existing->lead_number]]);
});

test('a new enquiry for the matching customer needs no reason (CRM-AC-01)', function () {
    $customer = Customer::factory()->create(['phone' => '01711000000']);

    $lead = app(CreateLead::class)->handle($this->actor, leadInput([
        'lead_source_id' => LeadSource::idFor(LeadSource::EXISTING), 'referrer_type' => 'customer', 'referrer_id' => $customer->id,
    ]));

    expect($lead->referrerCustomer->is($customer))->toBeTrue();
});

test('a sales executive can only assign to themselves (CRM-BR-05)', function () {
    $someone = User::factory()->create();
    $someone->syncDirectPermissions(['crm.leads.view_own']);

    expectValidationError(fn () => app(CreateLead::class)->handle($this->actor, leadInput(['assigned_to' => $someone->id])), 'assigned_to');
});

test('updating syncs services and refuses converted leads', function () {
    $lead = app(CreateLead::class)->handle($this->actor, leadInput());

    app(UpdateLead::class)->handle($this->actor, $lead, leadInput(['name' => 'Rahim U.', 'services' => [['service_id' => $this->survey->id, 'estimated_value' => '25000']]]));

    expect($lead->fresh()->name)->toBe('Rahim U.')
        ->and($lead->services()->pluck('service_id')->all())->toBe([$this->survey->id])
        ->and($lead->fresh()->expected_value)->toBe('25000.00');

    $lead->forceFill(['converted_customer_id' => Customer::factory()->create()->id])->save();

    expectValidationError(fn () => app(UpdateLead::class)->handle(superAdmin(), $lead->fresh(), leadInput()), 'lead');
});

test('editing a lead whose phones and email did not change asks for no new reason', function () {
    Lead::factory()->create(['phone' => '01711000000']);
    $lead = app(CreateLead::class)->handle($this->actor, leadInput(['duplicate_reason' => 'Second plot']));

    expect(app(UpdateLead::class)->handle($this->actor, $lead, leadInput(['name' => 'Rahim U.']))->name)->toBe('Rahim U.');
});
