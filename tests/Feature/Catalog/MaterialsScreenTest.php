<?php

use App\Modules\Catalog\Livewire\Materials\Form;
use App\Modules\Catalog\Livewire\Materials\Index;
use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\MaterialCategory;
use App\Modules\Catalog\Models\Unit;
use Livewire\Livewire;

test('each materials route needs its permission', function () {
    $material = Material::factory()->create();

    $this->actingAs(userWithPermissions('catalog.services.view'));
    $this->get(route('catalog.materials.index'))->assertForbidden();

    $this->actingAs(userWithPermissions('catalog.materials.view'));
    $this->get(route('catalog.materials.index'))->assertOk()->assertSee($material->name);
    $this->get(route('catalog.materials.create'))->assertForbidden();
    $this->get(route('catalog.materials.edit', $material))->assertForbidden();

    $this->actingAs(userWithPermissions('catalog.materials.view', 'catalog.materials.create', 'catalog.materials.update'));
    $this->get(route('catalog.materials.create'))->assertOk();
    $this->get(route('catalog.materials.edit', $material))->assertOk();
});

test('the list filters by category, unit and status', function () {
    $cement = MaterialCategory::factory()->create();
    $bag = Unit::factory()->create(['symbol' => 'bag']);
    Material::factory()->create(['name' => 'OPC cement', 'material_category_id' => $cement->id, 'unit_id' => $bag->id]);
    Material::factory()->create(['name' => 'River sand']);
    Material::factory()->create(['name' => 'Old stock', 'is_active' => false]);

    $component = Livewire::actingAs(userWithPermissions('catalog.materials.view'))->test(Index::class);

    $component->assertSee('OPC cement')->assertSee('River sand')->assertDontSee('Old stock');
    $component->set('filters.category', (string) $cement->id)->assertSee('OPC cement')->assertDontSee('River sand');
    $component->set('filters', ['unit' => (string) $bag->id])->assertSee('OPC cement')->assertDontSee('River sand');
    $component->set('filters', ['active' => '0'])->assertSee('Old stock')->assertDontSee('OPC cement');
});

test('a material is created through the form', function () {
    $category = MaterialCategory::factory()->create();
    $unit = Unit::factory()->create();

    Livewire::actingAs(userWithPermissions('catalog.materials.view', 'catalog.materials.create'))
        ->test(Form::class)
        ->set('code', 'cem-opc')
        ->set('name', 'OPC cement')
        ->set('material_category_id', (string) $category->id)
        ->set('unit_id', (string) $unit->id)
        ->set('standard_rate', '550')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('catalog.materials.index'));

    expect(Material::query()->where('code', 'CEM-OPC')->value('standard_rate'))->toBe('550.0000');
});

test('the edit form loads the material, saves changes and shows its history', function () {
    $material = Material::factory()->create(['standard_rate' => '550.2500']);

    Livewire::actingAs(userWithPermissions('catalog.materials.view', 'catalog.materials.update'))
        ->test(Form::class, ['material' => $material])
        ->assertSet('standard_rate', '550.25')
        ->assertSeeLivewire('foundation.history')
        ->set('name', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($material->fresh()->name)->toBe('Renamed');
});

test('form errors show inline', function () {
    Livewire::actingAs(userWithPermissions('catalog.materials.view', 'catalog.materials.create'))
        ->test(Form::class)
        ->set('standard_rate', '-1')
        ->call('save')
        ->assertHasErrors(['code', 'name', 'material_category_id', 'unit_id', 'standard_rate']);
});
