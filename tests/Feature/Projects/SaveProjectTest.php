<?php

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Models\Customer;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Projects\Actions\CreateProject;
use App\Modules\Projects\Actions\DeleteProject;
use App\Modules\Projects\Actions\SaveProjectServices;
use App\Modules\Projects\Actions\SetServiceStatus;
use App\Modules\Projects\Actions\UpdateProject;
use App\Modules\Projects\Events\ProjectCreated;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectContract;
use App\Modules\Projects\Models\ProjectRole;
use App\Modules\Projects\Models\ProjectServiceStatus;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ProjectType;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskTemplate;
use App\Modules\Projects\Notifications\ProjectManagerAssigned;
use App\Modules\Projects\Notifications\TeamMemberAdded;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->actor = staffUser('management');
    $this->line = BusinessLine::factory()->create(['project_prefix' => 'SOC-BD']);
    $this->customer = Customer::factory()->create();
    $this->pm = Employee::factory()->create();
    $this->service = Service::factory()->create();
});

test('a project gets the next number of its business line (PRJ-BR-01)', function () {
    $first = app(CreateProject::class)->handle($this->actor, projectInput());
    $second = app(CreateProject::class)->handle($this->actor, projectInput());

    expect($first->project_number)->toBe('SOC-BD-0001')
        ->and($second->project_number)->toBe('SOC-BD-0002')
        ->and($first->status->code)->toBe(ProjectStatus::ENQUIRY)
        ->and($first->created_by)->toBe($this->actor->id)
        ->and($first->statusHistories()->count())->toBe(1);
});

test('the contract value is the sum of the service lines (PRJ-BR-03)', function () {
    $project = app(CreateProject::class)->handle($this->actor, projectInput(['services' => [
        ['service_id' => $this->service->id, 'quantity' => '2', 'rate' => '1,50,000.50', 'discount_amount' => '1000'],
        ['service_id' => Service::factory()->create()->id, 'quantity' => '1', 'rate' => '20000'],
    ]]));

    expect($project->contract_value)->toBe('319001.00')
        ->and($project->services()->first()->amount)->toBe('299001.00');
});

test('a discount larger than the line is refused', function () {
    expectValidationError(fn () => app(CreateProject::class)->handle($this->actor, projectInput(['services' => [
        ['service_id' => $this->service->id, 'quantity' => '1', 'rate' => '100', 'discount_amount' => '200'],
    ]])), 'services.0.discount_amount');
});

test('billable projects need an active customer and internal ones none (PRJ-BR-02, CRM-BR-14)', function () {
    expectValidationError(fn () => app(CreateProject::class)->handle($this->actor, projectInput(['customer_id' => null])), 'customer_id');
    expectValidationError(fn () => app(CreateProject::class)->handle($this->actor, projectInput(['customer_id' => Customer::factory()->blocked()->create()->id])), 'customer_id');

    $internalLine = BusinessLine::factory()->internal()->create(['project_prefix' => 'SOC-HR']);
    $internal = ['business_line_id' => $internalLine->id, 'project_type_id' => ProjectType::idFor('INTERNAL'), 'services' => []];

    expectValidationError(fn () => app(CreateProject::class)->handle($this->actor, projectInput($internal)), 'customer_id');
    expectValidationError(fn () => app(CreateProject::class)->handle($this->actor, projectInput(['business_line_id' => $internalLine->id])), 'project_type_id');

    $project = app(CreateProject::class)->handle($this->actor, projectInput([...$internal, 'customer_id' => null]));

    expect($project->customer_id)->toBeNull()->and($project->project_number)->toBe('SOC-HR-0001');
});

test('the project manager must be an active employee (PRJ-BR-10)', function () {
    $left = Employee::factory()->withStatus(EmployeeStatus::RESIGNED)->create();

    expectValidationError(fn () => app(CreateProject::class)->handle($this->actor, projectInput(['project_manager_id' => $left->id])), 'project_manager_id');
    expectValidationError(fn () => app(CreateProject::class)->handle($this->actor, projectInput(['project_manager_id' => null])), 'project_manager_id');
});

test('the key people join the team and the PM is told (spec P6)', function () {
    $pmUser = staffUser('project_manager');
    $pm = Employee::query()->findOrFail($pmUser->employee_id);
    $supervisor = Employee::factory()->create();

    $project = app(CreateProject::class)->handle($this->actor, projectInput(['project_manager_id' => $pm->id, 'supervisor_id' => $supervisor->id]));

    expect($project->activeTeam()->with('role')->get()->map(fn ($m) => [$m->employee_id, $m->role->code])->all())
        ->toEqualCanonicalizing([[$pm->id, ProjectRole::PM], [$supervisor->id, ProjectRole::SUPERVISOR]]);

    Notification::assertSentTo($pmUser, ProjectManagerAssigned::class);
    Notification::assertNotSentTo($pmUser, TeamMemberAdded::class);
});

