<?php

use App\Models\User;
use App\Modules\Hrm\Jobs\NotifyExpiringDocuments;
use App\Modules\Hrm\Jobs\NotifyProbationEnding;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeDocument;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmployeeType;
use App\Modules\Hrm\Notifications\DocumentExpiring;
use App\Modules\Hrm\Notifications\ProbationEnding;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedHrm();
    Notification::fake();
    $this->hr = userWithPermissions('hrm.documents.manage', 'hrm.employees.update');
});

test('a passport expiring in 20 days notifies HR and the employee once (HR-AC-05)', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->linkedTo($user)->create();
    $passport = EmployeeDocumentType::idFor('PASSPORT');
    $soon = EmployeeDocument::factory()->for($employee)->expiringOn(today()->addDays(20)->toDateString())->create(['employee_document_type_id' => $passport]);
    EmployeeDocument::factory()->for($employee)->expiringOn(today()->addDays(45)->toDateString())->create(['employee_document_type_id' => $passport]);
    $gone = Employee::factory()->withStatus(EmployeeStatus::RESIGNED)->create();
    EmployeeDocument::factory()->for($gone)->expiringOn(today()->addDays(5)->toDateString())->create(['employee_document_type_id' => $passport]);

    (new NotifyExpiringDocuments)->handle();
    (new NotifyExpiringDocuments)->handle();

    Notification::assertSentToTimes($this->hr, DocumentExpiring::class, 1);
    Notification::assertSentToTimes($user->fresh(), DocumentExpiring::class, 1);
    expect($soon->fresh()->expiry_notified_at)->not->toBeNull();
});

test('probation ending within 15 days notifies HR and the manager once', function () {
    $managerUser = User::factory()->create();
    $manager = Employee::factory()->linkedTo($managerUser)->create();
    Employee::factory()->create(['manager_id' => $manager->id, 'employee_type_id' => EmployeeType::idFor('PROBATION'), 'confirmation_date' => today()->addDays(10)]);
    Employee::factory()->create(['employee_type_id' => EmployeeType::idFor('PROBATION'), 'confirmation_date' => today()->addDays(30)]);
    Employee::factory()->create(['employee_type_id' => EmployeeType::idFor('PERMANENT'), 'confirmation_date' => today()->addDays(5)]);

    (new NotifyProbationEnding)->handle();
    (new NotifyProbationEnding)->handle();

    Notification::assertSentToTimes($this->hr, ProbationEnding::class, 1);
    Notification::assertSentToTimes($managerUser->fresh(), ProbationEnding::class, 1);
});
