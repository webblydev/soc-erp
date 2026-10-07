<?php

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Actions\ConvertLead;
use App\Modules\Crm\Livewire\Leads\Convert;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ProjectType;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->seller = staffUser('sales_executive');
    $this->line = BusinessLine::factory()->create(['project_prefix' => 'SOC-BD']);
    $this->service = Service::factory()->create();
    $this->pm = Employee::factory()->create();
    $this->lead = Lead::factory()->assignedTo($this->seller)->create(['name' => 'Rahim Uddin', 'phone' => '01711000000', 'site_location_text' => 'Mirpur 10', 'business_line_id' => $this->line->id]);
    $this->lead->services()->create(['service_id' => $this->service->id, 'estimated_value' => 150000]);
});

function conversionInput(array $project = []): array
{
    return [
        'customer' => ['customer_type_id' => CustomerType::idFor('INDIVIDUAL'), 'name' => 'Rahim Uddin', 'phone' => '01711000000', 'contacts' => []],
        'project' => [
            'business_line_id' => test()->line->id, 'project_type_id' => ProjectType::idFor('RESIDENTIAL'), 'name' => 'Rahim Uddin, Mirpur 10',
            'project_manager_id' => test()->pm->id, 'services' => [['service_id' => test()->service->id, 'quantity' => '1', 'rate' => '150000']],
            ...$project,
        ],
    ];
}

test('converting creates the customer and the project in one step (CRM-AC-05)', function () {
    $customer = app(ConvertLead::class)->handle($this->seller, $this->lead, conversionInput());

    $lead = $this->lead->fresh();
    $project = Project::query()->firstOrFail();

    expect($lead)->lead_status_id->toBe(LeadStatus::idFor('WON'))->converted_customer_id->toBe($customer->id)->converted_project_id->toBe($project->id)
        ->and($project)->project_number->toBe('SOC-BD-0001')->customer_id->toBe($customer->id)->source_lead_id->toBe($lead->id)->contract_value->toBe('150000.00')
        ->and($project->status->code)->toBe(ProjectStatus::CONTRACTED)
        ->and($this->seller->can('view', $project))->toBeTrue();
});

test('a project failure leaves no customer and the lead unchanged (CRM-AC-06)', function () {
    expectValidationError(fn () => app(ConvertLead::class)->handle($this->seller, $this->lead, conversionInput(['project_manager_id' => null])), 'project.project_manager_id');

    expect(Customer::query()->count())->toBe(0)
        ->and(Project::query()->count())->toBe(0)
        ->and($this->lead->fresh())->lead_status_id->toBe(LeadStatus::idFor('NEW'))->converted_customer_id->toBeNull();
});

test('the project can be skipped only when the setting allows it', function () {
    expectValidationError(fn () => app(ConvertLead::class)->handle($this->seller, $this->lead, [...conversionInput(), 'skip_project' => true]), 'project');

    allowConversionWithoutProject();
    app(ConvertLead::class)->handle($this->seller, $this->lead, [...conversionInput(), 'skip_project' => true]);

    expect($this->lead->fresh()->converted_project_id)->toBeNull()->and(Project::query()->count())->toBe(0);
});

test('the wizard prefills the project from the lead and lands on the project', function () {
    $component = Livewire::actingAs($this->seller)->test(Convert::class, ['lead' => $this->lead])
        ->set('choice', 'new')
        ->set('customer.customer_type_id', (string) CustomerType::idFor('INDIVIDUAL'))
        ->call('next')
        ->assertSet('step', 2)
        ->assertSet('project.name', 'Rahim Uddin, Mirpur 10')
        ->assertSet('project.business_line_id', $this->line->id)
        ->assertSet('project.services.0.rate', '150000')
        ->assertDontSee('Convert without a project')
        ->call('next')
        ->assertHasErrors('project.project_manager_id')
        ->set('project.project_manager_id', $this->pm->id)
        ->set('applyTemplate', false)
        ->call('next')
        ->assertSet('step', 3)
        ->call('convert')
        ->assertHasNoErrors();

    $component->assertRedirect(route('projects.projects.show', Project::query()->firstOrFail()));
});

test('project errors from the action send the wizard back to the project step', function () {
    Livewire::actingAs($this->seller)->test(Convert::class, ['lead' => $this->lead])
        ->set('choice', 'new')
        ->set('customer.customer_type_id', (string) CustomerType::idFor('INDIVIDUAL'))
        ->call('next')
        ->set('project.project_manager_id', $this->pm->id)
        ->set('project.services.0.quantity', '0')
        ->set('applyTemplate', false)
        ->call('next')
        ->call('convert')
        ->assertHasErrors('project.services.0.quantity')
        ->assertSet('step', 2);
});
