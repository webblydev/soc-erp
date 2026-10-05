<?php

use App\Models\User;
use Livewire\Livewire;
use Tests\Fixtures\ListingFixture;

test('search matches name or username case-insensitively', function () {
    User::factory()->create(['username' => 'karim', 'name' => 'Abdul Karim']);
    User::factory()->create(['username' => 'rahim', 'name' => 'Rahim Uddin']);

    Livewire::test(ListingFixture::class)
        ->set('search', 'KARIM')
        ->assertSee('desktop:karim,')
        ->assertDontSee('rahim,');
});

test('wildcard characters in the search are matched literally', function () {
    User::factory()->create(['username' => 'a_b', 'name' => 'Underscore']);
    User::factory()->create(['username' => 'axb', 'name' => 'Other']);
    User::factory()->create(['username' => 'pct', 'name' => '50% off']);

    Livewire::test(ListingFixture::class)
        ->set('search', 'a_b')->assertSee('desktop:a_b,')->assertDontSee('axb,')
        ->set('search', '%')->assertSee('desktop:pct,')->assertDontSee('a_b,');
});

test('unknown sort keys, bad directions and page sizes fall back to defaults', function () {
    User::factory()->create(['username' => 'zed', 'name' => 'Zed']);
    User::factory()->create(['username' => 'amy', 'name' => 'Amy']);

    Livewire::withQueryParams(['sort' => 'password', 'direction' => 'sideways', 'perPage' => 9999])
        ->test(ListingFixture::class)
        ->assertSee('desktop:zed,amy,')
        ->assertSet('perPage', 25);
});

test('sorting by a whitelisted key toggles direction', function () {
    User::factory()->create(['username' => 'zed', 'name' => 'Zed']);
    User::factory()->create(['username' => 'amy', 'name' => 'Amy']);

    Livewire::test(ListingFixture::class)
        ->call('sortBy', 'name')->assertSee('desktop:amy,zed,')
        ->call('sortBy', 'name')->assertSee('desktop:zed,amy,');
});

test('filters narrow the rows and clearFilters resets them', function () {
    User::factory()->create(['username' => 'live']);
    User::factory()->inactive()->create(['username' => 'gone']);

    Livewire::test(ListingFixture::class)
        ->set('filters.active', '0')->assertSee('desktop:gone,')->assertDontSee('live,')
        ->call('clearFilters')->assertSee('live,')->assertSee('gone,');
});

test('mobile rows load 25 at a time', function () {
    User::factory()->count(30)->create();

    Livewire::test(ListingFixture::class)
        ->assertSee('more:yes')
        ->call('loadMore')
        ->assertSet('limit', 50)
        ->assertSee('more:no');
});

test('a tampered limit is clamped to a positive multiple of 25 within the cap', function () {
    User::factory()->count(30)->create();

    Livewire::test(ListingFixture::class)
        ->set('limit', 100000)
        ->assertSet('limit', 500)
        ->set('limit', -40)
        ->assertSet('limit', 25)
        ->set('limit', 30)
        ->assertSet('limit', 25);
});
