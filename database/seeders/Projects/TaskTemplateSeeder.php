<?php

namespace Database\Seeders\Projects;

use App\Modules\Catalog\Models\Service;
use App\Modules\Projects\Models\ProjectPhase;
use App\Modules\Projects\Models\ProjectRole;
use App\Modules\Projects\Models\TaskTemplate;
use App\Modules\Projects\Models\TaskType;
use Illuminate\Database\Seeder;

/**
 * The "Building Design & RAJUK Approval" template (docs/04 §3.9, spec P17). Created once; later
 * admin edits are kept.
 */
class TaskTemplateSeeder extends Seeder
{
    public const NAME = 'Building Design & RAJUK Approval';

    /**
     * title, task type, phase, role, start offset, duration, hours, checklist
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: int, 5: int, 6: int, 7?: list<string>}>
     */
    private const ITEMS = [
        ['Site survey', 'SURVEY', 'DESIGN', 'SURVEYOR', 0, 3, 6, ['Boundary measured', 'Photos taken', 'Survey drawing uploaded']],
        ['Architectural concept', 'DESIGN', 'DESIGN', 'ARCHITECT', 3, 7, 16],
        ['Client approval of concept', 'CLIENT_MEETING', 'DESIGN', 'PM', 10, 3, 2],
        ['Architectural drawings', 'DRAWING', 'DESIGN', 'ARCHITECT', 13, 10, 24],
        ['Structural design', 'STRUCTURAL_CALC', 'DESIGN', 'STRUCTURAL', 23, 10, 20],
        ['MEP drawings', 'DRAWING', 'DESIGN', 'MEP', 23, 7, 12],
        ['Soil test coordination', 'SITE_VISIT', 'DESIGN', 'SITE_ENGINEER', 13, 7, 4],
        ['RAJUK file preparation', 'APPROVAL_FILE', 'APPROVAL', 'SUPPORT_OFFICER', 33, 5, 8, ['Land deed', 'Mutation', 'DCR', 'Tax receipt', 'NID', 'Soil report', 'Drawings']],
        ['RAJUK submission', 'APPROVAL_FILE', 'APPROVAL', 'SUPPORT_OFFICER', 38, 1, 2],
        ['Query response', 'APPROVAL_FILE', 'APPROVAL', 'ARCHITECT', 39, 30, 6],
        ['Approval collection', 'APPROVAL_FILE', 'APPROVAL', 'SUPPORT_OFFICER', 69, 30, 2],
        ['Handover of approved drawings', 'CLIENT_MEETING', 'HANDOVER', 'PM', 99, 2, 2],
    ];

    public function run(): void
    {
        if (TaskTemplate::query()->withTrashed()->where('name', self::NAME)->exists()) {
            return;
        }

        $types = TaskType::query()->pluck('id', 'code');
        $phases = ProjectPhase::query()->pluck('id', 'code');
        $roles = ProjectRole::query()->pluck('id', 'code');

        $template = TaskTemplate::query()->create([
            'name' => self::NAME,
            'service_id' => Service::query()->where('code', 'BDRA')->value('id'),
            'is_active' => true,
        ]);

        $previous = null;

        foreach (self::ITEMS as $index => $item) {
            $previous = $template->items()->create([
                'title' => $item[0],
                'task_type_id' => $types[$item[1]],
                'project_phase_id' => $phases[$item[2]],
                'project_role_id' => $roles[$item[3]],
                'offset_days_start' => $item[4],
                'duration_days' => $item[5],
                'estimated_hours' => $item[6],
                'checklist' => $item[7] ?? null,
                'sort_order' => $index + 1,
                'depends_on_item_id' => $index >= 7 ? $previous?->id : null,
            ]);
        }
    }
}
