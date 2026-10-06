<?php

use App\Modules\Catalog\Livewire\Services\Form;
use App\Modules\Catalog\Livewire\Services\Index;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\PricingBasis;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceCategory;
use Livewire\Livewire;

test('each services route needs its permission', function () {
    $service = Service::factory()->create();

    $this->actingAs(userWithPermissions('catalog.work_items.view'));
    $this->get(route('catalog.services.index'))->assertForbidden();

    $this->actingAs(userWithPermissions('catalog.services.view'));
    $this->get(route('catalog.services.index'))->assertOk()->assertSee($service->name);
    $this->get(route('catalog.services.create'))->assertForbidden();
    $this->get(route('catalog.services.edit', $service))->assertForbidden();

    $this->actingAs(userWithPermissions('catalog.services.view', 'catalog.services.create', 'catalog.services.update'));
    $this->get(route('catalog.services.create'))->assertOk();
    $this->get(route('catalog.services.edit', $service))->assertOk();
});

test('the list searches code and name and filters by category, business line and status', function () {
    $design = ServiceCategory::factory()->create();
    $line = BusinessLine::factory()->create();
    Service::factory()->create(['code' => 'BD', 'name' => 'Building Design Work', 'service_category_id' => $design->id, 'business_line_id' => $line->id]);
    Service::factory()->create(['code' => 'SOIL', 'name' => 'Soil Test Work']);
    Service::factory()->create(['code' => 'OLD', 'name' => 'Retired Work', 'is_active' => false]);

    $component = Livewire::actingAs(userWithPermissions('catalog.services.view'))->test(Index::class);

    $component->assertSee('Building Design Work')->assertSee('Soil Test Work')->assertDontSee('Retired Work');
    $component->set('search', 'soil')->assertSee('Soil Test Work')->assertDontSee('Building Design Work');
    $component->set('search', '')->set('filters.category', (string) $design->id)->assertSee('Building Design Work')->assertDontSee('Soil Test Work');
    $component->set('filters', ['business_line' => (string) $line->id, 'active' => ''])->assertSee('Building Design Work')->assertDontSee('Soil Test Work');
    $component->set('filters', ['active' => '0'])->assertSee('Retired Work')->assertDontSee('Soil Test Work');
});

test('the categories action shows only with the master data permission', function () {
    Livewire::actingAs(userWithPermissions('catalog.services.view'))->test(Index::class)
        ->assertDontSee(route('admin.master-data.show', 'service_categories'));

    Livewire::actingAs(userWithPermissions('catalog.services.view', 'catalog.master_data.view'))->test(Index::class)
        ->assertSee(route('admin.master-data.show', 'service_categories'));
});

test('a service is created through the form', function () {
    $category = ServiceCategory::factory()->create();
    $fixed = PricingBasis::factory()->create(['code' => PricingBasis::FIXED]);

    Livewire::actingAs(userWithPermissions('catalog.services.view', 'catalog.services.create'))
        ->test(Form::class)
        ->assertSet('pricing_basis_id', $fixed->id)
        ->set('code', 'soil')
        ->set('name', 'Soil Test Work')
        ->set('service_category_id', (string) $category->id)
        ->set('default_rate', '15000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('catalog.services.index'));

    expect(Service::query()->where('code', 'SOIL')->value('default_rate'))->toBe('15000.0000');
});

test('rule failures show on the form fields', function () {
    Livewire::actingAs(userWithPermissions('catalog.services.view', 'catalog.services.create'))
        ->test(Form::class)
        ->set('code', 'bad code')
        ->set('default_rate', '-5')
        ->call('save')
        ->assertHasErrors(['code', 'name', 'service_category_id', 'default_rate']);
});

test('internal business lines are not offered and are refused when forced', function () {
    $internal = BusinessLine::factory()->internal()->create(['name' => 'Management']);
    BusinessLine::factory()->create(['name' => 'Building Design']);

    Livewire::actingAs(userWithPermissions('catalog.services.view', 'catalog.services.create'))
        ->test(Form::class)
        ->assertSee('Building Design')
        ->assertDontSee('Management')
        ->set('business_line_id', (string) $internal->id)
        ->call('save')
        ->assertHasErrors(['business_line_id']);
});

test('the edit form loads the service and shows its history', function () {
    $service = Service::factory()->create(['name' => 'Old name']);

    Livewire::actingAs(userWithPermissions('catalog.services.view', 'catalog.services.update'))
        ->test(Form::class, ['service' => $service])
        ->assertSet('code', $service->code)
        ->assertSeeLivewire('foundation.history')
        ->set('name', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    expect($service->fresh()->name)->toBe('New name');
});
