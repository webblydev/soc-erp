<?php

use App\Models\User;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\UnitKind;
use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Currency;
use App\Support\Exports\QueryExport;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

test('master data needs a view permission for at least one table', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.master-data.index'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.branches.view'))->get(route('admin.master-data.index'))->assertOk()->assertSee('Branches');
});

test('unknown tables are 404 and tables without permission are 403', function () {
    $this->actingAs(userWithPermissions('admin.branches.view'));

    $this->get(route('admin.master-data.show', 'nope'))->assertNotFound();
    $this->get(route('admin.master-data.show', 'currencies'))->assertForbidden();
    $this->get(route('admin.master-data.show', 'branches'))->assertOk();
});

test('the branches nav item redirects to the branches table', function () {
    $this->actingAs(userWithPermissions('admin.branches.view'))
        ->get(route('admin.branches.index'))
        ->assertRedirect(route('admin.master-data.show', 'branches'));
});

test('a row is created through the sheet form', function () {
    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.create'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('create')
        ->assertDispatched('open-sheet-lookup-row')
        ->set('form.code', 'CTG')
        ->set('form.name', 'Chattogram')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('close-sheet-lookup-row');

    expect(Branch::query()->where('code', 'CTG')->exists())->toBeTrue();
});

test('rule failures show on the sheet fields', function () {
    $system = Branch::factory()->create(['code' => 'HO', 'is_system' => true]);

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('edit', $system->id)
        ->set('form.code', 'HQ')
        ->call('save')
        ->assertHasErrors(['form.code']);
});

test('deactivating through the form needs the deactivate permission', function () {
    $branch = Branch::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('edit', $branch->id)
        ->set('form.is_active', false)
        ->call('save')
        ->assertForbidden();
});

test('deleting a row in use shows an error toast', function () {
    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update', 'admin.branches.deactivate'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('edit', $branch->id)
        ->call('delete')
        ->assertDispatched('toast', type: 'error');
});

test('sorting needs update permission and a row from the open table', function () {
    $branch = Branch::factory()->create();
    $currency = Currency::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.branches.view'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('sort', $branch->id, 0)
        ->assertForbidden();

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('sort', $currency->id + 1000, 0)
        ->assertNotFound();
});

