<?php

namespace Database\Seeders\Crm;

use App\Modules\Foundation\Models\Setting;
use App\Support\Facades\Settings;
use Illuminate\Database\Seeder;

class CrmSettingSeeder extends Seeder
{
    /**
     * CRM settings (docs/03 §4).
     *
     * @var list<array{group: string, key: string, type: string, value: mixed, label: string}>
     */
    private const SETTINGS = [
        ['group' => 'crm', 'key' => 'duplicate_check_fields', 'type' => 'json', 'value' => ['phone', 'whatsapp', 'email'], 'label' => 'Duplicate check fields'],
        ['group' => 'crm', 'key' => 'auto_assign_mode', 'type' => 'string', 'value' => 'none', 'label' => 'Auto-assign mode (none, round_robin_team)'],
        ['group' => 'crm', 'key' => 'follow_up_required_on_status', 'type' => 'json', 'value' => ['CONTACTED', 'QUALIFIED', 'MEETING', 'SITE_VISIT', 'PROPOSAL', 'NEGOTIATION'], 'label' => 'Statuses that need an open follow-up'],
        ['group' => 'crm', 'key' => 'stale_lead_days', 'type' => 'int', 'value' => 14, 'label' => 'Days without activity before a lead is stale'],
        ['group' => 'crm', 'key' => 'reminder_lead_minutes', 'type' => 'int', 'value' => 30, 'label' => 'Default reminder (minutes before)'],
        ['group' => 'crm', 'key' => 'lost_reason_required', 'type' => 'bool', 'value' => true, 'label' => 'Lost reason required'],
        ['group' => 'crm', 'key' => 'allow_convert_without_project', 'type' => 'bool', 'value' => false, 'label' => 'Allow conversion without a project'],
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
