<?php

use App\Models\User;
use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Livewire\WorkItems\Form;
use App\Modules\Catalog\Livewire\WorkItems\Index;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Catalog\Models\WorkItemCategory;
use App\Support\Exports\QueryExport;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(fn () => Model::preventLazyLoading());
afterEach(fn () => Model::preventLazyLoading(false));

test('each work items route needs its permission', function () {
    $item = WorkItem::factory()->create();

    $this->actingAs(userWithPermissions('catalog.services.view'));
    $this->get(route('catalog.work-items.index'))->assertForbidden();

    $this->actingAs(userWithPermissions('catalog.work_items.view'));
    $this->get(route('catalog.work-items.index'))->assertOk()->assertSee($item->name);
    $this->get(route('catalog.work-items.create'))->assertForbidden();
    $this->get(route('catalog.work-items.edit', $item))->assertForbidden();

    $this->actingAs(userWithPermissions('catalog.work_items.view', 'catalog.work_items.create', 'catalog.work_items.update'));
    $this->get(route('catalog.work-items.create'))->assertOk();
    $this->get(route('catalog.work-items.edit', $item))->assertOk();
});

test('a project manager may edit work items but not services', function () {
    seedAccessControl();
    $manager = User::factory()->create();
    $manager->syncRoles(['project_manager']);

    $this->actingAs($manager);
    $this->get(route('catalog.work-items.create'))->assertOk();
    $this->get(route('catalog.services.create'))->assertForbidden();
});

test('the list filters by category, unit and status and shows the formula', function () {
    $concrete = WorkItemCategory::factory()->create();
    $cum = Unit::factory()->create(['symbol' => 'cum']);
    WorkItem::factory()->create(['name' => 'RCC in slab', 'work_item_category_id' => $concrete->id, 'unit_id' => $cum->id]);
    WorkItem::factory()->create(['name' => 'Brick wall', 'measurement_formula' => MeasurementFormula::NosLW]);

    $component = Livewire::actingAs(userWithPermissions('catalog.work_items.view'))->test(Index::class);

    $component->assertSee('RCC in slab')->assertSee('Brick wall')->assertSee('Nos × L × W');
    $component->set('filters.category', (string) $concrete->id)->assertSee('RCC in slab')->assertDontSee('Brick wall');
    $component->set('filters', ['unit' => (string) $cum->id])->assertSee('RCC in slab')->assertDontSee('Brick wall');
});

test('a work item is created through the form', function () {
    $category = WorkItemCategory::factory()->create();
    $unit = Unit::factory()->create();

    Livewire::actingAs(userWithPermissions('catalog.work_items.view', 'catalog.work_items.create'))
        ->test(Form::class)
        ->set('code', 'ew-01')
        ->set('name', 'Earth work in excavation')
        ->set('work_item_category_id', (string) $category->id)
        ->set('unit_id', (string) $unit->id)
        ->set('measurement_formula', 'nos_l_w_h')
        ->set('standard_rate', '145.5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('catalog.work-items.index'));

    expect(WorkItem::query()->where('code', 'EW-01')->value('measurement_formula'))->toBe(MeasurementFormula::NosLWH);
});

test('the edit form loads the work item, saves changes and shows its history', function () {
    $item = WorkItem::factory()->create(['measurement_formula' => MeasurementFormula::NosL]);

    Livewire::actingAs(userWithPermissions('catalog.work_items.view', 'catalog.work_items.update'))
        ->test(Form::class, ['workItem' => $item])
        ->assertSet('measurement_formula', 'nos_l')
        ->assertSeeLivewire('foundation.history')
        ->set('name', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($item->fresh()->name)->toBe('Renamed');
});

test('form errors show inline', function () {
    Livewire::actingAs(userWithPermissions('catalog.work_items.view', 'catalog.work_items.create'))
        ->test(Form::class)
        ->set('measurement_formula', 'cubic')
        ->call('save')
        ->assertHasErrors(['code', 'name', 'work_item_category_id', 'unit_id', 'measurement_formula']);
});

test('export selected needs work_items.export and downloads only the selected items', function () {
    Excel::fake();
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(10, 0, 0));
    $chosen = WorkItem::factory()->create(['code' => 'WI-CHOSEN']);
    WorkItem::factory()->create(['code' => 'WI-OTHER']);

    Livewire::actingAs(userWithPermissions('catalog.work_items.view'))->test(Index::class)
        ->set('selected', [(string) $chosen->id])
        ->call('exportSelected')
        ->assertForbidden();

    Livewire::actingAs(userWithPermissions('catalog.work_items.view', 'catalog.work_items.export'))->test(Index::class)
        ->set('selected', [(string) $chosen->id])
        ->call('exportSelected');

    Excel::assertDownloaded('work-items-20261007-100000.xlsx', fn (QueryExport $export): bool => $export->query()->pluck('code')->all() === ['WI-CHOSEN']);
});
