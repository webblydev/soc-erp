<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Currency;
use Livewire\Livewire;

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
