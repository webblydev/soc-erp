<?php

use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Livewire\Leads\Form;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadSource;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedCrm();
    Notification::fake();
    $this->user = userWithPermissions('crm.leads.view_own', 'crm.leads.create', 'crm.leads.update', 'crm.customers.view_all');
    $this->service = Service::factory()->create();
});

function fillLeadForm($component, string $phone = '01711000000')
{
    return $component
        ->set('name', 'Rahim Uddin')
        ->set('phone', $phone)
        ->set('lead_source_id', (string) LeadSource::idFor('F2F'))
        ->set('services.0.service_id', (string) test()->service->id);
}

test('routes need create and update rights; converted leads cannot be edited', function () {
    $mine = Lead::factory()->assignedTo($this->user)->create();
    $converted = Lead::factory()->assignedTo($this->user)->converted(Customer::factory()->create())->create();

    $this->actingAs($this->user);
    $this->get(route('crm.leads.create'))->assertOk();
    $this->get(route('crm.leads.edit', $mine))->assertOk();
    $this->get(route('crm.leads.edit', $converted))->assertForbidden();

    $this->actingAs(userWithPermissions('crm.leads.view_own'));
    $this->get(route('crm.leads.create'))->assertForbidden();
});

test('a lead is created and the user lands on its page, assigned to themselves', function () {
    fillLeadForm(Livewire::actingAs($this->user)->test(Form::class))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('crm.leads.show', Lead::query()->first()));

    expect(Lead::query()->first())->assigned_to->toBe($this->user->id)->phone->toBe('01711000000');
});

test('the duplicate panel shows matches live and offers the three choices (CRM-AC-01)', function () {
    $customer = Customer::factory()->create(['phone' => '01711000000', 'name' => 'Existing Rahim']);

    $component = fillLeadForm(Livewire::actingAs($this->user)->test(Form::class));

    $component->assertSet('duplicates.0.number', $customer->customer_number)
        ->assertSee('Existing Rahim')
        ->assertSee(__('Add as new enquiry for this customer'));

    $component->call('addAsEnquiry', $customer->id)
        ->assertSet('lead_source_id', LeadSource::idFor(LeadSource::EXISTING))
        ->assertSet('referrer_type', 'customer')
        ->assertSet('referrer_id', $customer->id)
        ->call('save')
        ->assertHasNoErrors();
});

test('create anyway needs a reason', function () {
    Lead::factory()->create(['phone' => '01711000000']);

    fillLeadForm(Livewire::actingAs($this->user)->test(Form::class))
        ->call('save')->assertHasErrors('duplicates')
        ->set('overrideDuplicates', true)
        ->call('save')->assertHasErrors('duplicates')
        ->set('duplicate_reason', 'Second plot')
        ->call('save')->assertHasNoErrors();

    expect(Lead::query()->count())->toBe(2);
});

test('whatsapp can copy the phone and the referrer field appears for referral sources', function () {
    fillLeadForm(Livewire::actingAs($this->user)->test(Form::class))
        ->set('whatsappSameAsPhone', true)
        ->assertDontSee(__('Referrer'))
        ->set('lead_source_id', (string) LeadSource::idFor('REFERENCE'))
        ->assertSee(__('Referrer'))
        ->set('referrer_type', 'other')
        ->set('referrer_name', 'Uncle Karim')
        ->call('save')
        ->assertHasNoErrors();

    expect(Lead::query()->first())->whatsapp->toBe('01711000000')->referrer_name->toBe('Uncle Karim');
});

test('service rows are added and removed and the expected value follows them until typed', function () {
    $other = Service::factory()->create();

    fillLeadForm(Livewire::actingAs($this->user)->test(Form::class))
        ->set('services.0.estimated_value', '100000')
        ->call('addService')
        ->set('services.1.service_id', (string) $other->id)
        ->set('services.1.estimated_value', '50000')
        ->assertSet('expected_value', '150000.00')
        ->set('expected_value', '200000')
        ->assertSet('expected_value_manual', true)
        ->call('removeService', 1)
        ->call('save')
        ->assertHasNoErrors();

    expect(Lead::query()->first())->expected_value->toBe('200000.00')->and(Lead::query()->first()->services()->count())->toBe(1);
});

test('editing keeps the services and saves changes', function () {
    $lead = Lead::factory()->assignedTo($this->user)->create(['name' => 'Old Name']);
    $lead->services()->create(['service_id' => $this->service->id]);

    Livewire::actingAs($this->user)->test(Form::class, ['lead' => $lead])
        ->assertSet('services.0.service_id', $this->service->id)
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('crm.leads.show', $lead));

    expect($lead->fresh()->name)->toBe('New Name');
});
