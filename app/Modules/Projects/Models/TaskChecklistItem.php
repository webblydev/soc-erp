<?php

namespace App\Modules\Projects\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A checklist item of a task (docs/04 §3.9).
 *
 * @property int $id
 * @property int $task_id
 * @property string $title
 * @property bool $is_done
 * @property int|null $done_by
 * @property Carbon|null $done_at
 * @property int $sort_order
 * @property-read Task $task
 * @property-read User|null $doer
 */
#[Fillable(['title', 'is_done', 'done_by', 'done_at', 'sort_order'])]
class TaskChecklistItem extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'done_at' => 'datetime', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
