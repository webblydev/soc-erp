<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\LoginHistory as LoginHistoryScreen;
use App\Modules\Foundation\Models\LoginHistory;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    LoginHistory::query()->create(['username_attempted' => 'goodlogin', 'succeeded' => true, 'ip_address' => '10.0.0.1']);
    LoginHistory::query()->create(['username_attempted' => 'badlogin', 'succeeded' => false, 'ip_address' => '10.0.0.2']);
});

test('login history needs admin.login_history.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.login-history.index'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.login_history.view'))->get(route('admin.login-history.index'))->assertOk();
});

test('results and usernames filter the rows', function () {
    Livewire::actingAs(userWithPermissions('admin.login_history.view'))
        ->test(LoginHistoryScreen::class)
        ->set('filters.result', '0')
        ->assertSee('badlogin')
        ->assertDontSee('goodlogin')
        ->set('filters.result', '')
        ->set('filters.user', 'GOODLOGIN')
        ->assertSee('goodlogin')
        ->assertDontSee('badlogin');
});

test('an invalid date filter is ignored instead of failing', function () {
    Livewire::actingAs(userWithPermissions('admin.login_history.view'))
        ->test(LoginHistoryScreen::class)
        ->set('filters.from', 'garbage')
        ->set('filters.to', '2026-13-45')
        ->assertOk()
        ->assertSee('goodlogin');
});

test('login history exports to excel', function () {
    Excel::fake();
    Excel::matchByRegex();

    Livewire::actingAs(userWithPermissions('admin.login_history.view'))->test(LoginHistoryScreen::class)->call('export');

    Excel::assertDownloaded('/^login-history-\d{8}-\d{6}\.xlsx$/');
});

test('array-valued filters are ignored instead of failing', function () {
    Livewire::actingAs(userWithPermissions('admin.login_history.view'))
        ->test(LoginHistoryScreen::class)
        ->set('filters.user', ['x'])
        ->set('filters.result', ['x'])
        ->assertOk()
        ->assertSee('goodlogin')
        ->assertSee('badlogin');
});
