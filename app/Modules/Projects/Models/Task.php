<?php

namespace App\Modules\Projects\Models;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Services\ProjectAccess;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use App\Support\Collaboration\HasAttachments;
use Database\Factories\Projects\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A task, on a project or general (docs/04 §3.8). Status, completion and hours are written by
 * their Actions only. No money columns.
 *
 * @property int $id
 * @property string $task_number
 * @property int|null $project_id
 * @property int|null $parent_id
 * @property int $task_type_id
 * @property int|null $project_phase_id
 * @property string $title
 * @property string|null $description
 * @property string|null $file_number
 * @property int $assigned_by
 * @property int|null $assignee_employee_id
 * @property int|null $support_officer_id
 * @property int|null $reviewer_employee_id
 * @property int $task_priority_id
 * @property bool $is_important
 * @property int $task_status_id
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 * @property string|null $estimated_hours
 * @property string $actual_hours
 * @property int $progress_pct
 * @property Carbon|null $archived_at
 * @property string|null $blocked_reason
 * @property int $sort_order
 * @property Carbon $created_at
 * @property-read Project|null $project
 * @property-read Task|null $parent
 * @property-read TaskType $type
 * @property-read ProjectPhase|null $phase
 * @property-read TaskStatus $status
 * @property-read TaskPriority $priority
 * @property-read Employee|null $assignee
 * @property-read Employee|null $supportOfficer
 * @property-read Employee|null $reviewer
 * @property-read User $assigner
 */
#[Fillable(['project_phase_id', 'title', 'description', 'file_number', 'task_priority_id', 'is_important', 'start_date', 'due_date', 'estimated_hours', 'sort_order'])]
#[UseFactory(TaskFactory::class)]
class Task extends Model implements Collaborative
{
    /** @use HasFactory<TaskFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_important' => 'boolean',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
            'progress_pct' => 'integer',
            'archived_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'task_number';
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('view', $this);
    }

    /**
     * Tasks the user may see (spec P8).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        app(ProjectAccess::class)->scopeTasks($query, $user);
    }

    /**
     * Not done and not cancelled.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('task_status_id'), TaskStatus::query()->select('id')->where('is_done', false)->where('is_cancelled', false));
    }

    /**
     * Open with a due date before today (PRJ-BR-14).
     *
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereDate($this->qualifyColumn('due_date'), '<', today());
    }

    public function isOpen(): bool
    {
        return ! $this->status->is_done && ! $this->status->is_cancelled;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date !== null && $this->due_date->lt(today());
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
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
     * @return BelongsTo<TaskStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'task_status_id');
    }

    /**
     * @return BelongsTo<TaskPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class, 'task_priority_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_employee_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function supportOfficer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'support_officer_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * @return HasMany<TaskChecklistItem, $this>
     */
    public function checklist(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest('created_at')->latest('id');
    }

    /**
     * @return HasMany<TaskTimeLog, $this>
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class)->latest('work_date')->latest('id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers');
    }

    /**
     * Tasks this one waits for (docs/04 §6.3).
     *
     * @return BelongsToMany<Task, $this>
     */
    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id');
    }
}
