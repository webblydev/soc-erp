<?php

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\EmployeeFields;

beforeEach(fn () => seedHrm());

test('basic viewers see directory fields only', function () {
    $user = userWithPermissions('hrm.employees.view_basic');
    $employee = Employee::factory()->create();

    expect($user->can('view', $employee))->toBeTrue()
        ->and($user->can('viewFull', $employee))->toBeFalse()
        ->and($user->can('viewSalary', $employee))->toBeFalse()
        ->and(EmployeeFields::visibleTo($user, $employee))->not->toContain('nid_number')->not->toContain('gross_salary');
});

test('the linked user sees their own full and salary fields', function () {
    $user = userWithPermissions('hrm.employees.view_basic');
    $employee = Employee::factory()->linkedTo($user)->create();
    $user->refresh();

    expect($user->can('viewFull', $employee))->toBeTrue()
        ->and($user->can('viewSalary', $employee))->toBeTrue()
        ->and($user->can('update', $employee))->toBeFalse()
        ->and($user->can('viewFull', Employee::factory()->create()))->toBeFalse();
});

test('documents need documents.view and full visibility', function () {
    $employee = Employee::factory()->create();

    expect(userWithPermissions('hrm.employees.view_basic', 'hrm.documents.view')->can('viewDocuments', $employee))->toBeFalse()
        ->and(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.view_full', 'hrm.documents.view')->can('viewDocuments', $employee))->toBeTrue();
});

test('a project manager sees job info but not NID or salary (HR-AC-04)', function () {
    seedAccessControl();
    $pm = User::factory()->create();
    $pm->syncRoles(['project_manager']);
    $employee = Employee::factory()->create(['nid_number' => '1234567890']);

    expect(EmployeeFields::visibleTo($pm->fresh(), $employee))->toContain('designation_id')->not->toContain('nid_number')->not->toContain('gross_salary');
});
