<?php

use App\Modules\Foundation\Actions\SaveLookup;
use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use Livewire\Livewire;

beforeEach(fn () => seedHrm());

test('HR tables need the hrm.masters permissions', function () {
    $this->actingAs(userWithPermissions('hrm.employees.view_basic'));
    $this->get(route('admin.master-data.show', 'departments'))->assertForbidden();

    $this->actingAs(userWithPermissions('hrm.masters.view'));
    $this->get(route('admin.master-data.show', 'departments'))->assertOk()->assertSee('Project Operation');
});

test('status flags are not editable on Master Data', function () {
    $status = EmployeeStatus::query()->where('code', 'SUSPENDED')->first();

    app(SaveLookup::class)->handle('employee_statuses', ['code' => 'SUSPENDED', 'name' => 'Suspended', 'is_active' => true, 'is_active_employment' => true, 'is_exit' => true], $status);

    expect($status->fresh())->is_active_employment->toBeFalse()->is_exit->toBeFalse();
});

test('a department head must be an assignable employee', function () {
    $resigned = Employee::factory()->withStatus(EmployeeStatus::RESIGNED)->create();
    $active = Employee::factory()->create();
    $design = Department::query()->where('code', 'DESIGN')->first();

    expectValidationError(fn () => app(SaveLookup::class)->handle('departments', ['code' => 'DESIGN', 'name' => 'Design', 'is_active' => true, 'head_employee_id' => $resigned->id], $design), 'head_employee_id');

    app(SaveLookup::class)->handle('departments', ['code' => 'DESIGN', 'name' => 'Design', 'is_active' => true, 'head_employee_id' => $active->id], $design);

    expect($design->fresh()->head_employee_id)->toBe($active->id);
});

test('a department cannot be its own ancestor', function () {
    $parent = Department::query()->where('code', 'PROJECT_OPS')->first();
    $child = Department::query()->where('code', 'DESIGN')->first();
    $child->update(['parent_id' => $parent->id]);

    expectValidationError(fn () => app(SaveLookup::class)->handle('departments', ['code' => 'PROJECT_OPS', 'name' => 'Project Operation', 'is_active' => true, 'parent_id' => $child->id], $parent), 'parent_id');
    expectValidationError(fn () => app(SaveLookup::class)->handle('departments', ['code' => 'DESIGN', 'name' => 'Design', 'is_active' => true, 'parent_id' => $child->id], $child), 'parent_id');
});

test('branch and business line managers use the employee picker', function () {
    Employee::factory()->create(['first_name' => 'Karim', 'last_name' => 'Ahmed', 'full_name' => 'Karim Ahmed']);

    Livewire::actingAs(superAdmin())->test(MasterData::class, ['table' => 'branches'])
        ->call('create')
        ->assertSee('Karim Ahmed');

    Livewire::actingAs(superAdmin())->test(MasterData::class, ['table' => 'business_lines'])
        ->call('create')
        ->assertSee('Karim Ahmed');
});
