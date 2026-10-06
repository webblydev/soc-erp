<?php

use App\Models\User;
use App\Modules\Foundation\Actions\CreateUser;
use App\Modules\Foundation\Actions\SaveNotificationPreferences;
use App\Modules\Foundation\Actions\UpdateUser;
use App\Modules\Foundation\Models\LoginHistory;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Notifications\AccountCreated;
use App\Modules\Foundation\Notifications\NewIpSignIn;
use App\Modules\Foundation\Notifications\PasswordResetByAdmin;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    ensureRole('accountant');
    ensureRole(Role::SUPER_ADMIN, ['is_system' => true]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function deliveryUserInput(array $overrides = []): array
{
    return [
        'name' => 'Rahim Uddin', 'username' => 'rahim', 'email' => 'rahim@example.com', 'phone' => null,
        'branch_id' => null, 'roles' => ['accountant'], 'permissions' => [],
        'password' => 'secret-pass', 'password_confirmation' => 'secret-pass', 'is_active' => true,
        ...$overrides,
    ];
}

test('channels follow the key config, the user preferences and the email setting', function () {
    $this->seed(SettingSeeder::class);
    $user = User::factory()->create(['email' => 'a@example.com']);
    $notification = new PasswordResetByAdmin;

    expect($notification->via($user))->toBe(['mail', 'database']);

    app(SaveNotificationPreferences::class)->handle($user, ['user.password_reset' => ['database' => false]]);
    expect($notification->via($user))->toBe(['mail']);

    Settings::set('notifications.email_enabled', false);
    expect($notification->via($user))->toBe([]);
});

test('mail is skipped for users without an email address', function () {
    expect((new PasswordResetByAdmin)->via(User::factory()->create(['email' => null])))->toBe(['database']);
});

test('a new user with an email is told their account exists, without the password', function () {
    Notification::fake();

    $user = app(CreateUser::class)->handle(deliveryUserInput(), superAdmin());

    Notification::assertSentTo($user, AccountCreated::class, function (AccountCreated $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return str_contains(implode(' ', $mail->introLines), 'rahim')
            && ! str_contains(serialize($mail), 'secret-pass');
    });
});

test('a new user without an email gets no account notification', function () {
    Notification::fake();

    app(CreateUser::class)->handle(deliveryUserInput(['email' => null]), superAdmin());

    Notification::assertNothingSent();
});

test('an admin setting another user\'s password notifies that user', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->syncRoles(['accountant']);

    app(UpdateUser::class)->handle($user, deliveryUserInput(['username' => $user->username, 'email' => $user->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass']), superAdmin());

    Notification::assertSentTo($user, PasswordResetByAdmin::class);
});

test('saving a user without a new password sends nothing', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->syncRoles(['accountant']);

    app(UpdateUser::class)->handle($user, deliveryUserInput(['username' => $user->username, 'email' => $user->email, 'password' => '', 'password_confirmation' => '']), superAdmin());

    Notification::assertNothingSent();
});

test('a finance user signing in from a new IP is alerted', function () {
    Notification::fake();
    $user = User::factory()->create(['username' => 'karim']);
    $user->syncRoles(['accountant']);
    LoginHistory::query()->create(['user_id' => $user->id, 'username_attempted' => 'karim', 'succeeded' => true, 'ip_address' => '10.0.0.1']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->post(route('login.store'), ['login' => 'karim', 'password' => 'password']);

    Notification::assertSentTo($user, NewIpSignIn::class, fn (NewIpSignIn $notification): bool => $notification->ipAddress === '10.0.0.9');
});

test('no alert for a known IP, a first sign-in or a role outside finance', function () {
    Notification::fake();

    $known = User::factory()->create(['username' => 'known']);
    $known->syncRoles(['accountant']);
    LoginHistory::query()->create(['user_id' => $known->id, 'username_attempted' => 'known', 'succeeded' => true, 'ip_address' => '10.0.0.9']);

    $first = User::factory()->create(['username' => 'first']);
    $first->syncRoles(['accountant']);

    $sales = User::factory()->create(['username' => 'sales']);
    $sales->syncRoles([ensureRole('sales_executive')->code]);
    LoginHistory::query()->create(['user_id' => $sales->id, 'username_attempted' => 'sales', 'succeeded' => true, 'ip_address' => '10.0.0.1']);

    foreach (['known', 'first', 'sales'] as $login) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->post(route('login.store'), ['login' => $login, 'password' => 'password']);
        auth()->logout();
    }

    Notification::assertNothingSent();
});
