<?php

namespace Database\Seeders\Estimation;

use App\Modules\Foundation\Models\Setting;
use App\Support\Facades\Settings;
use Illuminate\Database\Seeder;

class EstimationSettingSeeder extends Seeder
{
    /**
     * Estimation & Site settings (docs/05 §4, spec §3.1).
     *
     * @var list<array{group: string, key: string, type: string, value: mixed, label: string}>
     */
    private const SETTINGS = [
        ['group' => 'estimation', 'key' => 'pm_approval_limit', 'type' => 'int', 'value' => 5000000, 'label' => 'Project managers approve estimates up to (৳)'],
        ['group' => 'estimation', 'key' => 'auto_budget_from_approved_estimate', 'type' => 'bool', 'value' => true, 'label' => 'Build the budget from approved estimates'],
        ['group' => 'site', 'key' => 'mb_requires_verification', 'type' => 'bool', 'value' => true, 'label' => 'MB entries need verification by another person'],
        ['group' => 'site', 'key' => 'mb_allow_exceed_boq_pct', 'type' => 'int', 'value' => 10, 'label' => 'MB may exceed the BOQ quantity by (%)'],
        ['group' => 'site', 'key' => 'finding_overdue_notify', 'type' => 'bool', 'value' => true, 'label' => 'Notify about overdue findings'],
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
