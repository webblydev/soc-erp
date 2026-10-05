<?php

use App\Modules\Foundation\Actions\SaveLocation;
use App\Modules\Foundation\Actions\SetLocationActive;
use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use Database\Seeders\Foundation\LocationLevelSeeder;
use Database\Seeders\Foundation\LocationSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->seed(LocationLevelSeeder::class));

function addLocation(string $name, ?Location $parent = null): Location
{
    return app(SaveLocation::class)->handle(['name' => $name, 'parent_id' => $parent?->id]);
}

test('a child gets the next level and a full path', function () {
    $dhaka = addLocation('Dhaka');
    $district = addLocation('Dhaka', $dhaka);
    $uttara = addLocation('Uttara', $district);
    $area = addLocation('Uttar Khan', $uttara);

    expect($dhaka->level->code)->toBe(LocationLevel::DIVISION)
        ->and($district->level->code)->toBe(LocationLevel::DISTRICT)
        ->and($area->level->code)->toBe(LocationLevel::AREA)
        ->and($area->full_path)->toBe('Dhaka › Dhaka › Uttara › Uttar Khan');
});

test('areas cannot have children', function () {
    $area = addLocation('Uttar Khan', addLocation('Uttara', addLocation('Dhaka', addLocation('Dhaka'))));

    expect(fn () => addLocation('Sector 1', $area))->toThrow(ValidationException::class);
});

test('renaming a location rebuilds the full path of every descendant (FD-AC-09)', function () {
    $district = addLocation('Dhaka', addLocation('Dhaka'));
    $uttara = addLocation('Uttara', $district);
    $first = addLocation('Uttar Khan', $uttara);
    $second = addLocation('Dakshin Khan', $uttara);

    app(SaveLocation::class)->handle(['name' => 'Uttara Model Town'], $uttara);

    expect($first->fresh()->full_path)->toBe('Dhaka › Dhaka › Uttara Model Town › Uttar Khan')
        ->and($second->fresh()->full_path)->toBe('Dhaka › Dhaka › Uttara Model Town › Dakshin Khan');
});

test('names are unique under the same parent, ignoring case', function () {
    $division = addLocation('Dhaka');
    addLocation('Gazipur', $division);

    expect(fn () => addLocation('gazipur', $division))->toThrow(ValidationException::class);
    expect(addLocation('Gazipur')->exists)->toBeTrue();
});

test('moving a location under its own descendant is refused', function () {
    $division = addLocation('Dhaka');
    $district = addLocation('Dhaka', $division);

    expect(fn () => app(SaveLocation::class)->handle(['name' => 'Dhaka', 'parent_id' => $district->id], $division))
        ->toThrow(ValidationException::class);
});

test('the location seed loads all districts and is idempotent', function () {
    $this->seed(LocationSeeder::class);
    $this->seed(LocationSeeder::class);

    $districts = Location::query()->whereHas('level', fn ($q) => $q->where('code', LocationLevel::DISTRICT))->count();

    expect(Location::query()->whereNull('parent_id')->count())->toBe(8)
        ->and($districts)->toBe(64)
        ->and(Location::query()->where('full_path', 'Dhaka › Gazipur › Kaliakair')->exists())->toBeTrue();
});

test('top-level names are unique ignoring case', function () {
    addLocation('Dhaka');

    expect(fn () => addLocation('dhaka'))->toThrow(ValidationException::class);
});

test('renaming a division cascades through districts and thanas', function () {
    $division = addLocation('Dhaka');
    $district = addLocation('Gazipur', $division);
    $thana = addLocation('Kaliakair', $district);

    app(SaveLocation::class)->handle(['name' => 'Dhaka Division'], $division);

    expect($district->fresh()->full_path)->toBe('Dhaka Division › Gazipur')
        ->and($thana->fresh()->full_path)->toBe('Dhaka Division › Gazipur › Kaliakair');
});

test('a district cannot be promoted to top level', function () {
    $district = addLocation('Gazipur', addLocation('Dhaka'));
    $thana = addLocation('Kaliakair', $district);

    expect(fn () => app(SaveLocation::class)->handle(['name' => 'Gazipur', 'parent_id' => null], $district))
        ->toThrow(ValidationException::class);

    expect($district->fresh()->parent_id)->not->toBeNull()
        ->and($district->fresh()->full_path)->toBe('Dhaka › Gazipur')
        ->and($district->fresh()->level->code)->toBe(LocationLevel::DISTRICT)
        ->and($thana->fresh()->full_path)->toBe('Dhaka › Gazipur › Kaliakair');
});

test('moving a thana to another district updates its path and its children', function () {
    $division = addLocation('Dhaka');
    $gazipur = addLocation('Gazipur', $division);
    $narayanganj = addLocation('Narayanganj', $division);
    $thana = addLocation('Kaliakair', $gazipur);
    $area = addLocation('Sector 1', $thana);

    app(SaveLocation::class)->handle(['name' => 'Kaliakair', 'parent_id' => $narayanganj->id], $thana);

    expect($thana->fresh()->full_path)->toBe('Dhaka › Narayanganj › Kaliakair')
        ->and($area->fresh()->full_path)->toBe('Dhaka › Narayanganj › Kaliakair › Sector 1');
});

test('the Bengali name can be cleared', function () {
    $location = app(SaveLocation::class)->handle(['name' => 'Dhaka', 'name_bn' => 'ঢাকা']);

    app(SaveLocation::class)->handle(['name' => 'Dhaka'], $location);
    expect($location->fresh()->name_bn)->toBe('ঢাকা');

    app(SaveLocation::class)->handle(['name' => 'Dhaka', 'name_bn' => null], $location);
    expect($location->fresh()->name_bn)->toBeNull();
});

test('a location can be deactivated and reactivated', function () {
    $location = addLocation('Dhaka');

    app(SetLocationActive::class)->handle($location, false);
    expect($location->fresh()->is_active)->toBeFalse();

    app(SetLocationActive::class)->handle($location, true);
    expect($location->fresh()->is_active)->toBeTrue();
});
