<?php

use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\Setting;
use App\Support\Facades\Settings;
use Database\Seeders\Foundation\SettingSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(fn () => $this->seed(SettingSeeder::class));

test('values are returned with their declared type', function () {
    expect(Settings::get('general.session_timeout_minutes'))->toBe(120)
        ->and(Settings::get('notifications.email_enabled'))->toBeTrue()
        ->and(Settings::get('general.timezone'))->toBe('Asia/Dhaka');
});

test('missing keys return the default', function () {
    expect(Settings::get('general.nope', 'fallback'))->toBe('fallback');
});

test('setting a value updates the cache and writes an audit entry', function () {
    expect(Settings::get('general.session_timeout_minutes'))->toBe(120);

    Settings::set('general.session_timeout_minutes', '30');

    expect(Settings::get('general.session_timeout_minutes'))->toBe(30)
        ->and(AuditLog::query()->where('auditable_type', 'setting')->where('event', 'updated')->count())->toBe(1);
});

test('setting an unknown key fails', function () {
    Settings::set('general.unknown', 1);
})->throws(ModelNotFoundException::class);

test('re-seeding keeps values changed by an admin', function () {
    Settings::set('general.password_min_length', 12);

    $this->seed(SettingSeeder::class);

    expect(Setting::query()->where('key', 'password_min_length')->value('value'))->toBe(12);
});

test('re-seeding corrects the type of an existing row but keeps its value', function () {
    Setting::query()->where('key', 'password_min_length')->update(['type' => 'string', 'value' => json_encode(12)]);

    $this->seed(SettingSeeder::class);

    $row = Setting::query()->where('key', 'password_min_length')->first();
    expect($row->type)->toBe('int')->and($row->value)->toBe(12);
});
