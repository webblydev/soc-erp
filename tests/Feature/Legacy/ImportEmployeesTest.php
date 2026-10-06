<?php

use App\Modules\Hrm\Models\Employee;
use Database\Seeders\Foundation\CompanyProfileSeeder;
use Database\Seeders\Foundation\CurrencySeeder;
use Database\Seeders\Legacy\ImportEmployees;
use Database\Seeders\Legacy\LegacyContext;

beforeEach(function () {
    useLegacyDatabase();
    seedHrm();
    $this->seed([CurrencySeeder::class, CompanyProfileSeeder::class]);
});

test('employees are copied with their job and status', function () {
    legacyRow('tbl_employee', ['id' => 13, 'code' => 'E00013', 'name' => 'Md Rakib Hasan', 'post_id' => 13, 'department_id' => 2, 'gender' => 'male', 'marital_status' => 'unmarred', 'dob' => '1995-04-10', 'phone' => '01711-000013', 'email' => 'Rakib@Example.com', 'father_name' => 'Abdul Hasan', 'status' => 'a', 'added_date' => '2023-10-16 13:00:00']);
    legacyRow('tbl_employee', ['id' => 14, 'code' => 'E00014', 'name' => 'Sadia', 'post_id' => 13, 'department_id' => 2, 'gender' => 'female', 'marital_status' => 'married', 'dob' => '0000-00-00', 'phone' => 'n/a', 'status' => 'd', 'added_date' => '2023-11-01 10:00:00', 'update_date' => '2024-06-30 18:00:00']);

    $context = new LegacyContext;

    expect(app(ImportEmployees::class)->run($context))->toBe(2);

    $rakib = Employee::query()->where('employee_code', 'E00013')->with(['designation', 'department', 'maritalStatus', 'gender', 'status'])->sole();
    $sadia = Employee::query()->where('employee_code', 'E00014')->with('status')->sole();

    expect($rakib)
        ->first_name->toBe('Md Rakib')->last_name->toBe('Hasan')->full_name->toBe('Md Rakib Hasan')
        ->legacy_employee_id->toBe(13)->phone->toBe('01711000013')->official_email->toBe('rakib@example.com')
        ->father_name->toBe('Abdul Hasan')
        ->and($rakib->date_of_birth->toDateString())->toBe('1995-04-10')
        ->and($rakib->joining_date->toDateString())->toBe('2023-10-16')
        ->and($rakib->designation->code)->toBe('PROJECT_ENGINEER')
        ->and($rakib->department->code)->toBe('PROJECT_OPS')
        ->and($rakib->maritalStatus->code)->toBe('SINGLE')
        ->and($rakib->gender->code)->toBe('MALE')
        ->and($rakib->status->code)->toBe('ACTIVE')
        ->and($rakib->events()->count())->toBe(1)
        ->and($context->employees)->toBe([13 => $rakib->id, 14 => $sadia->id]);

    expect($sadia)
        ->first_name->toBe('Sadia')->last_name->toBeNull()->date_of_birth->toBeNull()
        ->phone->toBe('01714678285')->notes->toContain('n/a')
        ->and($sadia->status->code)->toBe('RESIGNED')
        ->and($sadia->exit_date->toDateString())->toBe('2024-06-30')
        ->and($sadia->exitReason)->not->toBeNull()
        ->and($sadia->events()->count())->toBe(2);
});

test('re-running adds nothing but still maps the employees', function () {
    legacyRow('tbl_employee', ['id' => 1, 'code' => 'E00001', 'name' => 'Farid Ahmed', 'post_id' => 1, 'department_id' => 8, 'phone' => '01714678285', 'status' => 'a', 'added_date' => '2023-06-05 15:00:00']);

    app(ImportEmployees::class)->run(new LegacyContext);
    $context = new LegacyContext;

    expect(app(ImportEmployees::class)->run($context))->toBe(0)
        ->and(Employee::query()->count())->toBe(1)
        ->and($context->employees)->toHaveKey(1);
});
