<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Profile\Edit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk();
});

test('old settings urls redirect to the profile', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/settings')->assertRedirect('/profile');
    $this->get('/settings/profile')->assertRedirect('/profile');
    $this->get('/settings/security')->assertRedirect('/profile?tab=password');
});

test('name and phone can be updated', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Edit::class)
        ->set('name', 'Test User')
        ->set('phone', '+8801912345678')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Test User')->and($user->fresh()->phone)->toBe('01912345678');
});

test('an avatar can be uploaded and replaces the old one', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test(Edit::class);

    $component->set('avatar', UploadedFile::fake()->image('me.png', 100, 100))->call('saveDetails')->assertHasNoErrors();
    $first = $user->fresh()->avatar_path;

    $component->set('avatar', UploadedFile::fake()->image('me2.jpg', 100, 100))->call('saveDetails')->assertHasNoErrors();
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);
});

test('an invalid avatar is rejected and nothing is stored', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Edit::class)
        ->set('avatar', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->call('saveDetails')
        ->assertHasErrors(['avatar']);

    expect($user->fresh()->avatar_path)->toBeNull()->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('an unknown tab falls back to details', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->withQueryParams(['tab' => 'bogus'])->test(Edit::class)->assertSet('tab', 'details');

    Livewire::actingAs($user)->test(Edit::class)->set('tab', 'bogus')->assertSet('tab', 'details');
});

test('the profile page does not offer account deletion', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('profile.edit'))->assertDontSee(__('Delete account'));
});
