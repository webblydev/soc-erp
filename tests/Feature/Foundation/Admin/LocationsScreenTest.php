<?php

use App\Models\User;
use App\Modules\Foundation\Actions\SaveLocation;
use App\Modules\Foundation\Livewire\Admin\Locations;
use App\Modules\Foundation\Models\Location;
use Database\Seeders\Foundation\LocationLevelSeeder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LocationLevelSeeder::class);
    $this->division = app(SaveLocation::class)->handle(['name' => 'Dhaka']);
    $this->district = app(SaveLocation::class)->handle(['name' => 'Gazipur', 'parent_id' => $this->division->id]);
});

test('locations need admin.locations.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.locations.index'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.locations.view'))->get(route('admin.locations.index'))->assertOk()->assertSee('Dhaka');
});

test('children appear only once their parent is expanded', function () {
    Livewire::actingAs(userWithPermissions('admin.locations.view'))
        ->test(Locations::class)
        ->assertDontSee('Gazipur')
        ->call('toggle', $this->division->id)
        ->assertSee('Gazipur');
});

test('search lists matches with their full path', function () {
    Livewire::actingAs(userWithPermissions('admin.locations.view'))
        ->test(Locations::class)
        ->set('search', 'gazi')
        ->assertSee('Dhaka › Gazipur');
});

test('adding a child and renaming go through the sheet', function () {
    $component = Livewire::actingAs(userWithPermissions('admin.locations.view', 'admin.locations.create', 'admin.locations.update'))
        ->test(Locations::class)
        ->call('addChild', $this->district->id)
        ->assertDispatched('open-sheet-location')
        ->set('name', 'Kaliakair')
        ->call('save')
        ->assertHasNoErrors();

    $thana = Location::query()->where('name', 'Kaliakair')->firstOrFail();
    expect($thana->full_path)->toBe('Dhaka › Gazipur › Kaliakair');

    $component->call('edit', $this->district->id)->set('name', 'Gazipur City')->call('save')->assertHasNoErrors();
    expect($thana->fresh()->full_path)->toBe('Dhaka › Gazipur City › Kaliakair');
});

test('duplicate names show on the name field', function () {
    Livewire::actingAs(userWithPermissions('admin.locations.view', 'admin.locations.create'))
        ->test(Locations::class)
        ->call('addChild', $this->division->id)
        ->set('name', 'GAZIPUR')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('activating and deactivating needs admin.locations.deactivate', function () {
    Livewire::actingAs(userWithPermissions('admin.locations.view', 'admin.locations.update'))
        ->test(Locations::class)
        ->call('toggleActive', $this->district->id)
        ->assertForbidden();

    Livewire::actingAs(userWithPermissions('admin.locations.view', 'admin.locations.deactivate'))
        ->test(Locations::class)
        ->call('toggleActive', $this->district->id);

    expect($this->district->fresh()->is_active)->toBeFalse();
});

test('editing and parent state cannot be set from the client', function () {
    $component = Livewire::actingAs(userWithPermissions('admin.locations.view', 'admin.locations.update'))
        ->test(Locations::class);

    expect(fn () => $component->set('editingId', $this->district->id))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => $component->set('parent_id', $this->division->id))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('toggling and editing are refused without permission', function () {
    Livewire::actingAs(userWithPermissions('admin.locations.view'))
        ->test(Locations::class)
        ->call('edit', $this->district->id)
        ->assertForbidden();
});
