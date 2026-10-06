<?php

use App\Models\User;
use App\Modules\Hrm\Contracts\EmployeeExitCheck;
use App\Modules\Hrm\Livewire\Employees\ExitWizard;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\ExitReason;
use App\Modules\Hrm\Services\ExitCheckItem;
use App\Modules\Hrm\Services\ExitChecks;
use Livewire\Livewire;

beforeEach(fn () => seedHrm());

test('the exit route needs deactivate', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(userWithPermissions('hrm.employees.view_basic'));
    $this->get(route('hrm.employees.exit', $employee))->assertForbidden();

    $this->actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.deactivate'));
    $this->get(route('hrm.employees.exit', $employee))->assertOk();
});

test('the wizard shows the checks then exits the employee', function () {
    $user = User::factory()->create(['username' => 'rafiq']);
    $employee = Employee::factory()->linkedTo($user)->create();

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.deactivate'))->test(ExitWizard::class, ['employee' => $employee])
        ->set('employee_status_id', (string) EmployeeStatus::idFor('TERMINATED'))
        ->set('exit_date', today()->toDateString())
        ->set('exit_reason_id', (string) ExitReason::query()->value('id'))
        ->call('next')
        ->assertSet('step', 2)
        ->assertSee('rafiq')
        ->call('confirm')
        ->assertRedirect(route('hrm.employees.show', $employee));

    expect($employee->fresh()->employee_status_id)->toBe(EmployeeStatus::idFor('TERMINATED'));
});

test('step one validates before showing checks', function () {
    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.deactivate'))->test(ExitWizard::class, ['employee' => Employee::factory()->create()])
        ->set('employee_status_id', '')->set('exit_date', '')
        ->call('next')->assertHasErrors(['employee_status_id', 'exit_date', 'exit_reason_id'])->assertSet('step', 1);
});

test('a blocking check disables confirm and refuses the exit', function () {
    app(ExitChecks::class)->register(WizardBlockingCheck::class);
    $employee = Employee::factory()->create();

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.deactivate'))->test(ExitWizard::class, ['employee' => $employee])
        ->set('employee_status_id', (string) EmployeeStatus::idFor('RESIGNED'))
        ->set('exit_reason_id', (string) ExitReason::query()->value('id'))
        ->call('next')
        ->assertSee('Open advance')->assertSee(__('Must be resolved'))
        ->call('confirm')
        ->assertNoRedirect()
        ->assertDispatched('toast');

    expect($employee->fresh()->employee_status_id)->toBe(EmployeeStatus::idFor('ACTIVE'));
});

test('an employee who already left is sent back to the profile', function () {
    $employee = Employee::factory()->withStatus(EmployeeStatus::RESIGNED)->create();

    $this->actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.deactivate'))
        ->get(route('hrm.employees.exit', $employee))->assertRedirect(route('hrm.employees.show', $employee));
});

class WizardBlockingCheck implements EmployeeExitCheck
{
    public function check(Employee $employee): array
    {
        return [new ExitCheckItem('Open advance ৳5,000', null, blocking: true)];
    }
}
