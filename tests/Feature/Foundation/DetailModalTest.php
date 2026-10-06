<?php

use App\Models\User;
use App\Modules\Crm\Livewire\Customers\Show;
use App\Modules\Crm\Livewire\Teams\Form as TeamForm;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Foundation\Livewire\Admin\Users\Form as UserForm;
use App\Modules\Foundation\Livewire\Shared\DetailModal;
use App\Modules\Foundation\Livewire\Shared\Notes;
use App\Modules\Hrm\Models\Employee;
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
    'not a modal route' => fn () => route('crm.customers.merge', $this->customer),
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

test('a create route opens its form with a "New" heading', function () {
    Livewire::actingAs(userWithPermissions('crm.teams.view', 'crm.teams.manage'))->test(DetailModal::class)
        ->call('show', route('crm.teams.create'))
        ->assertSeeLivewire(TeamForm::class)
        ->assertSeeInOrder(['New sales team', 'Sales Team']);
});

test('an edit route opens its form with the record heading', function () {
    $team = SalesTeam::factory()->create(['name' => 'Team Dhaka']);

    Livewire::actingAs(userWithPermissions('crm.teams.view', 'crm.teams.manage'))->test(DetailModal::class)
        ->call('show', route('crm.teams.edit', $team))
        ->assertSeeLivewire(TeamForm::class)
        ->assertSeeInOrder(['Team Dhaka', 'Edit sales team']);
});

test('a form the user may not open is forbidden in the modal', function () {
    Livewire::actingAs(userWithPermissions('crm.teams.view'))->test(DetailModal::class)
        ->call('show', route('crm.teams.create'))
        ->assertForbidden();
});

test('saving a form opened in the modal reloads the page under it', function () {
    $manager = User::factory()->create();

    Livewire::actingAs(userWithPermissions('crm.teams.view', 'crm.teams.manage'))
        ->test(TeamForm::class, ['returnUrl' => url('/crm/teams?search=North')])
        ->set('name', 'Team North')
        ->set('manager_user_id', (string) $manager->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(url('/crm/teams?search=North'));

    expect(SalesTeam::query()->where('name', 'Team North')->exists())->toBeTrue()
        ->and(session('success'))->toBe('Team created.');
});

test('the modal passes only a local return URL to the form', function () {
    $modal = Livewire::actingAs(userWithPermissions('crm.teams.view', 'crm.teams.manage'))->test(DetailModal::class)
        ->call('show', route('crm.teams.create'), 'https://evil.example/crm/teams?search=x');

    expect($modal->get('returnUrl'))->toBe(url('/crm/teams?search=x'));
});

test('create user from an employee profile prefills the employee inside the modal', function () {
    $employee = Employee::factory()->create(['employee_code' => 'EMP-0007', 'full_name' => 'Jamal Uddin']);
    $admin = userWithPermissions('admin.users.view', 'admin.users.create');

    Livewire::actingAs($admin)->test(DetailModal::class)
        ->call('show', route('admin.users.create', ['employee' => 'EMP-0007']))
        ->assertSeeLivewire(UserForm::class);

    Livewire::actingAs($admin)->test(UserForm::class, ['employee' => 'EMP-0007'])
        ->assertSet('employee_id', $employee->id)
        ->assertSet('name', 'Jamal Uddin');
});
