<?php

use App\Models\User;

test('the dashboard renders the mobile shell with home, profile and more', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="mobile-bottom-nav"', false)
        ->assertSee('data-test="mobile-top-bar"', false)
        ->assertSee(__('Home'))
        ->assertSee(__('Profile'))
        ->assertSee(__('More'));
});

test('the viewport allows drawing under the safe areas', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertSee('viewport-fit=cover', false);
});

test('users without an email see their username in the menus', function () {
    $this->actingAs(User::factory()->create(['email' => null, 'username' => 'rahim.bd']));

    $this->get(route('dashboard'))->assertSee('rahim.bd');
});
