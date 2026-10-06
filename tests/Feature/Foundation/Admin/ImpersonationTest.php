<?php

use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use App\Modules\Foundation\Actions\StartImpersonation;
use App\Modules\Foundation\Actions\UpdateProfile;
use App\Modules\Foundation\Livewire\Admin\Users\Index;
use App\Modules\Foundation\Livewire\Profile\Edit;
use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\LoginHistory;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = superAdmin();
    $this->target = User::factory()->create(['name' => 'Target Person']);
});

test('only super admins can impersonate, and never themselves, super admins or inactive users', function () {
    $this->actingAs($this->admin);
    $plain = userWithPermissions('admin.users.impersonate');

    expectValidationError(fn () => app(StartImpersonation::class)->handle($plain, $this->target), 'user');
    expectValidationError(fn () => app(StartImpersonation::class)->handle($this->admin, $this->admin), 'user');
    expectValidationError(fn () => app(StartImpersonation::class)->handle($this->admin, superAdmin()), 'user');
    expectValidationError(fn () => app(StartImpersonation::class)->handle($this->admin, User::factory()->inactive()->create()), 'user');
});

test('starting switches the user, audits both ids and writes no login rows', function () {
    $this->actingAs($this->admin);

    app(StartImpersonation::class)->handle($this->admin, $this->target);

    expect(Auth::id())->toBe($this->target->id)
        ->and(session(HandleImpersonation::SESSION_KEY))->toBe($this->admin->id)
        ->and(AuditLog::query()->where('event', 'impersonation_started')->first()?->new_values)->toBe(['impersonator_id' => $this->admin->id, 'user_id' => $this->target->id])
        ->and(AuditLog::query()->where('event', 'login')->where('auditable_id', $this->target->id)->exists())->toBeFalse()
        ->and(LoginHistory::query()->count())->toBe(0)
        ->and($this->target->fresh()->last_login_at)->toBeNull();
});

test('the banner shows and the forced password change is skipped while impersonating', function () {
    $this->target->forceFill(['must_change_password' => true])->save();

    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="impersonation-banner"', false)
        ->assertSee('Target Person');
});

test('user and role admin is blocked while impersonating', function () {
    createPermissions('admin.users.view');
    $this->target->syncDirectPermissions(['admin.users.view']);

    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id])
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('stopping restores the super admin and audits the end', function () {
    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id])
        ->post(route('impersonation.stop'))
        ->assertRedirect(route('admin.users.index'));

    expect(Auth::id())->toBe($this->admin->id)
        ->and(session()->has(HandleImpersonation::SESSION_KEY))->toBeFalse()
        ->and(AuditLog::query()->where('event', 'impersonation_ended')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

test('stopping without an active impersonation changes nothing', function () {
    $this->actingAs($this->target)
        ->post(route('impersonation.stop'))
        ->assertRedirect(route('admin.users.index'));

    expect(Auth::id())->toBe($this->target->id)
        ->and(AuditLog::query()->where('event', 'impersonation_ended')->exists())->toBeFalse();
});

test('super admins start impersonation from the users list', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('impersonate', $this->target->id)
        ->assertRedirect(route('dashboard'));

    expect(Auth::id())->toBe($this->target->id);
});

test('users without the impersonate permission cannot call impersonate', function () {
    $viewer = userWithPermissions('admin.users.view');

    Livewire::actingAs($viewer)
        ->test(Index::class)
        ->call('impersonate', $this->target->id)
        ->assertForbidden();
});

test('role admin is blocked while impersonating', function () {
    createPermissions('admin.roles.view');
    $this->target->syncDirectPermissions(['admin.roles.view']);

    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id])
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});

test('the impersonation guard also applies to Livewire requests', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(HandleImpersonation::class);
});

test('stopping writes no login audit row for the impersonator', function () {
    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id])
        ->post(route('impersonation.stop'));

    expect(AuditLog::query()->where('event', 'login')->where('auditable_id', $this->admin->id)->doesntExist())->toBeTrue();
});

test('a second impersonation is refused while one is active', function () {
    $this->actingAs($this->admin);
    session()->put(HandleImpersonation::SESSION_KEY, $this->admin->id);
    $other = User::factory()->create();

    expectValidationError(fn () => app(StartImpersonation::class)->handle($this->admin, $other), 'user');
    expect(Auth::id())->toBe($this->admin->id);
});

test('impersonating drops password confirmation and blocks the confirmation routes', function () {
    $this->actingAs($this->admin);
    session()->put('auth.password_confirmed_at', time());

    app(StartImpersonation::class)->handle($this->admin, $this->target);

    expect(session()->has('auth.password_confirmed_at'))->toBeFalse();

    $this->get(route('password.confirmation'))->assertForbidden();
});

test('stopping drops password confirmation', function () {
    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id, 'auth.password_confirmed_at' => time()])
        ->post(route('impersonation.stop'));

    expect(session()->has('auth.password_confirmed_at'))->toBeFalse();
});

test('the profile hides the password tab while impersonating', function () {
    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id]);

    Livewire::withQueryParams(['tab' => 'password'])
        ->test(Edit::class)
        ->assertSet('tab', 'details')
        ->call('savePassword')
        ->assertForbidden();
});

test('changes made while impersonating record the impersonator', function () {
    $this->actingAs($this->target)->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id]);

    app(UpdateProfile::class)->handle($this->target, ['name' => 'Renamed Person', 'phone' => '']);

    $entry = AuditLog::query()->where('event', 'updated')->where('auditable_id', $this->target->id)->sole();
    expect($entry->user_id)->toBe($this->target->id)
        ->and($entry->impersonator_id)->toBe($this->admin->id);
});

test('changes made without impersonation have no impersonator', function () {
    $this->actingAs($this->target);

    app(UpdateProfile::class)->handle($this->target, ['name' => 'Renamed Person', 'phone' => '']);

    expect(AuditLog::query()->where('event', 'updated')->where('auditable_id', $this->target->id)->sole()->impersonator_id)->toBeNull();
});

test('logging out while impersonating ends the impersonation instead of logging out', function () {
    $this->actingAs($this->target)
        ->withSession([HandleImpersonation::SESSION_KEY => $this->admin->id])
        ->post(route('logout'));

    $ended = AuditLog::query()->where('event', 'impersonation_ended')->sole();
    expect($ended->auditable_id)->toBe($this->target->id)
        ->and($ended->user_id)->toBe($this->admin->id)
        ->and($ended->new_values)->toBe(['impersonator_id' => $this->admin->id, 'user_id' => $this->target->id])
        ->and(AuditLog::query()->where('event', 'logout')->exists())->toBeFalse();
});
