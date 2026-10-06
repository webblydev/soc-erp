<?php

use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\UnitKind;
use App\Modules\Foundation\Actions\DeleteLookup;
use App\Modules\Foundation\Actions\SaveLookup;
use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Foundation\Services\Navigation;
use Livewire\Livewire;

function businessLineInput(array $overrides = []): array
{
    return [
        'code' => 'BD', 'name' => 'Building Design', 'project_prefix' => 'SOC-BD', 'is_internal' => false, 'is_active' => true,
        ...$overrides,
    ];
}

test('the project prefix is upper-cased and limited to A-Z 0-9 - & (CT-BR-01)', function () {
    $line = app(SaveLookup::class)->handle('business_lines', businessLineInput(['project_prefix' => ' soc-bd&ra ']));

    expect($line->project_prefix)->toBe('SOC-BD&RA');

    expectValidationError(fn () => app(SaveLookup::class)->handle('business_lines', businessLineInput(['code' => 'X', 'project_prefix' => 'SOC BD'])), 'project_prefix');
    expectValidationError(fn () => app(SaveLookup::class)->handle('business_lines', businessLineInput(['code' => 'Y', 'project_prefix' => str_repeat('A', 31)])), 'project_prefix');
    expectValidationError(fn () => app(SaveLookup::class)->handle('business_lines', businessLineInput(['code' => 'Z', 'project_prefix' => ''])), 'project_prefix');
});

test('the project prefix is unique, ignoring the row being edited', function () {
    $line = app(SaveLookup::class)->handle('business_lines', businessLineInput());

    expectValidationError(fn () => app(SaveLookup::class)->handle('business_lines', businessLineInput(['code' => 'BD2', 'project_prefix' => 'soc-bd'])), 'project_prefix');

    app(SaveLookup::class)->handle('business_lines', businessLineInput(['name' => 'Design']), $line);
    expect($line->fresh()->name)->toBe('Design');
});

test('a unit needs an active unit kind but keeps an inactive one it already has', function () {
    $area = UnitKind::factory()->create();
    $retired = UnitKind::factory()->create(['is_active' => false]);
    $input = ['code' => 'sft', 'name' => 'Square foot', 'symbol' => 'sft', 'is_active' => true];

    expectValidationError(fn () => app(SaveLookup::class)->handle('units', [...$input, 'unit_kind_id' => '']), 'unit_kind_id');
    expectValidationError(fn () => app(SaveLookup::class)->handle('units', [...$input, 'unit_kind_id' => $retired->id]), 'unit_kind_id');
    expectValidationError(fn () => app(SaveLookup::class)->handle('units', [...$input, 'unit_kind_id' => $area->id, 'symbol' => str_repeat('s', 16)]), 'symbol');

    $unit = app(SaveLookup::class)->handle('units', [...$input, 'unit_kind_id' => (string) $area->id]);
    expect($unit->unit_kind_id)->toEqual($area->id);

    $old = Unit::factory()->create(['unit_kind_id' => $retired->id]);
    app(SaveLookup::class)->handle('units', ['code' => $old->code, 'name' => 'Renamed', 'symbol' => $old->symbol, 'unit_kind_id' => $retired->id, 'is_active' => true], $old);
    expect($old->fresh()->name)->toBe('Renamed');
});

test('a unit kind in use cannot be deleted', function () {
    $unit = Unit::factory()->create();

    expectValidationError(fn () => app(DeleteLookup::class)->handle('unit_kinds', $unit->kind), 'row');
});

test('catalog lookups are gated by their catalog permission prefix (C8)', function () {
    $this->actingAs(userWithPermissions('catalog.master_data.view'));
    $this->get(route('admin.master-data.show', 'service_categories'))->assertOk();
    $this->get(route('admin.master-data.show', 'business_lines'))->assertForbidden();

    $this->actingAs(userWithPermissions('catalog.business_lines.view'));
    $this->get(route('admin.master-data.show', 'business_lines'))->assertOk()->assertSee('Catalog', false);
});

test('the unit form renders a unit kind select and saves it', function () {
    $kind = UnitKind::factory()->create(['name' => 'Area']);

    Livewire::actingAs(userWithPermissions('catalog.units.view', 'catalog.units.create'))
        ->test(MasterData::class, ['table' => 'units'])
        ->call('create')
        ->assertSee('Area')
        ->set('form.code', 'sqm')
        ->set('form.name', 'Square metre')
        ->set('form.symbol', 'sqm')
        ->set('form.unit_kind_id', (string) $kind->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Unit::query()->where('code', 'sqm')->value('unit_kind_id'))->toEqual($kind->id);
});

test('the business lines and units entry points redirect to master data', function () {
    $this->actingAs(userWithPermissions('catalog.business_lines.view', 'catalog.units.view'));

    $this->get(route('catalog.business-lines.index'))->assertRedirect(route('admin.master-data.show', 'business_lines'));
    $this->get(route('catalog.units.index'))->assertRedirect(route('admin.master-data.show', 'units'));
});

test('the business lines entry point needs its view permission', function () {
    $this->actingAs(userWithPermissions('catalog.units.view'))->get(route('catalog.business-lines.index'))->assertForbidden();
});

test('existing lookups keep working without the new keys', function () {
    $branch = app(SaveLookup::class)->handle('branches', ['code' => 'ctg', 'name' => 'Chattogram', 'is_active' => true, 'is_head_office' => false]);

    expect($branch->code)->toBe('ctg');
});

test('the catalog nav group lists only what the user may view', function () {
    $user = userWithPermissions('catalog.units.view');

    $catalog = collect(app(Navigation::class)->for($user))->firstWhere('key', 'catalog');

    expect(collect($catalog['items'])->pluck('label')->all())->toBe(['Units']);
});
