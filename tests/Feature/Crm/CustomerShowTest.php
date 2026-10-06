<?php

use App\Modules\Crm\Livewire\Customers\Show;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Foundation\Livewire\Shared\Attachments;
use App\Modules\Foundation\Livewire\Shared\Notes;
use Livewire\Livewire;

beforeEach(function () {
    seedCrm();
    $this->user = userWithPermissions('crm.customers.view_own', 'crm.customers.update', 'crm.activities.view_own', 'crm.activities.create');
    $this->customer = Customer::factory()->managedBy($this->user)->create(['name' => 'Rahim Holdings']);
});

test('the detail page needs the customer to be visible', function () {
    $this->actingAs($this->user);
    $this->get(route('crm.customers.show', $this->customer))->assertOk()->assertSee('Rahim Holdings')->assertSee($this->customer->customer_number);

    $this->actingAs(userWithPermissions('crm.customers.view_own'));
    $this->get(route('crm.customers.show', $this->customer))->assertForbidden();
});

test('the timeline merges customer, converted-lead and referred-lead activities (CRM-AC-05 timeline part)', function () {
    $converted = Lead::factory()->converted($this->customer)->create();
    $referred = Lead::factory()->create(['referrer_type' => 'customer', 'referrer_id' => $this->customer->id]);
    $unrelated = Lead::factory()->create();

    CrmActivity::factory()->on($this->customer)->create(['title' => 'Customer call']);
    CrmActivity::factory()->on($converted)->create(['title' => 'Lead meeting']);
    CrmActivity::factory()->on($referred)->create(['title' => 'Referral call']);
    CrmActivity::factory()->on($unrelated)->create(['title' => 'Unrelated call']);

    expect($this->customer->timeline()->pluck('title')->all())->toEqualCanonicalizing(['Customer call', 'Lead meeting', 'Referral call']);

    Livewire::actingAs($this->user)->test(Show::class, ['customer' => $this->customer])
        ->set('tab', 'activities')
        ->assertSee('Lead meeting')->assertSee('Referral call')->assertDontSee('Unrelated call');
});

test('the leads tab lists origin and later enquiries', function () {
    $origin = Lead::factory()->converted($this->customer)->create(['name' => 'First enquiry']);
    Lead::factory()->create(['name' => 'Second enquiry', 'referrer_type' => 'customer', 'referrer_id' => $this->customer->id]);
    $this->customer->forceFill(['source_lead_id' => $origin->id])->save();

    Livewire::actingAs($this->user)->test(Show::class, ['customer' => $this->customer])
        ->set('tab', 'leads')
        ->assertSee('First enquiry')->assertSee('Second enquiry')->assertSee(__('Origin'));
});

test('documents and notes panels are on the page, and no money tabs are shown (spec R2)', function () {
    Livewire::actingAs($this->user)->test(Show::class, ['customer' => $this->customer])
        ->set('tab', 'documents')->assertSeeLivewire(Attachments::class)
        ->set('tab', 'notes')->assertSeeLivewire(Notes::class)
        ->assertDontSee(__('Invoices'))->assertDontSee(__('Outstanding'));
});
