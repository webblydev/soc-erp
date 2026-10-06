<?php

use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;

test('catalog permissions are seeded from the manifest', function () {
    seedAccessControl();

    expect(Permission::query()->where('module', 'catalog')->pluck('name')->all())->toEqualCanonicalizing([
        'catalog.business_lines.view', 'catalog.business_lines.create', 'catalog.business_lines.update', 'catalog.business_lines.deactivate',
        'catalog.services.view', 'catalog.services.create', 'catalog.services.update', 'catalog.services.delete', 'catalog.services.export',
        'catalog.units.view', 'catalog.units.create', 'catalog.units.update', 'catalog.units.deactivate',
        'catalog.work_items.view', 'catalog.work_items.create', 'catalog.work_items.update', 'catalog.work_items.delete', 'catalog.work_items.import', 'catalog.work_items.export',
        'catalog.materials.view', 'catalog.materials.create', 'catalog.materials.update', 'catalog.materials.delete', 'catalog.materials.import', 'catalog.materials.export',
        'catalog.master_data.view', 'catalog.master_data.create', 'catalog.master_data.update', 'catalog.master_data.deactivate',
    ]);
});

test('default roles get the catalog grants of the spec', function () {
    seedAccessControl();

    $grants = fn (string $role): array => Role::query()->where('code', $role)->firstOrFail()
        ->permissions()->where('module', 'catalog')->pluck('name')->all();

    expect($grants('management'))->toHaveCount(29)
        ->and($grants('project_manager'))->toContain('catalog.services.view', 'catalog.work_items.import', 'catalog.materials.update')
        ->not->toContain('catalog.services.update', 'catalog.business_lines.create')
        ->and($grants('accountant'))->each->toEndWith('.view')
        ->and($grants('sales_executive'))->toContain('catalog.services.view')->each->toEndWith('.view')
        ->and($grants('engineer'))->toHaveCount(6);
});
