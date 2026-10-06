<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Models\User;
use App\Modules\Foundation\Livewire\Profile\TwoFactor;
use App\Modules\Foundation\Models\LoginHistory;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    ensureRole('finance_manager');
});

function financeManager(): User
{
    $user = User::factory()->create(['username' => 'fm1', 'password' => Hash::make('password')]);
    $user->syncRoles(['finance_manager']);

    return $user;
}

test('users in a required role without 2FA are sent to set it up', function () {
    Settings::set('general.require_2fa_roles', ['finance_manager']);

    $this->actingAs(financeManager())->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));
    $this->get(route('two-factor.setup'))->assertOk();
});

test('nobody is forced while the role list is empty', function () {
    $this->actingAs(financeManager())->get(route('dashboard'))->assertOk();
});

test('users who already use 2FA are not redirected', function () {
    Settings::set('general.require_2fa_roles', ['finance_manager']);
    $user = User::factory()->withTwoFactor()->create();
    $user->syncRoles(['finance_manager']);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('login asks for the code when 2FA is on', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'otpuser', 'password' => Hash::make('password')]);

    $this->post(route('login.store'), ['login' => 'otpuser', 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('enabling and confirming 2FA from the panel, with the QR labelled by username', function () {
    $user = financeManager();

    $component = Livewire::actingAs($user)->test(TwoFactor::class)->call('enable');

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and(urldecode($user->twoFactorQrCodeUrl()))->toContain('fm1');

    $component->set('code', '000000')->call('confirm')->assertHasErrors(['code']);

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    $component->set('code', $code)->call('confirm')->assertHasNoErrors();

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('2FA cannot be turned off while a role requires it', function () {
    Settings::set('general.require_2fa_roles', ['finance_manager']);
    $user = User::factory()->withTwoFactor()->create(['password' => Hash::make('password')]);
    $user->syncRoles(['finance_manager']);

    Livewire::actingAs($user)->test(TwoFactor::class)
        ->set('current_password', 'password')
        ->call('disable')
        ->assertDispatched('toast', type: 'error');

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});

test('turning 2FA off needs the current password', function () {
    $user = User::factory()->withTwoFactor()->create(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(TwoFactor::class)
        ->set('current_password', 'wrong')
        ->call('disable')
        ->assertHasErrors(['current_password']);

    Livewire::actingAs($user)->test(TwoFactor::class)
        ->set('current_password', 'password')
        ->call('disable')
        ->assertHasNoErrors();

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

test('the 2FA challenge is throttled', function () {
    User::factory()->withTwoFactor()->create(['username' => 'otpuser', 'password' => Hash::make('password')]);
    $this->post(route('login.store'), ['login' => 'otpuser', 'password' => 'password']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertStatus(302);
    }

    $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertStatus(429);
});

test('Fortify route cannot turn off 2FA for a required role', function () {
    Settings::set('general.require_2fa_roles', ['finance_manager']);
    $user = User::factory()->withTwoFactor()->create();
    $user->syncRoles(['finance_manager']);

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->deleteJson('/user/two-factor-authentication')->assertStatus(422);

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});

test('Fortify route turns off 2FA for other users', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->deleteJson('/user/two-factor-authentication')->assertSuccessful();

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

test('2FA and password-change middleware also run on Livewire requests', function () {
    expect(Livewire::getPersistentMiddleware())
        ->toContain(EnsureTwoFactorEnabled::class, EnsurePasswordChanged::class);
});

test('a login writes exactly one history row', function () {
    User::factory()->create(['username' => 'single']);

    $this->post(route('login.store'), ['login' => 'single', 'password' => 'password']);
    expect(LoginHistory::where('succeeded', true)->count())->toBe(1);

    auth()->logout();
    $this->post(route('login.store'), ['login' => 'single', 'password' => 'bad']);
    expect(LoginHistory::where('succeeded', false)->count())->toBe(1);
});

test('the challenge accepts a valid code', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'otpuser', 'password' => Hash::make('password')]);
    $this->post(route('login.store'), ['login' => 'otpuser', 'password' => 'password']);

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    $this->post(route('two-factor.login.store'), ['code' => $code])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('the challenge accepts a recovery code', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'otpuser', 'password' => Hash::make('password')]);
    $this->post(route('login.store'), ['login' => 'otpuser', 'password' => 'password']);

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('the challenge rejects a wrong code', function () {
    User::factory()->withTwoFactor()->create(['username' => 'otpuser', 'password' => Hash::make('password')]);
    $this->post(route('login.store'), ['login' => 'otpuser', 'password' => 'password']);

    $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('new recovery codes need the current password', function () {
    $user = User::factory()->withTwoFactor()->create(['password' => Hash::make('password')]);
    $before = $user->two_factor_recovery_codes;

    Livewire::actingAs($user)->test(TwoFactor::class)
        ->set('current_password', 'wrong')
        ->call('regenerateRecoveryCodes')
        ->assertHasErrors(['current_password']);

    expect($user->fresh()->two_factor_recovery_codes)->toBe($before);

    Livewire::actingAs($user)->test(TwoFactor::class)
        ->set('current_password', 'password')
        ->call('regenerateRecoveryCodes')
        ->assertHasNoErrors();

    expect($user->fresh()->two_factor_recovery_codes)->not->toBe($before);
});
