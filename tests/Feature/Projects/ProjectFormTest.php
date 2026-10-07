<?php

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Models\Customer;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Livewire\Projects\Form;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectContract;
use App\Modules\Projects\Models\ProjectType;
use App\Modules\Projects\Models\TaskTemplate;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->director = staffUser('management');
    $this->line = BusinessLine::factory()->create(['project_prefix' => 'SOC-BD']);
    $this->customer = Customer::factory()->create(['name' => 'Rahim Uddin', 'customer_number' => 'C-000057']);
    $this->service = Service::factory()->create(['name' => 'Building Design Work']);
});

test('the form needs create or update permission', function () {
    $this->actingAs(staffUser('engineer'));
    $this->get(route('projects.projects.create'))->assertForbidden();

    $this->actingAs($this->director);
    $this->get(route('projects.projects.create'))->assertOk()->assertSee('New project');
});

test('a project is created from the form with live totals', function () {
    $pm = Employee::factory()->create();

    $component = Livewire::actingAs($this->director)->test(Form::class)
        ->set('business_line_id', $this->line->id)
        ->set('name', 'Rahim Uddin, Bonosree')
        ->set('customerSearch', 'Rahim')
        ->call('pickCustomer', $this->customer->id)
        ->set('project_manager_id', $pm->id)
        ->set('applyTemplate', false)
        ->set('services.0.service_id', $this->service->id)
        ->set('services.0.rate', '2,50,000')
        ->set('services.0.quantity', '2')
        ->assertSee('5,00,000.00')
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::query()->firstOrFail();
    expect($project)->project_number->toBe('SOC-BD-0001')->customer_id->toBe($this->customer->id)->contract_value->toBe('500000.00');
    $component->assertRedirect(route('projects.projects.show', $project));
});

test('the customer query string preselects the customer', function () {
    Livewire::actingAs($this->director)->withQueryParams(['customer' => 'C-000057'])->test(Form::class)
        ->assertSet('customer_id', $this->customer->id)
        ->assertSet('name', 'Rahim Uddin');
});

test('picking the first service suggests its template', function () {
    $template = TaskTemplate::query()->firstOrFail();
    $template->update(['service_id' => $this->service->id]);

    Livewire::actingAs($this->director)->test(Form::class)
        ->set('services.0.service_id', $this->service->id)
        ->assertSet('task_template_id', $template->id);
});

test('internal types hide the customer', function () {
    Livewire::actingAs($this->director)->test(Form::class)
        ->call('pickCustomer', $this->customer->id)
        ->set('project_type_id', ProjectType::idFor('INTERNAL'))
        ->assertSet('customer_id', null)
        ->assertSee('Internal project');
});

test('a signed project edits without its services', function () {
    $project = Project::factory()->forCustomer($this->customer)->managedBy(Employee::factory()->create())->create(['business_line_id' => $this->line->id, 'contract_value' => 1000]);
    ProjectContract::factory()->signed()->create(['project_id' => $project->id]);

    Livewire::actingAs($this->director)->test(Form::class, ['project' => $project])
        ->assertSee('The contract is signed')
        ->set('name', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($project->fresh())->name->toBe('Renamed')->contract_value->toBe('1000.00');
});
