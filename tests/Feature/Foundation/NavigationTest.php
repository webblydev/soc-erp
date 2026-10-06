<?php

use App\Models\User;
use App\Modules\Foundation\Services\Navigation;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('test/leads', fn () => 'ok')->name('crm.leads.index');
    Route::get('test/customers', fn () => 'ok')->name('crm.customers.index');
    Route::get('test/users', fn () => 'ok')->name('admin.users.index');
    Route::getRoutes()->refreshNameLookups();

    createPermissions('crm.leads.view_own', 'admin.users.view');

    $this->navigation = new Navigation([
        ['key' => 'crm', 'label' => 'CRM', 'icon' => 'users', 'items' => [
            ['label' => 'Leads', 'route' => 'crm.leads.index', 'icon' => 'target', 'permission' => 'crm.leads.view_own', 'mobile_primary' => true],
            ['label' => 'Customers', 'route' => 'crm.customers.index', 'icon' => 'building', 'permission' => 'crm.customers.view_own'],
            ['label' => 'Pipeline', 'route' => 'crm.pipeline.index', 'icon' => 'kanban', 'permission' => null],
        ]],
        ['key' => 'admin', 'label' => 'Admin', 'icon' => 'settings', 'items' => [
            ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'user-cog', 'permission' => 'admin.users.view'],
        ]],
    ]);
});

test('items the user cannot access, items without routes and empty groups are dropped', function () {
    $user = User::factory()->create();
    $user->syncDirectPermissions(['crm.leads.view_own']);

    $groups = $this->navigation->for($user);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['key'])->toBe('crm')
        ->and(collect($groups[0]['items'])->pluck('label')->all())->toBe(['Leads']);
});

test('primary mobile destinations come from permitted flagged items', function () {
    $user = User::factory()->create();
    $user->syncDirectPermissions(['crm.leads.view_own', 'admin.users.view']);

    expect(collect($this->navigation->primaryMobile($user))->pluck('label')->all())->toBe(['Leads']);
});

test('items are marked active for their route family', function () {
    $user = User::factory()->create();
    $user->syncDirectPermissions(['crm.leads.view_own']);

    $this->actingAs($user)->get('test/leads');

    expect($this->navigation->for($user)[0]['items'][0]['active'])->toBeTrue();
});

test('links with route params point at that page and are active only there', function () {
    Route::get('test/tables/{table}', fn () => 'ok')->name('test.tables.show');
    Route::getRoutes()->refreshNameLookups();

    $navigation = new Navigation([
        ['key' => 'admin', 'label' => 'Admin', 'icon' => 'shield', 'items' => [
            ['label' => 'Master data', 'icon' => 'database', 'children' => [
                ['label' => 'Branches', 'route' => 'test.tables.show', 'params' => ['table' => 'branches'], 'icon' => 'building-2'],
                ['label' => 'Units', 'route' => 'test.tables.show', 'params' => ['table' => 'units'], 'icon' => 'ruler'],
            ]],
        ]],
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('test/tables/units');
    $children = $navigation->for($user)[0]['items'][0]['children'];

    expect($children[0]['url'])->toBe(url('test/tables/branches'))
        ->and($children[0]['active'])->toBeFalse()
        ->and($children[1]['active'])->toBeTrue();
});

test('tree nodes keep only permitted children and are dropped when none remain', function () {
    $navigation = new Navigation([
        ['key' => 'admin', 'label' => 'Admin', 'icon' => 'shield', 'items' => [
            ['label' => 'User management', 'icon' => 'users', 'children' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'users', 'permission' => 'admin.users.view', 'mobile_primary' => true],
                ['label' => 'Leads', 'route' => 'crm.leads.index', 'icon' => 'target', 'permission' => 'crm.leads.view_own'],
            ]],
            ['label' => 'Master data', 'icon' => 'database', 'children' => [
                ['label' => 'Pipeline', 'route' => 'crm.pipeline.index', 'icon' => 'kanban'],
            ]],
        ]],
    ]);
    $user = User::factory()->create();
    $user->syncDirectPermissions(['admin.users.view']);

    $this->actingAs($user)->get('test/users');
    $items = $navigation->for($user)[0]['items'];

    expect($items)->toHaveCount(1)
        ->and($items[0]['label'])->toBe('User management')
        ->and($items[0]['active'])->toBeTrue()
        ->and(collect($items[0]['children'])->pluck('label')->all())->toBe(['Users'])
        ->and(collect($navigation->primaryMobile($user))->pluck('label')->all())->toBe(['Users']);
});

test('the sidebar renders admin pages as a tree', function () {
    $this->actingAs(userWithPermissions('admin.users.view'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="sidebar-tree-node"', false)
        ->assertSeeInOrder([__('User management'), __('Users')]);
});
