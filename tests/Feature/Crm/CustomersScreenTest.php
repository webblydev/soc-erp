<?php

use App\Models\User;
use App\Modules\Crm\Livewire\Customers\Form;
use App\Modules\Crm\Livewire\Customers\Index;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\Lead;
use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(fn () => Model::preventLazyLoading());
afterEach(fn () => Model::preventLazyLoading(false));

beforeEach(fn () => seedCrm());

test('each customers route needs its permission and scope', function () {
    $mine = userWithPermissions('crm.customers.view_own', 'crm.customers.update');
    $own = Customer::factory()->managedBy($mine)->create(['name' => 'Own Customer']);
    $other = Customer::factory()->create(['name' => 'Other Customer']);

    $this->actingAs(userWithPermissions('crm.leads.view_own'));
    $this->get(route('crm.customers.index'))->assertForbidden();

    $this->actingAs($mine);
    $this->get(route('crm.customers.index'))->assertOk()->assertSee('Own Customer')->assertDontSee('Other Customer');
    $this->get(route('crm.customers.create'))->assertForbidden();
    $this->get(route('crm.customers.edit', $own))->assertOk();
    $this->get(route('crm.customers.edit', $other))->assertForbidden();
});

test('the list searches and filters by type and location subtree', function () {
    $level = LocationLevel::query()->firstOrCreate(['code' => 'district'], ['name' => 'District']);
    $dhaka = Location::query()->create(['location_level_id' => $level->id, 'name' => 'Dhaka', 'full_path' => 'Dhaka']);
    $mirpur = Location::query()->create(['location_level_id' => $level->id, 'parent_id' => $dhaka->id, 'name' => 'Mirpur', 'full_path' => 'Dhaka › Mirpur']);
    $company = CustomerType::idFor('COMPANY');

    Customer::factory()->create(['name' => 'Mirpur Builders', 'location_id' => $mirpur->id, 'customer_type_id' => $company]);
    Customer::factory()->create(['name' => 'Sylhet Homes', 'phone' => '01911222333']);

    $component = Livewire::actingAs(userWithPermissions('crm.customers.view_all'))->test(Index::class);

    $component->set('search', '01911222333')->assertSee('Sylhet Homes')->assertDontSee('Mirpur Builders');
    $component->set('search', '')->set('filters.location', (string) $dhaka->id)->assertSee('Mirpur Builders')->assertDontSee('Sylhet Homes');
    $component->set('filters', ['type' => (string) $company])->assertSee('Mirpur Builders')->assertDontSee('Sylhet Homes');
});

test('bulk change of account manager only touches customers the user may update', function () {
    $manager = User::factory()->create();
    $a = Customer::factory()->create();
    $b = Customer::factory()->create();

    Livewire::actingAs(userWithPermissions('crm.customers.view_all', 'crm.customers.update'))->test(Index::class)
        ->set('selected', [(string) $a->id, (string) $b->id])
        ->set('bulkManagerId', (string) $manager->id)
        ->call('bulkChangeManager')
        ->assertDispatched('toast');

    expect($a->fresh()->account_manager_user_id)->toBe($manager->id)->and($b->fresh()->account_manager_user_id)->toBe($manager->id);
});

test('bulk change skips customers outside the user scope', function () {
    $mine = userWithPermissions('crm.customers.view_own', 'crm.customers.update');
    $own = Customer::factory()->managedBy($mine)->create();
    $foreign = Customer::factory()->create();
    $manager = User::factory()->create();

    Livewire::actingAs($mine)->test(Index::class)
        ->set('selected', [(string) $own->id, (string) $foreign->id])
        ->set('bulkManagerId', (string) $manager->id)
        ->call('bulkChangeManager');

    expect($own->fresh()->account_manager_user_id)->toBe($manager->id)->and($foreign->fresh()->account_manager_user_id)->toBeNull();
});

test('export needs crm.customers.export', function () {
    Livewire::actingAs(userWithPermissions('crm.customers.view_all'))->test(Index::class)->call('export')->assertForbidden();
    Livewire::actingAs(userWithPermissions('crm.customers.view_all', 'crm.customers.export'))->test(Index::class)->call('export')->assertFileDownloaded();
});

test('the form creates a customer and shows the reason field on a duplicate phone', function () {
    Customer::factory()->create(['phone' => '01711000000']);

    $component = Livewire::actingAs(userWithPermissions('crm.customers.view_all', 'crm.customers.create'))->test(Form::class)
        ->set('customer_type_id', (string) CustomerType::idFor('INDIVIDUAL'))
        ->set('name', 'Rahim Uddin')
        ->set('phone', '01711-000000')
        ->call('save')
        ->assertHasErrors('duplicate_reason')
        ->assertSee(__('Reason'));

    $component->set('duplicate_reason', 'Family number')->call('save')->assertHasNoErrors();

    expect(Customer::query()->where('name', 'Rahim Uddin')->exists())->toBeTrue();
});

test('finance fields are read-only without update_finance', function () {
    $customer = Customer::factory()->create(['tin' => '111']);

    Livewire::actingAs(userWithPermissions('crm.customers.view_all', 'crm.customers.update'))->test(Form::class, ['customer' => $customer])
        ->assertSet('canEditFinance', false)
        ->assertSee(__('Only finance users can change these.'));
});

test('bulk delete skips customers converted from a lead and customers the user cannot see', function () {
    $actor = userWithPermissions('crm.customers.view_own', 'crm.customers.delete');
    $plain = Customer::factory()->managedBy($actor)->create();
    $converted = Customer::factory()->managedBy($actor)->create();
    Lead::factory()->converted($converted)->create();
    $hidden = Customer::factory()->create();

    Livewire::actingAs($actor)->test(Index::class)
        ->set('selected', [(string) $plain->id, (string) $converted->id, (string) $hidden->id])
        ->call('deleteSelected')
        ->assertDispatched('toast', type: 'warning', description: '1 deleted, 2 skipped. Customers converted from a lead cannot be deleted.');

    expect($plain->fresh()->trashed())->toBeTrue()
        ->and($converted->fresh()->trashed())->toBeFalse()
        ->and($hidden->fresh()->trashed())->toBeFalse();
});
