<?php

use App\Models\User;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmploymentEventType;
use Database\Seeders\Hrm\HrmSeeder;

test('the HRM seeder is idempotent and sets the system flags', function () {
    seedHrm();
    $this->seed(HrmSeeder::class);

    expect(Department::query()->count())->toBe(8)
        ->and(EmployeeStatus::query()->where('code', 'ACTIVE')->first())->is_active_employment->toBeTrue()->is_exit->toBeFalse()->is_system->toBeTrue()
        ->and(EmployeeStatus::query()->where('code', 'SUSPENDED')->first())->is_active_employment->toBeFalse()->is_exit->toBeFalse()
        ->and(EmployeeStatus::query()->where('is_exit', true)->pluck('code')->sort()->values()->all())->toBe(['RESIGNED', 'RETIRED', 'TERMINATED'])
        ->and(EmploymentEventType::query()->where('code', 'RETIRED')->exists())->toBeTrue()
        ->and(EmployeeDocumentType::query()->where('has_expiry', true)->pluck('code')->sort()->values()->all())->toBe(['DRIVING_LICENCE', 'IEB_IAB', 'PASSPORT']);
});

test('roles get the HRM grants', function () {
    seedAccessControl();

    $withRole = function (string $role): User {
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return $user->fresh();
    };

    expect($withRole('hr_admin')->hasPermission('hrm.employees.update_salary'))->toBeTrue()
        ->and($withRole('accountant')->hasPermission('hrm.employees.view_salary'))->toBeTrue()
        ->and($withRole('accountant')->hasPermission('hrm.employees.view_full'))->toBeFalse()
        ->and($withRole('engineer')->hasPermission('hrm.employees.view_basic'))->toBeTrue();
});
