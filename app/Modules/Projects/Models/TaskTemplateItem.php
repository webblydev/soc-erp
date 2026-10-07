<?php

namespace App\Modules\Projects\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One task of a template (docs/04 §3.9). Checklist is a list of titles (spec P17).
 *
 * @property int $id
 * @property int $task_template_id
 * @property string $title
 * @property int $task_type_id
 * @property int|null $project_phase_id
 * @property int|null $project_role_id
 * @property int $offset_days_start
 * @property int $duration_days
 * @property string|null $estimated_hours
 * @property list<string>|null $checklist
 * @property int $sort_order
 * @property int|null $depends_on_item_id
 * @property-read TaskType $type
 * @property-read ProjectPhase|null $phase
 * @property-read ProjectRole|null $role
 */
#[Fillable(['title', 'task_type_id', 'project_phase_id', 'project_role_id', 'offset_days_start', 'duration_days', 'estimated_hours', 'checklist', 'sort_order', 'depends_on_item_id'])]
class TaskTemplateItem extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offset_days_start' => 'integer',
            'duration_days' => 'integer',
            'estimated_hours' => 'decimal:2',
            'checklist' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TaskType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TaskType::class, 'task_type_id');
    }

    /**
     * @return BelongsTo<ProjectPhase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'project_phase_id');
    }

    /**
     * @return BelongsTo<ProjectRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(ProjectRole::class, 'project_role_id');
    }
}
