<?php

use App\Models\User;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

test('usernames and emails are stored trimmed and lowercase', function () {
    $user = User::factory()->create(['username' => '  Rahim.BD ', 'email' => ' Rahim@Example.COM ']);

    expect($user->fresh()->username)->toBe('rahim.bd')
        ->and($user->fresh()->email)->toBe('rahim@example.com');
});

test('a blank email is stored as null', function () {
    expect(User::factory()->create(['email' => '  '])->fresh()->email)->toBeNull();
});

test('bangladeshi mobile numbers are normalised to the local 01 form', function (string $input) {
    expect(User::factory()->create(['phone' => $input])->fresh()->phone)->toBe('01712345678');
})->with(['+8801712345678', '8801712345678', '01712-345678', '0171 234 5678']);

test('the default password rule uses the minimum length setting', function () {
    $this->seed(SettingSeeder::class);
    Settings::set('general.password_min_length', 12);

    expect(Validator::make(['password' => 'short-pass1'], ['password' => Password::default()])->fails())->toBeTrue()
        ->and(Validator::make(['password' => 'long-enough-pass'], ['password' => Password::default()])->fails())->toBeFalse();
});
