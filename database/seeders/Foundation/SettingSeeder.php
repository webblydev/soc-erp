<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Setting;
use App\Support\Facades\Settings;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Initial keys from docs/01 §3.5; other modules seed their own groups.
     *
     * @var list<array{group: string, key: string, type: string, value: mixed, label: string}>
     */
    private const SETTINGS = [
        ['group' => 'general', 'key' => 'date_format', 'type' => 'string', 'value' => 'd-M-Y', 'label' => 'Date format'],
        ['group' => 'general', 'key' => 'timezone', 'type' => 'string', 'value' => 'Asia/Dhaka', 'label' => 'Timezone'],
        ['group' => 'general', 'key' => 'money_grouping', 'type' => 'string', 'value' => 'bd', 'label' => 'Money digit grouping'],
        ['group' => 'general', 'key' => 'session_timeout_minutes', 'type' => 'int', 'value' => 120, 'label' => 'Session idle timeout (minutes)'],
        ['group' => 'general', 'key' => 'password_min_length', 'type' => 'int', 'value' => 8, 'label' => 'Minimum password length'],
        ['group' => 'notifications', 'key' => 'email_enabled', 'type' => 'bool', 'value' => true, 'label' => 'Send email notifications'],
        ['group' => 'notifications', 'key' => 'sms_enabled', 'type' => 'bool', 'value' => false, 'label' => 'Send SMS notifications'],
        ['group' => 'notifications', 'key' => 'daily_digest_time', 'type' => 'string', 'value' => '09:00', 'label' => 'Daily digest time'],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $setting) {
            $row = Setting::query()->firstOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                ['type' => $setting['type'], 'value' => $setting['value'], 'label' => $setting['label']],
            );

            if ($row->type !== $setting['type'] || $row->label !== $setting['label']) {
                $row->update(['type' => $setting['type'], 'label' => $setting['label']]);
            }
        }

        Settings::flush();
    }
}
