<?php

use App\Models\User;
use App\Modules\Hrm\Actions\ExitEmployee;
use App\Modules\Hrm\Actions\RejoinEmployee;
use App\Modules\Hrm\Contracts\EmployeeExitCheck;
use App\Modules\Hrm\Events\EmployeeDeactivated;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmploymentEventType;
use App\Modules\Hrm\Models\ExitReason;
use App\Modules\Hrm\Notifications\ExitChecklist;
use App\Modules\Hrm\Services\ExitCheckItem;
use App\Modules\Hrm\Services\ExitChecks;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedHrm();
    $this->actor = userWithPermissions('hrm.employees.view_basic', 'hrm.employees.deactivate');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function exitInput(array $overrides = []): array
{
    return ['employee_status_id' => EmployeeStatus::idFor('RESIGNED'), 'exit_date' => today()->toDateString(), 'exit_reason_id' => ExitReason::query()->value('id'), 'note' => 'Moving abroad', ...$overrides];
}

test('exiting sets status, date, reason and event, and deactivates the linked user (HR-AC-03)', function () {
    Notification::fake();
    $hr = userWithPermissions('hrm.employees.deactivate');
    $user = User::factory()->create();
    $employee = Employee::factory()->linkedTo($user)->create();

    app(ExitEmployee::class)->handle($this->actor, $employee, exitInput());

    expect($employee->fresh())->employee_status_id->toBe(EmployeeStatus::idFor('RESIGNED'))->exit_date->not->toBeNull()
        ->and($employee->events()->first()->employment_event_type_id)->toBe(EmploymentEventType::idFor('RESIGNED'))
        ->and($user->fresh()->is_active)->toBeFalse();
    Notification::assertSentTo($hr, ExitChecklist::class);
    Notification::assertNotSentTo($this->actor, ExitChecklist::class);
});

test('the exit date cannot precede joining and the status must be an exit status', function () {
    $employee = Employee::factory()->create();

    expectValidationError(fn () => app(ExitEmployee::class)->handle($this->actor, $employee, exitInput(['exit_date' => $employee->joining_date->subDay()->toDateString()])), 'exit_date');
    expectValidationError(fn () => app(ExitEmployee::class)->handle($this->actor, $employee, exitInput(['employee_status_id' => EmployeeStatus::idFor('ON_LEAVE')])), 'employee_status_id');
});

test('a blocking exit check stops the exit', function () {
    app(ExitChecks::class)->register(BlockingCheck::class);
    $employee = Employee::factory()->create();

    expect(app(ExitChecks::class)->run($employee))->toHaveCount(1);
    expectValidationError(fn () => app(ExitEmployee::class)->handle($this->actor, $employee, exitInput()), 'checks');
    expect($employee->fresh()->employee_status_id)->toBe(EmployeeStatus::idFor('ACTIVE'));
});

test('exiting your own employee record is refused and nothing changes', function () {
    $employee = Employee::factory()->linkedTo($this->actor)->create();

    expectValidationError(fn () => app(ExitEmployee::class)->handle($this->actor->fresh(), $employee, exitInput()), 'user');
    expect($employee->fresh()->exit_date)->toBeNull()
        ->and($employee->events()->count())->toBe(0)
        ->and($this->actor->fresh()->is_active)->toBeTrue();
});

test('an exited employee cannot exit again and fires EmployeeDeactivated once', function () {
    Event::fake([EmployeeDeactivated::class]);
    $employee = Employee::factory()->create();

    app(ExitEmployee::class)->handle($this->actor, $employee, exitInput());
    expectValidationError(fn () => app(ExitEmployee::class)->handle($this->actor, $employee->fresh(), exitInput()), 'employee_status_id');

    Event::assertDispatchedTimes(EmployeeDeactivated::class, 1);
});

test('rejoining restores ACTIVE and records REJOINED', function () {
    $employee = Employee::factory()->create();
    app(ExitEmployee::class)->handle($this->actor, $employee, exitInput());

    app(RejoinEmployee::class)->handle($this->actor, $employee->fresh(), ['effective_date' => today()->toDateString(), 'note' => null]);

    expect($employee->fresh())->employee_status_id->toBe(EmployeeStatus::idFor('ACTIVE'))->exit_date->toBeNull()->exit_reason_id->toBeNull()
        ->and($employee->events()->first()->employment_event_type_id)->toBe(EmploymentEventType::idFor('REJOINED'));

    expectValidationError(fn () => app(RejoinEmployee::class)->handle($this->actor, $employee->fresh(), ['effective_date' => today()->toDateString()]), 'employee_status_id');
});

test('the linked user check lists the login that will be deactivated', function () {
    $user = User::factory()->create(['username' => 'rafiq']);
    $employee = Employee::factory()->linkedTo($user)->create();

    $items = app(ExitChecks::class)->run($employee->fresh());

    expect($items)->toHaveCount(1)->and($items[0]->label)->toContain('rafiq')->and($items[0]->blocking)->toBeFalse();
});

class BlockingCheck implements EmployeeExitCheck
{
    public function check(Employee $employee): array
    {
        return [new ExitCheckItem('Open advance ৳5,000', null, blocking: true)];
    }
}