test('creating a project fires ProjectCreated and can apply a template (PRJ-AC-01)', function () {
    Event::fake([ProjectCreated::class]);
    $template = TaskTemplate::query()->firstOrFail();

    $project = app(CreateProject::class)->handle($this->actor, projectInput(['task_template_id' => $template->id, 'start_date' => '2026-11-01']));

    Event::assertDispatched(ProjectCreated::class);
    $tasks = Task::query()->where('project_id', $project->id)->orderBy('sort_order')->get();

    expect($tasks)->toHaveCount(12)
        ->and($tasks->first()->start_date->toDateString())->toBe('2026-11-01')
        ->and($tasks->first()->due_date->toDateString())->toBe('2026-11-04')
        ->and($tasks->first()->assignee_employee_id)->toBe($this->pm->id)
        ->and($tasks->first()->checklist()->count())->toBe(3);
});

test('updating keeps the number and business line', function () {
    $project = app(CreateProject::class)->handle($this->actor, projectInput());
    $other = BusinessLine::factory()->create();

    app(UpdateProject::class)->handle($this->actor, $project, projectInput(['name' => 'Renamed', 'business_line_id' => $other->id]));

    expect($project->fresh())->name->toBe('Renamed')->business_line_id->toBe($this->line->id)->project_number->toBe('SOC-BD-0001');
});

test('a signed contract locks the services (PRJ-BR-04)', function () {
    $project = app(CreateProject::class)->handle($this->actor, projectInput());
    ProjectContract::factory()->signed()->create(['project_id' => $project->id, 'deed_amount' => $project->contract_value]);

    expectValidationError(fn () => app(SaveProjectServices::class)->handle($this->actor, $project->fresh(), [
        ['service_id' => $this->service->id, 'quantity' => '1', 'rate' => '1'],
    ]), 'services');
    expectValidationError(fn () => app(UpdateProject::class)->handle($this->actor, $project->fresh(), projectInput(['services' => []])), 'services');

    expect($project->fresh()->contract_value)->toBe('500000.00');
});

test('saving services keeps existing lines by id and their delivery status', function () {
    $project = app(CreateProject::class)->handle($this->actor, projectInput());
    $line = $project->services()->firstOrFail();
    app(SetServiceStatus::class)->handle($this->actor, $line, ProjectServiceStatus::DELIVERED);

    app(SaveProjectServices::class)->handle($this->actor, $project, [
        ['id' => $line->id, 'service_id' => $this->service->id, 'quantity' => '1', 'rate' => '600000', 'project_service_status_id' => ProjectServiceStatus::idFor(ProjectServiceStatus::CANCELLED)],
    ]);

    expect($line->fresh())->amount->toBe('600000.00')->project_service_status_id->toBe(ProjectServiceStatus::idFor(ProjectServiceStatus::DELIVERED))
        ->and($line->fresh()->delivered_on)->not->toBeNull()
        ->and($project->fresh()->contract_value)->toBe('600000.00');
});

test('a line id from another project is refused', function () {
    $project = app(CreateProject::class)->handle($this->actor, projectInput());
    $foreign = Project::factory()->create()->services()->create(['service_id' => $this->service->id, 'rate' => 1, 'amount' => 1, 'project_service_status_id' => ProjectServiceStatus::idFor('NOT_STARTED')]);

    expectValidationError(fn () => app(SaveProjectServices::class)->handle($this->actor, $project, [
        ['id' => $foreign->id, 'service_id' => $this->service->id, 'quantity' => '1', 'rate' => '1'],
    ]), 'services.0.id');
});

test('only empty enquiries can be deleted (PRJ-BR-17)', function () {
    $project = app(CreateProject::class)->handle($this->actor, projectInput());
    Task::factory()->onProject($project)->create();

    expectValidationError(fn () => app(DeleteProject::class)->handle($this->actor, $project), 'project');

    $empty = app(CreateProject::class)->handle($this->actor, projectInput());
    app(DeleteProject::class)->handle($this->actor, $empty);

    expect($empty->fresh()->trashed())->toBeTrue();
});

test('users without projects.projects.create cannot create projects', function () {
    app(CreateProject::class)->handle(staffUser('engineer'), projectInput());
})->throws(AuthorizationException::class);
