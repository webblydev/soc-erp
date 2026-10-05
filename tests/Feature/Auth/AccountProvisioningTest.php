<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

test('public registration and email verification routes are not registered', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('verification.notice'))->toBeFalse();
});

test('users may exist without an email address', function () {
    User::factory()->count(2)->create(['email' => null]);

    expect(User::query()->whereNull('email')->count())->toBe(2);
});

test('saving the profile with an empty email stores null', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', 'Karim')
        ->set('email', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->email)->toBeNull();
});

test('new users must change their password by default', function () {
    $user = User::query()->create([
        'name' => 'Rahim',
        'username' => 'rahim',
        'password' => 'secret-password',
    ]);

    expect($user->refresh()->must_change_password)->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});
