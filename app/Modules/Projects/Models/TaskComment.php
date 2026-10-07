<?php

namespace App\Modules\Projects\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A comment on a task; @username mentions notify (spec P16).
 *
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property string $body
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Task $task
 */
#[Fillable(['body'])]
class TaskComment extends Model
{
    use Auditable, SoftDeletes;

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
