<?php

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Actions\CreateLead;
use App\Modules\Crm\Livewire\Leads\Show;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    seedCrm();
    seedHrm();
    $this->actor = userWithPermissions('crm.leads.view_own', 'crm.leads.create');
    $this->design = Service::factory()->create();
    $this->survey = Service::factory()->create();
});

test('an employee referrer must be an employee', function () {
    $employee = Employee::factory()->create(['full_name' => 'Referring Employee']);
    $input = leadInput(['lead_source_id' => LeadSource::idFor('REFERENCE'), 'referrer_type' => 'employee']);

    expectValidationError(fn () => app(CreateLead::class)->handle($this->actor, [...$input, 'referrer_id' => $employee->id + 1000]), 'referrer_id');

    $lead = app(CreateLead::class)->handle($this->actor, [...$input, 'referrer_id' => $employee->id]);

    expect($lead->referrerEmployee->is($employee))->toBeTrue()
        ->and($lead->referrerLabel())->toBe('Referring Employee');
});

test('the lead page links an employee referrer to the profile for HR viewers', function () {
    $employee = Employee::factory()->create(['full_name' => 'Referring Employee']);
    $viewer = userWithPermissions('crm.leads.view_all', 'hrm.employees.view_basic');
    $lead = Lead::factory()->create(['referrer_type' => 'employee', 'referrer_id' => $employee->id]);

    Livewire::actingAs($viewer)->test(Show::class, ['lead' => $lead])
        ->assertSee('Referring Employee')->assertSee(route('hrm.employees.show', $employee));
});

test('the data migration keeps the old user name and clears the id', function () {
    $user = User::factory()->create(['name' => 'Old Referrer']);
    $lead = Lead::factory()->create();
    DB::table('leads')->where('id', $lead->id)->update(['referrer_type' => 'employee', 'referrer_id' => $user->id, 'referrer_name' => null]);

    $migration = require database_path('migrations/hrm/2026_10_08_100400_move_employee_referrers_to_employees.php');
    $migration->up();

    expect(DB::table('leads')->where('id', $lead->id)->first())->referrer_id->toBeNull()->referrer_name->toBe('Old Referrer');
});
