<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Profile\Edit;
use App\Modules\Foundation\Models\NotificationPreference;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('password can be updated', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(Edit::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('savePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(Edit::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('savePassword')
        ->assertHasErrors(['current_password']);
});

test('the new password follows the minimum length setting', function () {
    $this->seed(SettingSeeder::class);
    Settings::set('general.password_min_length', 12);
    $user = User::factory()->create(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(Edit::class)
        ->set('current_password', 'password')
        ->set('password', 'elevenchars')
        ->set('password_confirmation', 'elevenchars')
        ->call('savePassword')
        ->assertHasErrors(['password']);
});

test('notification preferences are saved from the profile', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Edit::class)
        ->set('notifications.security__login_new_ip.mail', false)
        ->call('saveNotifications')
        ->assertHasNoErrors();

    expect(NotificationPreference::matrixFor($user)['security.login_new_ip']['mail'])->toBeFalse();
});

test('tampered notification preference input is skipped', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Edit::class)
        ->set('notifications.security__login_new_ip', 'junk')
        ->call('saveNotifications')
        ->assertHasNoErrors();
});
