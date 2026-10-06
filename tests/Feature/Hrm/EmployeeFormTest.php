<?php

use App\Modules\Hrm\Livewire\Employees\Form;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmployeeType;
use App\Modules\Hrm\Models\EmploymentEventType;
use Livewire\Livewire;

beforeEach(function () {
    seedHrm();
    $this->hr = userWithPermissions('hrm.employees.view_basic', 'hrm.employees.view_full', 'hrm.employees.create', 'hrm.employees.update', 'hrm.employees.view_salary', 'hrm.employees.update_salary', 'hrm.history.manage');
});

test('create and edit routes need their permissions', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(userWithPermissions('hrm.employees.view_basic'));
    $this->get(route('hrm.employees.create'))->assertForbidden();
    $this->get(route('hrm.employees.edit', $employee))->assertForbidden();

    $this->actingAs($this->hr);
    $this->get(route('hrm.employees.create'))->assertOk();
    $this->get(route('hrm.employees.edit', $employee))->assertOk()->assertSee($employee->first_name);
});

test('creating an employee from the form redirects to the profile', function () {
    Livewire::actingAs($this->hr)->test(Form::class)
        ->set('first_name', 'Tania')->set('phone', '01712999888')
        ->set('department_id', (string) Department::idFor('DESIGN'))->set('designation_id', (string) Designation::idFor('DRAFTSMAN'))
        ->set('employee_type_id', (string) EmployeeType::idFor('PERMANENT'))->set('employee_status_id', (string) EmployeeStatus::idFor('ACTIVE'))
        ->set('joining_date', today()->toDateString())
        ->call('addEducation')->set('education.0.institution', 'BUET')->set('education.0.degree', 'Diploma')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('hrm.employees.show', Employee::query()->first()));

    expect(Employee::query()->first())->full_name->toBe('Tania')->education->toHaveCount(1);
});

test('validation errors show on the form', function () {
    Livewire::actingAs($this->hr)->test(Form::class)
        ->set('phone', '123')
        ->call('save')
        ->assertHasErrors(['first_name', 'phone', 'department_id']);
});

test('a job change opens the event sheet and saves with the event', function () {
    $employee = Employee::factory()->create(['designation_id' => Designation::idFor('SITE_ENGINEER')]);

    Livewire::actingAs($this->hr)->test(Form::class, ['employee' => $employee])
        ->set('designation_id', (string) Designation::idFor('PROJECT_ENGINEER'))
        ->call('save')
        ->assertDispatched('open-sheet-employment-event')
        ->assertNoRedirect()
        ->set('event.employment_event_type_id', (string) EmploymentEventType::idFor('PROMOTED'))
        ->set('event.effective_date', today()->toDateString())
        ->call('saveWithEvent')
        ->assertHasNoErrors()
        ->assertRedirect(route('hrm.employees.show', $employee));

    expect($employee->fresh()->designation_id)->toBe(Designation::idFor('PROJECT_ENGINEER'))->and($employee->events()->count())->toBe(1);
});

test('event errors show in the sheet', function () {
    $employee = Employee::factory()->create(['designation_id' => Designation::idFor('SITE_ENGINEER')]);

    Livewire::actingAs($this->hr)->test(Form::class, ['employee' => $employee])
        ->set('designation_id', (string) Designation::idFor('PROJECT_ENGINEER'))
        ->set('event.effective_date', '')
        ->call('saveWithEvent')
        ->assertHasErrors(['event.employment_event_type_id', 'event.effective_date']);
});

test('salary and personal fields follow visibility', function () {
    $basic = userWithPermissions('hrm.employees.view_basic', 'hrm.employees.create');

    Livewire::actingAs($basic)->test(Form::class)->assertDontSee(__('Gross salary'))->assertDontSee(__('Father’s name'));
    Livewire::actingAs($this->hr)->test(Form::class)->assertSee(__('Gross salary'))->assertSee(__('Father’s name'));
});

test('exit statuses are not offered on the form', function () {
    Livewire::actingAs($this->hr)->test(Form::class)->assertDontSee('Terminated')->assertSee('On leave');
});
