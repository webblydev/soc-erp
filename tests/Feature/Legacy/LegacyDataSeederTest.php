<?php

use App\Modules\Crm\Models\Lead;
use App\Modules\Hrm\Models\Employee;
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
