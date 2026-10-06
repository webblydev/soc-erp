<?php

use App\Modules\Hrm\Actions\RecordEmploymentEvent;
use App\Modules\Hrm\Actions\UpdateEmployee;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentEventType;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    seedHrm();
    $this->actor = userWithPermissions('hrm.employees.view_basic', 'hrm.employees.view_full', 'hrm.employees.update', 'hrm.employees.view_salary', 'hrm.employees.update_salary', 'hrm.history.manage');
    $this->employee = Employee::factory()->create(['designation_id' => Designation::idFor('SITE_ENGINEER')]);
    $this->employee->forceFill(['gross_salary' => '30000'])->save();
});

test('changing the designation without an event is refused', function () {
    expectValidationError(fn () => app(UpdateEmployee::class)->handle($this->actor, $this->employee, sameJob($this->employee, ['designation_id' => Designation::idFor('PROJECT_ENGINEER')])), 'event');

    expect($this->employee->fresh()->designation_id)->toBe(Designation::idFor('SITE_ENGINEER'));
});

test('a promotion records from and to values and applies them (HR-AC-02)', function () {
    app(UpdateEmployee::class)->handle($this->actor, $this->employee, sameJob($this->employee, ['designation_id' => Designation::idFor('PROJECT_ENGINEER'), 'gross_salary' => '38000']), [
        'employment_event_type_id' => EmploymentEventType::idFor('PROMOTED'), 'effective_date' => today()->toDateString(), 'note' => 'Annual review',
    ]);

    $event = $this->employee->events()->first();

    expect($this->employee->fresh())->designation_id->toBe(Designation::idFor('PROJECT_ENGINEER'))->gross_salary->toBe('38000.00')
        ->and($event)->from_designation_id->toBe(Designation::idFor('SITE_ENGINEER'))->to_designation_id->toBe(Designation::idFor('PROJECT_ENGINEER'))
        ->from_salary->toBe('30000.00')->to_salary->toBe('38000.00')->approved_by->toBe($this->actor->id)->note->toBe('Annual review');
});

test('a salary change needs update_salary', function () {
    $actor = userWithPermissions('hrm.employees.view_basic', 'hrm.employees.update', 'hrm.history.manage');

    $event = app(RecordEmploymentEvent::class)->handle($actor, $this->employee, ['employment_event_type_id' => EmploymentEventType::idFor('SALARY_REVISED'), 'effective_date' => today()->toDateString(), 'gross_salary' => '90000']);

    expect($this->employee->fresh()->gross_salary)->toBe('30000.00')->and($event->to_salary)->toBeNull();
});

test('a CONFIRMED event sets the confirmation date', function () {
    app(RecordEmploymentEvent::class)->handle($this->actor, $this->employee, ['employment_event_type_id' => EmploymentEventType::idFor('CONFIRMED'), 'effective_date' => today()->toDateString()]);

    expect($this->employee->fresh()->confirmation_date->toDateString())->toBe(today()->toDateString());
});

test('the effective date cannot precede joining and exit-type events are refused here', function () {
    expectValidationError(fn () => app(RecordEmploymentEvent::class)->handle($this->actor, $this->employee, ['employment_event_type_id' => EmploymentEventType::idFor('CONFIRMED'), 'effective_date' => $this->employee->joining_date->subDay()->toDateString()]), 'effective_date');
    expectValidationError(fn () => app(RecordEmploymentEvent::class)->handle($this->actor, $this->employee, ['employment_event_type_id' => EmploymentEventType::idFor('RESIGNED'), 'effective_date' => today()->toDateString()]), 'employment_event_type_id');
});

test('recording an event needs history.manage or update', function () {
    expect(fn () => app(RecordEmploymentEvent::class)->handle(userWithPermissions('hrm.employees.view_basic'), $this->employee, ['employment_event_type_id' => EmploymentEventType::idFor('CONFIRMED'), 'effective_date' => today()->toDateString()]))
        ->toThrow(AuthorizationException::class);
});
