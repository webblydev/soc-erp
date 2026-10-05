<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('changing the password clears the forced change flag', function () {
    $user = User::factory()->mustChangePassword()->create();
    $this->actingAs($user);

    Livewire::test('pages::auth.change-password')
        ->set('current_password', 'password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('new-password-123', $user->password))->toBeTrue();
});

test('the current password must be correct', function () {
    $this->actingAs(User::factory()->mustChangePassword()->create());

    Livewire::test('pages::auth.change-password')
        ->set('current_password', 'wrong')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('save')
        ->assertHasErrors('current_password');
});

test('the new password must meet the minimum length and differ from the current one', function () {
    $this->actingAs(User::factory()->mustChangePassword()->create());

    Livewire::test('pages::auth.change-password')
        ->set('current_password', 'password')
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('save')
        ->assertHasErrors('password');

    Livewire::test('pages::auth.change-password')
        ->set('current_password', 'password')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasErrors('password');
});
