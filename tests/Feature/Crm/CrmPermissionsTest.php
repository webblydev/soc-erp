<?php

use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;

test('crm permissions are seeded from the manifest', function () {
    seedAccessControl();

    expect(Permission::query()->where('module', 'crm')->pluck('name')->all())->toEqualCanonicalizing([
        'crm.leads.view_own', 'crm.leads.view_team', 'crm.leads.view_all', 'crm.leads.create', 'crm.leads.update',
        'crm.leads.delete', 'crm.leads.assign', 'crm.leads.convert', 'crm.leads.export', 'crm.leads.import',
        'crm.activities.view_own', 'crm.activities.view_team', 'crm.activities.view_all',
        'crm.activities.create', 'crm.activities.update', 'crm.activities.delete',
        'crm.customers.view_own', 'crm.customers.view_team', 'crm.customers.view_all', 'crm.customers.create',
        'crm.customers.update', 'crm.customers.update_finance', 'crm.customers.delete', 'crm.customers.export', 'crm.customers.merge',
        'crm.teams.view', 'crm.teams.manage',
        'crm.master_data.view', 'crm.master_data.create', 'crm.master_data.update', 'crm.master_data.deactivate',
        'crm.reports.view',
    ]);
});

test('default roles get the crm grants of the spec', function () {
    seedAccessControl();

    $grants = fn (string $role): array => Role::query()->where('code', $role)->firstOrFail()
        ->permissions()->where('module', 'crm')->pluck('name')->all();

    expect($grants('management'))->toContain('crm.leads.view_all', 'crm.leads.delete', 'crm.customers.merge', 'crm.teams.manage')
        ->not->toContain('crm.leads.view_own', 'crm.leads.view_team')
        ->and($grants('sales_manager'))->toContain('crm.leads.view_team', 'crm.leads.assign', 'crm.activities.delete', 'crm.teams.view')
        ->not->toContain('crm.leads.view_all', 'crm.leads.delete', 'crm.customers.merge', 'crm.teams.manage')
        ->and($grants('sales_executive'))->toContain('crm.leads.view_own', 'crm.leads.convert', 'crm.customers.update')
        ->not->toContain('crm.leads.assign', 'crm.leads.export', 'crm.activities.delete')
        ->and($grants('accountant'))->toEqualCanonicalizing(['crm.customers.view_all', 'crm.customers.update_finance', 'crm.customers.export'])
        ->and($grants('project_manager'))->toEqualCanonicalizing(['crm.customers.view_all', 'crm.activities.view_own', 'crm.activities.create', 'crm.activities.update'])
        ->and($grants('engineer'))->toEqualCanonicalizing(['crm.customers.view_own', 'crm.activities.view_own', 'crm.activities.create', 'crm.activities.update']);
});

test('the view gates accept any of own, team or all', function (string $permission, string $gate) {
    expect(userWithPermissions($permission)->can($gate))->toBeTrue()
        ->and(userWithPermissions('crm.teams.view')->can($gate))->toBeFalse();
})->with([
    ['crm.leads.view_own', 'crm.leads.view'],
    ['crm.leads.view_team', 'crm.leads.view'],
    ['crm.customers.view_all', 'crm.customers.view'],
    ['crm.activities.view_own', 'crm.activities.view'],
]);
