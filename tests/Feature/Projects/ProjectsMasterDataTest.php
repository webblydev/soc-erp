<?php

use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Projects\Models\ApprovalType;
use App\Support\Lookups\LookupRegistry;
use Livewire\Livewire;

beforeEach(fn () => seedProjects());

test('the projects lookups are registered under projects.master_data', function () {
    $tables = collect(app(LookupRegistry::class)->all())->where('module', 'projects');

    expect($tables->keys()->all())->toEqualCanonicalizing([
        'project_types', 'project_statuses', 'project_phases', 'project_roles', 'task_types', 'task_statuses',
        'task_priorities', 'approval_authorities', 'approval_types', 'approval_statuses', 'hold_reasons',
    ])->and($tables->pluck('permission')->unique()->all())->toBe(['projects.master_data']);
});

test('status flags are not editable on the master data screen (spec P23)', function () {
    $registry = app(LookupRegistry::class);

    expect($registry->get('project_statuses')['extra_fields'])->toBe([])
        ->and($registry->get('task_statuses')['extra_fields'])->toBe([])
        ->and($registry->get('approval_statuses')['extra_fields'])->toBe([])
        ->and($registry->get('project_types')['extra_fields'])->toHaveKeys(['is_internal', 'is_billable']);
});

test('the master data screen needs projects.master_data.view', function () {
    $this->actingAs(userWithPermissions('projects.projects.view_own'));
    $this->get(route('admin.master-data.show', 'approval_authorities'))->assertForbidden();

    $this->actingAs(userWithPermissions('projects.master_data.view'));
    $this->get(route('admin.master-data.show', 'approval_authorities'))->assertOk()->assertSee('RAJUK');
});

test('an approval type keeps its default checklist and typical days', function () {
    $type = ApprovalType::query()->where('code', 'FIRE_NOC')->firstOrFail();

    Livewire::actingAs(userWithPermissions('projects.master_data.view', 'projects.master_data.update'))
        ->test(MasterData::class, ['table' => 'approval_types'])
        ->call('edit', $type->id)
        ->set('form.typical_days', '21')
        ->set('form.default_checklist', "Fire drawings\nOwner NID")
        ->call('save')
        ->assertHasNoErrors();

    expect($type->fresh())->typical_days->toBe(21)->default_checklist->toBe("Fire drawings\nOwner NID");
});
