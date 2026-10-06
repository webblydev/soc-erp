<?php

use App\Models\User;
use App\Modules\Hrm\Actions\ExitEmployee;
use App\Modules\Hrm\Livewire\Employees\Documents;
use App\Modules\Hrm\Livewire\Employees\Show;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeDocument;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmploymentEventType;
use App\Modules\Hrm\Models\ExitReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Model::preventLazyLoading());
afterEach(fn () => Model::preventLazyLoading(false));
beforeEach(fn () => seedHrm());

test('basic viewers see job info but not NID or salary (HR-AC-04)', function () {
    $employee = Employee::factory()->create(['nid_number' => '1990555444']);
    $employee->forceFill(['gross_salary' => '55000'])->save();

    $this->actingAs(userWithPermissions('hrm.employees.view_basic'));
    $this->get(route('hrm.employees.show', $employee))->assertOk()
        ->assertSee($employee->full_name)->assertSee($employee->designation->name)
        ->assertDontSee('1990555444')->assertDontSee('55,000');

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic'))->test(Show::class, ['employee' => $employee])
        ->set('tab', 'salary')->assertSet('tab', 'overview');
});

test('the profile needs view_basic unless it is your own', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(userWithPermissions('crm.leads.view_own'))->get(route('hrm.employees.show', $employee))->assertForbidden();
});

test('an employee sees their own full profile and salary but cannot edit', function () {
    $user = userWithPermissions('crm.leads.view_own');
    $employee = Employee::factory()->linkedTo($user)->create(['nid_number' => '1990555444']);
    $employee->forceFill(['gross_salary' => '55000'])->save();

    $this->actingAs($user->fresh())->get(route('hrm.employees.show', $employee))->assertOk()
        ->assertSee('1990555444')->assertDontSee(route('hrm.employees.edit', $employee));

    Livewire::actingAs($user->fresh())->test(Show::class, ['employee' => $employee])
        ->set('tab', 'salary')->assertSet('tab', 'salary')->assertSee('55,000');
});

test('the employment history tab shows events, with salary only when allowed', function () {
    $employee = Employee::factory()->create();
    $employee->events()->create(['employment_event_type_id' => EmploymentEventType::idFor('SALARY_REVISED'), 'effective_date' => today(), 'from_salary' => '30000', 'to_salary' => '38000']);

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic'))->test(Show::class, ['employee' => $employee])
        ->set('tab', 'events')->assertSee('Salary revised')->assertDontSee('38,000');

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.view_salary'))->test(Show::class, ['employee' => $employee])
        ->set('tab', 'events')->assertSee('38,000');
});

test('recording an event from the profile', function () {
    $employee = Employee::factory()->create();

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.history.manage'))->test(Show::class, ['employee' => $employee])
        ->set('eventForm.employment_event_type_id', (string) EmploymentEventType::idFor('CONFIRMED'))
        ->set('eventForm.effective_date', today()->toDateString())
        ->call('recordEvent')->assertHasNoErrors()->assertDispatched('close-sheet-employment-event');

    expect($employee->fresh()->confirmation_date)->not->toBeNull();
});

test('event errors are shown in the sheet', function () {
    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.history.manage'))->test(Show::class, ['employee' => Employee::factory()->create()])
        ->set('eventForm.effective_date', '')
        ->call('recordEvent')->assertHasErrors(['eventForm.employment_event_type_id', 'eventForm.effective_date']);
});

test('rejoining from the profile and linking a user', function () {
    $admin = userWithPermissions('hrm.employees.view_basic', 'hrm.employees.deactivate', 'admin.users.view', 'admin.users.update', 'admin.users.create');
    $employee = Employee::factory()->create();
    app(ExitEmployee::class)->handle($admin, $employee, ['employee_status_id' => EmployeeStatus::idFor('RESIGNED'), 'exit_date' => today()->toDateString(), 'exit_reason_id' => ExitReason::query()->value('id')]);
    $user = User::factory()->create(['username' => 'karim.link']);

    Livewire::actingAs($admin)->test(Show::class, ['employee' => $employee->fresh()])
        ->assertSee(__('No login yet'))
        ->set('rejoinForm.effective_date', today()->toDateString())->call('rejoin')->assertHasNoErrors()
        ->set('linkUserId', (string) $user->id)->call('linkUser')->assertHasNoErrors()
        ->assertSee('karim.link')
        ->call('unlinkUser')->assertHasNoErrors();

    expect($employee->fresh()->employee_status_id)->toBe(EmployeeStatus::idFor('ACTIVE'))->and($user->fresh()->employee_id)->toBeNull();
});

test('the documents tab lists documents with expiry badges for allowed users only', function () {
    $employee = Employee::factory()->create();
    EmployeeDocument::factory()->for($employee)->expiringOn(today()->subDay()->toDateString())->create(['employee_document_type_id' => EmployeeDocumentType::idFor('PASSPORT'), 'document_no' => 'BX0011']);

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.view_full', 'hrm.documents.view'))
        ->test(Documents::class, ['employee' => $employee])->assertSee('BX0011')->assertSee(__('Expired'));

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.documents.view'))
        ->test(Documents::class, ['employee' => $employee])->assertForbidden();
});

test('a document can be added from the documents tab', function () {
    Storage::fake(config('foundation.attachments.disk'));
    $employee = Employee::factory()->create();

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.view_full', 'hrm.documents.view', 'hrm.documents.manage', 'attachments.upload'))
        ->test(Documents::class, ['employee' => $employee])
        ->call('create')
        ->set('form.employee_document_type_id', (string) EmployeeDocumentType::idFor('CV'))
        ->set('form.document_no', 'CV-1')
        ->set('file', UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'))
        ->call('save')->assertHasNoErrors()
        ->assertSee('CV-1');

    expect($employee->documents()->first()->attachment_id)->not->toBeNull();
});
