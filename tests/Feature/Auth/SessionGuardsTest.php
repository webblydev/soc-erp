<?php

use App\Models\User;

test('users who must change their password are sent to the change page', function () {
    $this->actingAs(User::factory()->mustChangePassword()->create());

    $this->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->get(route('password.change'))->assertOk();
});

test('users who must change their password can still log out', function () {
    $this->actingAs(User::factory()->mustChangePassword()->create());

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();
});

test('deactivated users are logged out on their next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->update(['is_active' => false]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('idle sessions expire after the configured timeout', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();

    $this->travel(121)->minutes();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('activity within the timeout keeps the session alive', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
    $this->travel(100)->minutes();
    $this->get(route('dashboard'))->assertOk();
    $this->travel(100)->minutes();
    $this->get(route('dashboard'))->assertOk();
});
