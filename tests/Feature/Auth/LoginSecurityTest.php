<?php

use App\Models\User;
use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\LoginHistory;

function attemptLogin(string $login, string $password = 'password')
{
    return test()->post(route('login.store'), ['login' => $login, 'password' => $password]);
}

test('users can log in with their username in any case and surrounding spaces', function () {
    User::factory()->create(['username' => 'rahim']);

    attemptLogin('  Rahim ')->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can log in with their email', function () {
    $user = User::factory()->create(['email' => 'rahim@soc.test']);

    attemptLogin('RAHIM@soc.test');

    $this->assertAuthenticatedAs($user);
});

test('inactive users are refused', function () {
    User::factory()->inactive()->create(['username' => 'karim']);

    attemptLogin('karim')->assertSessionHasErrors(['login' => 'This account is inactive.']);

    $this->assertGuest();
});

test('every attempt is written to login history', function () {
    $user = User::factory()->create(['username' => 'rahim']);

    attemptLogin('rahim', 'wrong');
    attemptLogin('nobody', 'wrong');
    attemptLogin('rahim');

    expect(LoginHistory::query()->orderBy('id')->get(['user_id', 'username_attempted', 'succeeded'])->toArray())->toBe([
        ['user_id' => $user->id, 'username_attempted' => 'rahim', 'succeeded' => false],
        ['user_id' => null, 'username_attempted' => 'nobody', 'succeeded' => false],
        ['user_id' => $user->id, 'username_attempted' => 'rahim', 'succeeded' => true],
    ]);
});

test('a successful login stamps last login and is audited', function () {
    $user = User::factory()->create(['username' => 'rahim']);

    attemptLogin('rahim');

    $user->refresh();

    expect($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->toBe('127.0.0.1')
        ->and(AuditLog::query()->where('event', 'login')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('logging out is audited', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'));

    expect(AuditLog::query()->where('event', 'logout')->where('auditable_id', $user->id)->exists())->toBeTrue();
});

test('five wrong passwords trigger the rate limit and are all recorded', function () {
    User::factory()->create(['username' => 'rahim']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin('rahim', 'wrong');
    }

    attemptLogin('rahim')->assertSessionHasErrors('login');

    $this->assertGuest();
    expect(LoginHistory::query()->where('succeeded', false)->count())->toBe(5);
});

test('ten failures within fifteen minutes lock the username for fifteen minutes', function () {
    User::factory()->create(['username' => 'rahim']);

    foreach (range(1, 10) as $attempt) {
        LoginHistory::query()->create(['username_attempted' => 'rahim', 'succeeded' => false, 'ip_address' => '10.0.0.'.$attempt]);
    }

    attemptLogin('RAHIM')->assertSessionHasErrors('login');
    $this->assertGuest();

    $this->travel(16)->minutes();

    attemptLogin('rahim');
    $this->assertAuthenticated();
});
