<?php

use App\Models\User;
use App\Modules\Foundation\Actions\SaveNotificationPreferences;
use App\Modules\Foundation\Models\NotificationPreference;

test('preferences default to enabled for every registered key and channel', function () {
    $matrix = NotificationPreference::matrixFor(User::factory()->create());

    expect($matrix)->toHaveKeys(['user.created', 'user.password_reset', 'security.login_new_ip'])
        ->and($matrix['security.login_new_ip'])->toBe(['mail' => true, 'database' => true]);
});

test('saving stores choices and ignores unknown keys and channels', function () {
    $user = User::factory()->create();

    app(SaveNotificationPreferences::class)->handle($user, [
        'security.login_new_ip' => ['mail' => false, 'sms' => true],
        'made.up' => ['mail' => false],
    ]);

    expect(NotificationPreference::matrixFor($user)['security.login_new_ip'])->toBe(['mail' => false, 'database' => true])
        ->and(NotificationPreference::query()->count())->toBe(1);
});
