<?php

use App\Modules\Crm\Actions\DeleteCustomer;
use App\Modules\Crm\Actions\LogActivity;
use App\Modules\Crm\Actions\MergeCustomers;
use App\Modules\Crm\Livewire\Activities\QuickLog;
use App\Modules\Crm\Livewire\Customers\Show as CustomerShow;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\Customer;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->customer = Customer::factory()->create(['name' => 'Project Customer']);
    $this->project = Project::factory()->forCustomer($this->customer)->create(['name' => 'Bonosree House']);
});

test('the customer page lists the customer projects', function () {
    Livewire::actingAs(staffUser('management'))->test(CustomerShow::class, ['customer' => $this->customer])
        ->set('tab', 'projects')
        ->assertSee('Bonosree House')
        ->assertSee($this->project->project_number);
});

test('an engineer sees the customers of projects they work on (docs/03 §2)', function () {
    $engineer = staffUser('engineer');
    ProjectEmployee::factory()->create(['project_id' => $this->project->id, 'employee_id' => $engineer->employee_id]);
    Customer::factory()->create();

    expect(Customer::query()->visibleTo($engineer, 'crm.customers')->pluck('id')->all())->toBe([$this->customer->id]);
});

test('customers with projects cannot be deleted (CRM-BR-13)', function () {
    expectValidationError(fn () => app(DeleteCustomer::class)->handle(superAdmin(), $this->customer), 'customer');
});

test('merging moves the duplicate projects to the survivor (CRM-AC-10)', function () {
    $survivor = Customer::factory()->create();

    app(MergeCustomers::class)->handle(superAdmin(), $survivor, $this->customer, 'Same person');

    expect($this->project->fresh()->customer_id)->toBe($survivor->id);
});

test('project activities are logged from the quick log and show on the customer timeline', function () {
    $pm = staffUser('project_manager');
    $this->project->forceFill(['project_manager_id' => $pm->employee_id])->save();

    Livewire::actingAs($pm)->test(QuickLog::class)
        ->call('open', 'project', $this->project->id)
        ->set('activity_type_id', ActivityType::idFor('SITE_VISIT'))
        ->set('title', 'Foundation inspection')
        ->set('duration_minutes', '60')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->project->activities()->count())->toBe(1)
        ->and($this->customer->timeline()->pluck('title')->all())->toContain('Foundation inspection');
});

test('activities cannot be logged on a project the user cannot see', function () {
    app(LogActivity::class)->handle(staffUser('engineer'), $this->project, ['activity_type_id' => ActivityType::idFor('CALL'), 'title' => 'x', 'done' => true]);
})->throws(AuthorizationException::class);
