<?php

use App\Models\User;
use App\Modules\Crm\Livewire\Leads\ChangeStatus;
use App\Modules\Crm\Livewire\Leads\Convert;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedCrm();
    Notification::fake();
    allowConversionWithoutProject();
    $this->seller = userWithPermissions('crm.leads.view_own', 'crm.leads.update', 'crm.leads.convert', 'crm.customers.view_own', 'crm.customers.create');
    $this->lead = Lead::factory()->assignedTo($this->seller)->create(['name' => 'Rahim Uddin', 'phone' => '01711000000', 'site_location_text' => 'Mirpur 10']);
});

test('the wizard route needs convert rights on an open lead', function () {
    $this->actingAs($this->seller);
    $this->get(route('crm.leads.convert', $this->lead))->assertOk();

    $this->actingAs(userWithPermissions('crm.leads.view_all'));
    $this->get(route('crm.leads.convert', $this->lead))->assertForbidden();
});

test('step 1 suggests matching customers and prefills a new one from the lead', function () {
    $match = Customer::factory()->create(['phone' => '01711000000', 'name' => 'Rahim (existing)']);

    Livewire::actingAs($this->seller)->test(Convert::class, ['lead' => $this->lead])
        ->assertSee('Rahim (existing)')
        ->assertSet('choice', 'link')
        ->assertSet('customerId', $match->id)
        ->set('choice', 'new')
        ->assertSet('customer.name', 'Rahim Uddin')
        ->assertSet('customer.phone', '01711000000');
});

test('creating a new customer through the wizard converts and redirects to the customer', function () {
    Livewire::actingAs($this->seller)->test(Convert::class, ['lead' => $this->lead])
        ->set('choice', 'new')
        ->set('customer.customer_type_id', (string) CustomerType::idFor('INDIVIDUAL'))
        ->call('next')
        ->assertSet('step', 2)
        ->set('skipProject', true)
        ->call('next')
        ->assertSet('step', 3)
        ->call('convert')
        ->assertHasNoErrors()
        ->assertRedirect(route('crm.customers.show', Customer::query()->first()));

    expect($this->lead->fresh()->lead_status_id)->toBe(LeadStatus::idFor('WON'));
});

test('linking a customer outside the user scope lands on the lead page', function () {
    $match = Customer::factory()->create(['phone' => '01711000000', 'acquired_by_user_id' => User::factory()->create()->id]);

    Livewire::actingAs($this->seller)->test(Convert::class, ['lead' => $this->lead])
        ->assertSet('customerId', $match->id)
        ->call('next')
        ->set('skipProject', true)
        ->call('next')
        ->call('convert')
        ->assertHasNoErrors()
        ->assertRedirect(route('crm.leads.show', $this->lead));

    expect($this->lead->fresh()->converted_customer_id)->toBe($match->id);
});

test('choosing Won in the change-status sheet goes to the wizard', function () {
    Livewire::actingAs($this->seller)->test(ChangeStatus::class)
        ->dispatch('crm-change-status', lead: $this->lead->lead_number, mode: 'status')
        ->set('statusId', 'won')
        ->call('save')
        ->assertRedirect(route('crm.leads.convert', $this->lead));
});
