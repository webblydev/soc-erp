<?php

use App\Modules\Crm\Livewire\Customers\Show;
use App\Modules\Crm\Models\Customer;
use App\Modules\Foundation\Livewire\Shared\DetailModal;
use App\Modules\Foundation\Livewire\Shared\Notes;
use Livewire\Livewire;

beforeEach(function () {
    seedCrm();
    $this->user = userWithPermissions('crm.customers.view_own', 'crm.leads.view_own');
    $this->customer = Customer::factory()->managedBy($this->user)->create(['name' => 'Rahim Holdings']);
});

test('an allowed detail URL renders its page component with the bound record and URL-bound tab', function () {
    Livewire::actingAs($this->user)->test(DetailModal::class)
        ->call('show', route('crm.customers.show', [$this->customer, 'tab' => 'notes']))
        ->assertOk()
        ->assertSeeLivewire(Show::class)
        ->assertSee('Rahim Holdings')
        ->assertSeeLivewire(Notes::class);
});

test('the modal header shows the record code, name and type', function () {
    Livewire::actingAs($this->user)->test(DetailModal::class)
        ->call('show', route('crm.customers.show', $this->customer))
        ->assertSeeInOrder([$this->customer->customer_number, 'Rahim Holdings', 'Customer']);
});

test('closing the modal drops the detail page', function () {
    Livewire::actingAs($this->user)->test(DetailModal::class)
        ->call('show', route('crm.customers.show', $this->customer))
        ->call('close')
        ->assertDontSeeLivewire(Show::class);
});

test('a URL outside the detail list or for a missing record returns 404', function (string $url) {
    Livewire::actingAs($this->user)->test(DetailModal::class)
        ->call('show', $url)
        ->assertNotFound();
})->with([
    'not a detail route' => fn () => route('crm.customers.edit', $this->customer),
    'unknown path' => fn () => url('/no-such-page'),
    'missing record' => fn () => route('crm.customers.show', 'CUS-999999'),
]);

test('a record the user may not view is forbidden', function () {
    Livewire::actingAs(userWithPermissions('crm.customers.view_own'))->test(DetailModal::class)
        ->call('show', route('crm.customers.show', $this->customer))
        ->assertForbidden();
});

test('the layout renders the detail modal and one copy of each CRM sheet', function () {
    $html = $this->actingAs(userWithPermissions('crm.leads.view_own', 'crm.activities.view_own'))->get(route('crm.leads.index'))
        ->assertOk()
        ->assertSeeLivewire(DetailModal::class)
        ->getContent();

    expect(substr_count($html, 'id="change-status-form"'))->toBe(1)
        ->and(substr_count($html, 'id="quick-log-form"'))->toBe(1);
});
