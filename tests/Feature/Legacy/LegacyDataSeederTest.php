<?php

use App\Models\User;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Hrm\Models\Employee;
use Database\Seeders\Catalog\CatalogSeeder;
use Database\Seeders\Foundation\CompanyProfileSeeder;
use Database\Seeders\Foundation\CurrencySeeder;
use Database\Seeders\Legacy\LegacyDataSeeder;
use Illuminate\Support\Facades\Schema;

test('without a legacy database the seeder writes nothing', function () {
    config(['database.connections.legacy.database' => null]);

    $this->seed(LegacyDataSeeder::class);

    expect(Lead::query()->count())->toBe(0)->and(Employee::query()->count())->toBe(0);
});

test('the client reference columns exist', function () {
    expect(Schema::hasColumn('leads', 'legacy_client_ref'))->toBeTrue()
        ->and(Schema::hasColumn('customers', 'legacy_client_ref'))->toBeTrue();
});

test('the seeder copies every entity in one run', function () {
    useLegacyDatabase();
    seedCrm();
    seedHrm();
    seedAccessControl();
    $this->seed([CatalogSeeder::class, CurrencySeeder::class, CompanyProfileSeeder::class]);
    superAdmin(['username' => 'admin']);

    legacyRow('tbl_employee', ['id' => 39, 'code' => 'E00039', 'name' => 'Kamal Hossain', 'post_id' => 10, 'department_id' => 3, 'phone' => '01711000039', 'status' => 'a', 'added_date' => '2024-01-01 09:00:00']);
    legacyRow('tbl_user', ['id' => 16, 'name' => 'MSD', 'user_name' => 'msd', 'type' => 't', 'team_name' => 'a', 'status' => 'a', 'employee_id' => 39]);
    legacyRow('tbl_client', ['id' => 1, 'client_id' => 'SOC-CON-0001', 'client_name' => 'Client One', 'phone' => '01711000001', 'requirement' => '1', 'status' => 's', 'source' => 'L', 'add_by' => 16, 'add_time' => '2024-02-01 10:00:00']);
    legacyRow('tbl_clientdetails', ['id' => 1, 'client_id' => 1, 'note' => 'Signed', 'added_by' => 'msd', 'added_date' => '2024-02-02 10:00:00']);

    $this->seed(LegacyDataSeeder::class);
    $this->seed(LegacyDataSeeder::class);

    expect(Employee::query()->count())->toBe(1)
        ->and(User::query()->where('username', 'msd')->value('employee_id'))->toBe(Employee::query()->value('id'))
        ->and(SalesTeam::query()->count())->toBe(1)
        ->and(Lead::query()->count())->toBe(1)
        ->and(Customer::query()->count())->toBe(1)
        ->and(CrmActivity::query()->count())->toBe(1);
});
