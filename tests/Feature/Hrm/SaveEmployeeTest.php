<?php

use App\Modules\Hrm\Actions\CreateEmployee;
use App\Modules\Hrm\Actions\UpdateEmployee;
use App\Modules\Hrm\Events\EmployeeCreated;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmploymentEventType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedHrm();
    $this->actor = userWithPermissions('hrm.employees.view_basic', 'hrm.employees.view_full', 'hrm.employees.create', 'hrm.employees.update', 'hrm.employees.view_salary', 'hrm.employees.update_salary');
});

test('creating an employee numbers it, stores the full name and phone, and records JOINED', function () {
    Event::fake([EmployeeCreated::class]);

    $employee = app(CreateEmployee::class)->handle($this->actor, employeeInput());

    expect($employee->employee_code)->toBe('EMP-0001')
        ->and($employee->full_name)->toBe('Rahima Khatun')
        ->and($employee->phone)->toBe('01712345678')
        ->and($employee->gross_salary)->toBe('45000.00')
        ->and($employee->events()->first())->employment_event_type_id->toBe(EmploymentEventType::idFor('JOINED'));
    Event::assertDispatched(EmployeeCreated::class);
});

test('a typed code is kept upper-case, must be unique and cannot change later', function () {
    $employee = app(CreateEmployee::class)->handle($this->actor, employeeInput(['employee_code' => 'soc-042']));
    expect($employee->employee_code)->toBe('SOC-042');

    expectValidationError(fn () => app(CreateEmployee::class)->handle($this->actor, employeeInput(['employee_code' => 'SOC-042', 'phone' => '01812345678'])), 'employee_code');

    app(UpdateEmployee::class)->handle($this->actor, $employee, sameJob($employee, ['employee_code' => 'X-1']));
    expect($employee->fresh()->employee_code)->toBe('SOC-042');
});

test('NID is unique and the joining date at most 60 days ahead (HR-BR-02, HR-BR-04)', function () {
    app(CreateEmployee::class)->handle($this->actor, employeeInput(['nid_number' => '1990123456']));

    expectValidationError(fn () => app(CreateEmployee::class)->handle($this->actor, employeeInput(['nid_number' => '1990123456'])), 'nid_number');
    expectValidationError(fn () => app(CreateEmployee::class)->handle($this->actor, employeeInput(['joining_date' => today()->addDays(61)->toDateString()])), 'joining_date');
});

test('a manager cycle is refused (HR-BR-03)', function () {
    $top = Employee::factory()->create();
    $middle = Employee::factory()->create(['manager_id' => $top->id]);

    expectValidationError(fn () => app(UpdateEmployee::class)->handle($this->actor, $top, sameJob($top, ['manager_id' => $middle->id])), 'manager_id');
    expectValidationError(fn () => app(UpdateEmployee::class)->handle($this->actor, $top, sameJob($top, ['manager_id' => $top->id])), 'manager_id');
});

test('salary input without update_salary is ignored', function () {
    $actor = userWithPermissions('hrm.employees.view_basic', 'hrm.employees.view_full', 'hrm.employees.create');

    $employee = app(CreateEmployee::class)->handle($actor, employeeInput(['gross_salary' => '99000', 'bank_account_no' => '123']));

    expect($employee->gross_salary)->toBeNull()->and($employee->bank_account_no)->toBeNull();
});

test('personal input without view_full is ignored', function () {
    $actor = userWithPermissions('hrm.employees.view_basic', 'hrm.employees.create');

    $employee = app(CreateEmployee::class)->handle($actor, employeeInput(['nid_number' => '1990123456', 'father_name' => 'X']));

    expect($employee->nid_number)->toBeNull()->and($employee->father_name)->toBeNull();
});

test('an exit status cannot be set on the form', function () {
    expectValidationError(fn () => app(CreateEmployee::class)->handle($this->actor, employeeInput(['employee_status_id' => EmployeeStatus::idFor('RESIGNED')])), 'employee_status_id');
});

test('education and experience rows are synced', function () {
    $employee = app(CreateEmployee::class)->handle($this->actor, employeeInput([
        'education' => [['institution' => 'BUET', 'degree' => 'B.Arch', 'from_year' => 2010, 'to_year' => 2015, 'result' => '3.6']],
        'experience' => [['company' => 'ABC Ltd', 'position' => 'Architect', 'from_date' => '2016-01-01', 'to_date' => '2020-12-31', 'notes' => null]],
    ]));

    expect($employee->education)->toHaveCount(1)->and($employee->experience)->toHaveCount(1);

    $experience = $employee->experience->map(fn ($row) => ['id' => $row->id, 'company' => $row->company, 'position' => 'Senior Architect', 'from_date' => null, 'to_date' => null, 'notes' => null])->all();
    app(UpdateEmployee::class)->handle($this->actor, $employee, sameJob($employee, ['education' => [], 'experience' => $experience]));

    expect($employee->fresh()->education)->toHaveCount(0)
        ->and($employee->fresh()->experience)->toHaveCount(1)
        ->and($employee->fresh()->experience->first()->position)->toBe('Senior Architect');
});

test('a photo is stored on the public disk and replaced on update', function () {
    Storage::fake('public');

    $employee = app(CreateEmployee::class)->handle($this->actor, employeeInput(), UploadedFile::fake()->image('me.jpg'));
    $first = $employee->photo_path;
    Storage::disk('public')->assertExists($first);

    app(UpdateEmployee::class)->handle($this->actor, $employee, sameJob($employee), null, UploadedFile::fake()->image('new.png'));

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($employee->fresh()->photo_path);
});
