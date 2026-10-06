<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('public registration and email verification routes are not registered', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('verification.notice'))->toBeFalse();
});

test('users may exist without an email address', function () {
    User::factory()->count(2)->create(['email' => null]);

    expect(User::query()->whereNull('email')->count())->toBe(2);
});

test('the profile page opens for a user without an email address', function () {
    $user = User::factory()->create(['email' => null]);

    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
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
