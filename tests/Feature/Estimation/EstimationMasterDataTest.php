<?php

use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Foundation\Services\Navigation;
use App\Support\Lookups\LookupRegistry;
use Livewire\Livewire;

beforeEach(fn () => seedEstimation());

test('the editable estimation lookups are registered under estimation.master_data (spec E23)', function () {
    $tables = collect(app(LookupRegistry::class)->all())->where('module', 'estimation');

    expect($tables->keys()->all())->toEqualCanonicalizing(['cost_categories', 'inspection_types', 'finding_categories', 'finding_severities'])
        ->and($tables->pluck('permission')->unique()->all())->toBe(['estimation.master_data'])
        ->and($tables->pluck('extra_fields')->flatten()->all())->toBe([])
        ->and(fn () => app(LookupRegistry::class)->get('estimate_statuses'))->toThrow(InvalidArgumentException::class);
});

test('the master data screen needs estimation.master_data.view', function () {
    $this->actingAs(userWithPermissions('estimation.estimates.view'));
    $this->get(route('admin.master-data.show', 'finding_categories'))->assertForbidden();

    $this->actingAs(userWithPermissions('estimation.master_data.view'));
    $this->get(route('admin.master-data.show', 'finding_categories'))->assertOk()->assertSee('Workmanship');
});

test('a severity colour can be changed but its follow-up flag stays', function () {
    $high = FindingSeverity::query()->where('code', FindingSeverity::HIGH)->firstOrFail();

    Livewire::actingAs(userWithPermissions('estimation.master_data.view', 'estimation.master_data.update'))
        ->test(MasterData::class, ['table' => 'finding_severities'])
        ->call('edit', $high->id)
        ->set('form.name', 'High risk')
        ->call('save')
        ->assertHasNoErrors();

    expect($high->fresh())->name->toBe('High risk')->requires_follow_up->toBeTrue();
});

test('the master data items show in the Estimation & Site group', function () {
    $user = userWithPermissions('estimation.master_data.view');

    $group = collect(app(Navigation::class)->for($user))->firstWhere('key', 'estimation');

    expect(collect($group['items'] ?? [])->firstWhere('label', 'Master data')['children'] ?? [])->toHaveCount(4);
});