test('the open table cannot be switched from the client', function () {
    Currency::factory()->create(['code' => 'ZZZ']);

    $component = Livewire::actingAs(userWithPermissions('admin.branches.view'))
        ->test(MasterData::class, ['table' => 'branches']);

    expect(fn () => $component->set('table', 'currencies'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $component->assertDontSee('ZZZ');
});

test('sorting a row that belongs to another table is a 404', function () {
    $branch = Branch::factory()->create();
    $currency = Currency::factory()->count(Branch::query()->max('id') + 2)->create()->last();

    expect(Branch::query()->whereKey($currency->id)->exists())->toBeFalse();

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('sort', $currency->id, 0)
        ->assertNotFound();

    expect($branch->fresh()->sort_order)->toBe($branch->sort_order);
});

test('creating without the create permission is forbidden', function () {
    Livewire::actingAs(userWithPermissions('admin.branches.view'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('create')
        ->assertForbidden();
});

test('deleting without the deactivate permission is forbidden', function () {
    $branch = Branch::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('edit', $branch->id)
        ->call('delete')
        ->assertForbidden();

    expect(Branch::query()->whereKey($branch->id)->exists())->toBeTrue();
});

test('viewers without update permission get no save button', function () {
    Branch::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.branches.view'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->assertDontSee('form="lookup-row-form"', false)
        ->assertSee('disabled', false);
});

test('a table opens as its own page without the list of other tables', function () {
    $this->actingAs(userWithPermissions('admin.branches.view', 'admin.master_data.view'))
        ->get(route('admin.master-data.show', 'branches'))
        ->assertOk()
        ->assertSee('Branches - ', false)
        ->assertDontSee('aria-label="Lookup tables"', false);
});

test('the master data index lists the tables the user may open', function () {
    $this->actingAs(userWithPermissions('admin.branches.view'))
        ->get(route('admin.master-data.index'))
        ->assertSee(route('admin.master-data.show', 'branches'), false)
        ->assertDontSee(route('admin.master-data.show', 'currencies'), false);
});

test('a table lists its rows in the ERP grid with extra fields resolved to names', function () {
    $kind = UnitKind::factory()->create(['name' => 'Area kind']);
    Unit::factory()->create(['code' => 'SQFT', 'name' => 'Square foot', 'symbol' => 'sft', 'unit_kind_id' => $kind->id]);

    Livewire::actingAs(userWithPermissions('catalog.units.view'))
        ->test(MasterData::class, ['table' => 'units'])
        ->assertSee('data-variant="bordered"', false)
        ->assertSee('Square foot')
        ->assertSee('sft')
        ->assertSee('Area kind');
});

test('search and the status filter narrow the rows and turn off drag reordering', function () {
    Branch::factory()->create(['name' => 'Chattogram']);
    Branch::factory()->create(['name' => 'Sylhet', 'is_active' => false]);

    $component = Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->assertSee('wire:sort="sortOnPage"', false);

    $component->set('search', 'chatto')->assertSee('Chattogram')->assertDontSee('Sylhet')
        ->assertDontSee('wire:sort="sortOnPage"', false);

    $component->set('search', '')->set('filters.active', '0')->assertSee('Sylhet')->assertDontSee('Chattogram');
});

test('dragging on a later desktop page reorders by the absolute position', function () {
    $branches = collect(range(1, 26))->map(fn (int $order) => Branch::factory()->create(['sort_order' => $order, 'name' => sprintf('Branch %02d', $order)]));
    $last = $branches->last();

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->withQueryParams(['page' => 2])
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('sortOnPage', $last->id, 0);

    $order = Branch::query()->orderBy('sort_order')->pluck('id');
    expect($order->search($last->id))->toBe(25);
});

test('delete selected removes unused rows and skips system rows', function () {
    $unused = Branch::factory()->create();
    $system = Branch::factory()->create(['is_system' => true]);

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.deactivate'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->set('selected', [(string) $unused->id, (string) $system->id])
        ->call('deleteSelected')
        ->assertDispatched('toast', type: 'warning', description: '1 deleted, 1 skipped. System rows cannot be deleted.');

    expect(Branch::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and(Branch::query()->whereKey($system->id)->exists())->toBeTrue();
});

test('deleting from the row menu needs the deactivate permission', function () {
    $branch = Branch::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.branches.view', 'admin.branches.update'))
        ->test(MasterData::class, ['table' => 'branches'])
        ->call('deleteRecord', $branch->id)
        ->assertForbidden();

    expect(Branch::query()->whereKey($branch->id)->exists())->toBeTrue();
});

test('export downloads the filtered rows with extra fields as names', function () {
    Excel::fake();
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(10, 0, 0));
    $kind = UnitKind::factory()->create(['name' => 'Area kind']);
    Unit::factory()->create(['code' => 'SQFT', 'unit_kind_id' => $kind->id]);
    Unit::factory()->create(['code' => 'OLD', 'unit_kind_id' => $kind->id, 'is_active' => false]);

    Livewire::actingAs(userWithPermissions('catalog.units.view'))
        ->test(MasterData::class, ['table' => 'units'])
        ->set('filters.active', '1')
        ->call('export');

    Excel::assertDownloaded('units-20261007-100000.xlsx', function (QueryExport $export): bool {
        $rows = $export->query()->get()->map(fn ($row) => $export->map($row));

        return $rows->pluck(0)->contains('SQFT') && ! $rows->pluck(0)->contains('OLD')
            && $rows->firstWhere(0, 'SQFT')[4] === 'Area kind';
    });
});
