<?php

namespace Database\Seeders\Projects;

use App\Modules\Foundation\Models\Setting;
use App\Support\Facades\Settings;
use Illuminate\Database\Seeder;

class ProjectSettingSeeder extends Seeder
{
    /**
     * Projects settings (docs/04 §4, spec §3.1).
     *
     * @var list<array{group: string, key: string, type: string, value: mixed, label: string}>
     */
    private const SETTINGS = [
        ['group' => 'projects', 'key' => 'pm_can_view_all', 'type' => 'bool', 'value' => false, 'label' => 'Project managers see all projects'],
        ['group' => 'projects', 'key' => 'completion_from_tasks', 'type' => 'bool', 'value' => false, 'label' => 'Completion % from tasks (else manual)'],
        ['group' => 'projects', 'key' => 'require_contract_before_billing', 'type' => 'bool', 'value' => true, 'label' => 'Require a signed contract before billing'],
        ['group' => 'projects', 'key' => 'auto_apply_task_template', 'type' => 'bool', 'value' => true, 'label' => 'Offer a task template on new projects'],
        ['group' => 'projects', 'key' => 'task_overdue_notify', 'type' => 'bool', 'value' => true, 'label' => 'Notify about overdue tasks'],
        ['group' => 'projects', 'key' => 'archive_done_tasks_after_days', 'type' => 'int', 'value' => 30, 'label' => 'Archive completed tasks after (days)'],
        ['group' => 'projects', 'key' => 'default_status_on_conversion', 'type' => 'string', 'value' => 'CONTRACTED', 'label' => 'Project status on lead conversion (status code)'],
        ['group' => 'projects', 'key' => 'block_complete_with_open_checklist', 'type' => 'bool', 'value' => false, 'label' => 'Block completing tasks with open checklist items'],
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
