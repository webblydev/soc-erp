<?php

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use Illuminate\Database\QueryException;

test('an employee belongs to its job lookups, manager and linked user', function () {
    $boss = Employee::factory()->create(['first_name' => 'Boss']);
    $employee = Employee::factory()->create(['manager_id' => $boss->id]);
    $user = User::factory()->create(['employee_id' => $employee->id]);

    expect($employee->manager->is($boss))->toBeTrue()
        ->and($boss->reports->pluck('id')->all())->toBe([$employee->id])
        ->and($employee->user->is($user))->toBeTrue()
        ->and($user->employee->is($employee))->toBeTrue()
        ->and($employee->department)->toBeInstanceOf(Department::class)
        ->and($employee->getRouteKeyName())->toBe('employee_code');
});

test('assignable employees are those in active employment', function () {
    $active = Employee::factory()->create();
    $leave = Employee::factory()->withStatus(EmployeeStatus::ON_LEAVE)->create();
    Employee::factory()->withStatus(EmployeeStatus::RESIGNED)->create();
    Employee::factory()->withStatus(EmployeeStatus::SUSPENDED)->create();

    expect(Employee::query()->assignable()->pluck('id')->sort()->values()->all())->toBe([$active->id, $leave->id]);
});

test('users, branches and business lines point at real employees', function () {
    expect(fn () => User::factory()->create(['employee_id' => 999999]))->toThrow(QueryException::class);

    $employee = Employee::factory()->create();
    $line = BusinessLine::factory()->create(['manager_employee_id' => $employee->id]);

    expect($line->manager->is($employee))->toBeTrue();
});
