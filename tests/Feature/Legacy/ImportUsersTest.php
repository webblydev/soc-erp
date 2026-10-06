<?php

use App\Models\User;
use Database\Seeders\Foundation\CompanyProfileSeeder;
use Database\Seeders\Foundation\CurrencySeeder;
use Database\Seeders\Legacy\ImportEmployees;
use Database\Seeders\Legacy\ImportUsers;
use Database\Seeders\Legacy\LegacyContext;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    useLegacyDatabase();
    seedHrm();
    seedAccessControl();
    $this->seed([CurrencySeeder::class, CompanyProfileSeeder::class]);
    $this->admin = superAdmin(['username' => 'admin', 'name' => 'System Administrator']);

    legacyRow('tbl_employee', ['id' => 2, 'code' => 'E00002', 'name' => 'Farid Ahmed', 'post_id' => 1, 'department_id' => 8, 'phone' => '01714678285', 'status' => 'a', 'added_date' => '2023-06-05 10:00:00']);
    legacyRow('tbl_employee', ['id' => 4, 'code' => 'E00004', 'name' => 'Jamal Hossain', 'post_id' => 5, 'department_id' => 1, 'phone' => '01711000004', 'status' => 'a', 'added_date' => '2023-06-05 10:00:00']);
    legacyRow('tbl_employee', ['id' => 23, 'code' => 'E00023', 'name' => 'Delower Hossain', 'post_id' => 16, 'department_id' => 2, 'phone' => '01711000023', 'status' => 'a', 'added_date' => '2023-06-05 10:00:00']);
    legacyRow('tbl_employee', ['id' => 25, 'code' => 'E00025', 'name' => 'Rezaul Karim', 'post_id' => 17, 'department_id' => 3, 'phone' => '01711000025', 'status' => 'a', 'added_date' => '2023-06-05 10:00:00']);

    legacyRow('tbl_user', ['id' => 1, 'name' => 'Admin', 'user_name' => 'Admin', 'type' => 'a', 'team_name' => 'a', 'status' => 'a', 'employee_id' => 2]);
    legacyRow('tbl_user', ['id' => 3, 'name' => 'Jamal', 'user_name' => 'jamal', 'email' => 'jamal@example.com', 'phone' => '01711000004', 'type' => 't', 'team_name' => 'd', 'status' => 'a', 'employee_id' => 4]);
    legacyRow('tbl_user', ['id' => 4, 'name' => 'Delower', 'user_name' => 'delower', 'phone' => '12345', 'type' => 'u', 'team_name' => 'a', 'status' => 'a', 'employee_id' => 23]);
    legacyRow('tbl_user', ['id' => 10, 'name' => 'Rezaul', 'user_name' => 'rezaul', 'type' => 'u', 'team_name' => 'a', 'status' => 'd', 'employee_id' => 25]);
    legacyRow('tbl_user', ['id' => 30, 'name' => 'LSD', 'user_name' => 'LSD', 'type' => 't', 'team_name' => 'a', 'status' => 'a', 'employee_id' => 2]);

    $this->context = new LegacyContext;
    app(ImportEmployees::class)->run($this->context);
});

test('users get roles, employee links and the dev password', function () {
    expect(app(ImportUsers::class)->run($this->context))->toBe(4);

    $jamal = User::query()->where('username', 'jamal')->sole();
    $delower = User::query()->where('username', 'delower')->sole();
    $rezaul = User::query()->where('username', 'rezaul')->sole();
    $lsd = User::query()->where('username', 'lsd')->sole();

    expect($jamal->hasRole('sales_manager'))->toBeTrue()
        ->and($jamal->employee->employee_code)->toBe('E00004')
        ->and($jamal->email)->toBe('jamal@example.com')
        ->and(Hash::check('password', $jamal->password))->toBeTrue()
        ->and($jamal->must_change_password)->toBeFalse()
        ->and($delower->hasRole('engineer'))->toBeTrue()
        ->and($delower->phone)->toBeNull()
        ->and($rezaul->hasRole('sales_executive'))->toBeTrue()
        ->and($rezaul->is_active)->toBeFalse()
        ->and($lsd->employee_id)->toBeNull()
        ->and($lsd->hasRole('sales_manager'))->toBeTrue();
});

test('a v1 username matching an existing user reuses it unchanged', function () {
    app(ImportUsers::class)->run($this->context);

    expect($this->context->users[1])->toBe($this->admin->id)
        ->and($this->context->usernames['admin'])->toBe($this->admin->id)
        ->and($this->admin->fresh()->name)->toBe('System Administrator')
        ->and($this->admin->fresh()->employee_id)->toBeNull();
});

test('re-running adds nothing but still maps the users', function () {
    app(ImportUsers::class)->run($this->context);
    $context = new LegacyContext;
    app(ImportEmployees::class)->run($context);

    expect(app(ImportUsers::class)->run($context))->toBe(0)
        ->and($context->users)->toHaveCount(5);
});

test('a v1 username the user form would refuse is turned into a slug', function () {
    legacyRow('tbl_user', ['id' => 8, 'name' => 'HR & Admin', 'user_name' => 'HR & Admin', 'type' => 't', 'team_name' => 'c', 'status' => 'a']);

    app(ImportUsers::class)->run($this->context);

    $user = User::query()->where('username', 'hr-admin')->sole();

    expect($this->context->users[8])->toBe($user->id)
        ->and($this->context->usernames['hr & admin'])->toBe($user->id)
        ->and(app(ImportUsers::class)->run($this->context))->toBe(0);
});
