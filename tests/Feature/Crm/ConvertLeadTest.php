<?php

use App\Models\User;
use App\Modules\Crm\Actions\ConvertLead;
use App\Modules\Crm\Events\LeadConverted;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Crm\Notifications\LeadWon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedCrm();
    seedAccessControl();
    Notification::fake();
    allowConversionWithoutProject();

    $this->manager = User::factory()->create();
    $this->manager->assignRole('sales_manager');
    $this->seller = User::factory()->create();
    $this->seller->assignRole('sales_executive');
    $this->director = User::factory()->create();
    $this->director->assignRole('management');

    $team = SalesTeam::factory()->managedBy($this->manager)->withMembers($this->seller)->create();
    $this->lead = Lead::factory()->assignedTo($this->seller)->create([
        'name' => 'Rahim Uddin', 'phone' => '01711000000', 'sales_team_id' => $team->id, 'lead_source_id' => LeadSource::idFor('F2F'),
    ]);
});

function newCustomerInput(array $overrides = []): array
{
    return ['skip_project' => true, 'customer' => [
        'customer_type_id' => CustomerType::idFor('INDIVIDUAL'), 'name' => 'Rahim Uddin', 'phone' => '01711000000',
        'contacts' => [], ...$overrides,
    ]];
}

test('converting creates the customer with attribution and marks the lead won (CRM-AC-05 without the project)', function () {
    CrmActivity::factory()->on($this->lead)->create(['title' => 'Site visit']);

    $customer = app(ConvertLead::class)->handle($this->seller, $this->lead, newCustomerInput());
    $lead = $this->lead->fresh();

    expect($customer)->source_lead_id->toBe($lead->id)->lead_source_id->toBe(LeadSource::idFor('F2F'))->acquired_by_user_id->toBe($this->seller->id)
        ->and($lead)->lead_status_id->toBe(LeadStatus::idFor('WON'))->converted_customer_id->toBe($customer->id)->converted_by->toBe($this->seller->id)
        ->and($lead->won_at)->not->toBeNull()
        ->and($lead->statusHistories()->first()->to_status_id)->toBe(LeadStatus::idFor('WON'))
        ->and($customer->timeline()->pluck('title')->all())->toContain('Site visit');

    Notification::assertSentTo([$this->manager, $this->director], LeadWon::class);
});

test('linking an existing customer keeps its first attribution (CRM-BR-16, CRM-BR-17)', function () {
    $first = Lead::factory()->create();
    $customer = Customer::factory()->create(['source_lead_id' => $first->id, 'lead_source_id' => LeadSource::idFor('GOOGLE'), 'acquired_by_user_id' => $this->manager->id]);

    app(ConvertLead::class)->handle($this->seller, $this->lead, ['customer_id' => $customer->id, 'skip_project' => true]);

    expect($customer->fresh())->source_lead_id->toBe($first->id)->lead_source_id->toBe(LeadSource::idFor('GOOGLE'))->acquired_by_user_id->toBe($this->manager->id)
        ->and($this->lead->fresh()->converted_customer_id)->toBe($customer->id);
});

test('linking fills attribution a manually created customer lacks', function () {
    $customer = Customer::factory()->create();

    app(ConvertLead::class)->handle($this->seller, $this->lead, ['customer_id' => $customer->id, 'skip_project' => true]);

    expect($customer->fresh())->source_lead_id->toBe($this->lead->id)->acquired_by_user_id->toBe($this->seller->id);
});

test('blocked and merged customers cannot be linked', function () {
    $blocked = Customer::factory()->blocked()->create();
    $merged = Customer::factory()->create(['merged_into_id' => Customer::factory()->create()->id]);

    expectValidationError(fn () => app(ConvertLead::class)->handle($this->seller, $this->lead, ['customer_id' => $blocked->id, 'skip_project' => true]), 'customer_id');
    expectValidationError(fn () => app(ConvertLead::class)->handle($this->seller, $this->lead, ['customer_id' => $merged->id, 'skip_project' => true]), 'customer_id');
});

test('a failure inside conversion leaves no customer and the lead unchanged (CRM-AC-06)', function () {
    Event::listen(LeadConverted::class, fn () => throw new RuntimeException('Project creation failed'));

    expect(fn () => app(ConvertLead::class)->handle($this->seller, $this->lead, newCustomerInput()))->toThrow(RuntimeException::class);

    expect(Customer::query()->count())->toBe(0)
        ->and($this->lead->fresh())->lead_status_id->toBe(LeadStatus::idFor('NEW'))->converted_customer_id->toBeNull();
});

test('closed or already converted leads cannot be converted', function () {
    $lost = Lead::factory()->assignedTo($this->seller)->withStatus('LOST')->create();

    expectValidationError(fn () => app(ConvertLead::class)->handle(superAdmin(), $lost, newCustomerInput()), 'lead');
});
