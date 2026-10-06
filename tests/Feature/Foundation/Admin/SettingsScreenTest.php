<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\Settings as SettingsScreen;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(SettingSeeder::class));

test('settings need admin.settings.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.settings.edit'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.settings.view'))->get(route('admin.settings.edit'))->assertOk()->assertSee(__('Session idle timeout (minutes)'));
});

test('a tab saves typed values and flushes the cache', function () {
    Livewire::actingAs(userWithPermissions('admin.settings.view', 'admin.settings.update'))
        ->test(SettingsScreen::class)
        ->set('values.general.session_timeout_minutes', '45')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    expect(Settings::get('general.session_timeout_minutes'))->toBe(45);
});

test('invalid values show on their fields', function () {
    Livewire::actingAs(userWithPermissions('admin.settings.view', 'admin.settings.update'))
        ->test(SettingsScreen::class)
        ->set('values.general.session_timeout_minutes', 'soon')
        ->call('save')
        ->assertHasErrors(['values.general.session_timeout_minutes']);
});

test('timeout and password length are kept within safe bounds', function () {
    Livewire::actingAs(userWithPermissions('admin.settings.view', 'admin.settings.update'))
        ->test(SettingsScreen::class)
        ->set('values.general.session_timeout_minutes', '0')
        ->set('values.general.password_min_length', '500')
        ->call('save')
        ->assertHasErrors(['values.general.session_timeout_minutes', 'values.general.password_min_length']);

    expect(Settings::get('general.session_timeout_minutes'))->toBe(120)
        ->and(Settings::get('general.password_min_length'))->toBe(8);
});

test('switching tabs saves only that group', function () {
    Livewire::actingAs(userWithPermissions('admin.settings.view', 'admin.settings.update'))
        ->test(SettingsScreen::class)
        ->set('group', 'notifications')
        ->set('values.notifications.sms_enabled', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Settings::get('notifications.sms_enabled'))->toBeTrue();
});

test('saving without update permission is forbidden', function () {
    Livewire::actingAs(userWithPermissions('admin.settings.view'))->test(SettingsScreen::class)->call('save')->assertForbidden();
});

test('a tampered group is not found on save', function () {
    Livewire::actingAs(userWithPermissions('admin.settings.view', 'admin.settings.update'))
        ->test(SettingsScreen::class)
        ->set('group', 'nonexistent')
        ->call('save')
        ->assertNotFound();
});

test('the mobile group control uses the group keys as values', function () {
    $this->actingAs(userWithPermissions('admin.settings.view'))->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('value="notifications"', false);
});
