<?php

namespace App\Modules\Projects\Models;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use App\Support\Collaboration\HasAttachments;
use Database\Factories\Projects\ProjectApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A permit / approval tracked for a project (docs/04 §3.10). The status changes only through
 * AddApprovalEvent (spec P18).
 *
 * @property int $id
 * @property int $project_id
 * @property int $approval_authority_id
 * @property int $approval_type_id
 * @property string|null $reference_no
 * @property int|null $responsible_employee_id
 * @property int $approval_status_id
 * @property Carbon|null $prepared_on
 * @property Carbon|null $submitted_on
 * @property Carbon|null $expected_on
 * @property Carbon|null $approved_on
 * @property Carbon|null $valid_until
 * @property string|null $authority_fee
 * @property int|null $fee_expense_id
 * @property string|null $notes
 * @property Carbon|null $overdue_notified_at
 * @property-read Project $project
 * @property-read ApprovalAuthority $authority
 * @property-read ApprovalType $type
 * @property-read ApprovalStatus $status
 * @property-read Employee|null $responsible
 */
#[Fillable(['approval_authority_id', 'approval_type_id', 'reference_no', 'responsible_employee_id', 'prepared_on', 'submitted_on', 'expected_on', 'approved_on', 'valid_until', 'authority_fee', 'notes'])]
#[UseFactory(ProjectApprovalFactory::class)]
class ProjectApproval extends Model implements Collaborative
{
    /** @use HasFactory<ProjectApprovalFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prepared_on' => 'date',
            'submitted_on' => 'date',
            'expected_on' => 'date',
            'approved_on' => 'date',
            'valid_until' => 'date',
            'authority_fee' => 'decimal:2',
            'overdue_notified_at' => 'datetime',
        ];
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('view', $this);
    }

    /**
     * Approvals on projects the user may see.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn($this->qualifyColumn('project_id'), Project::query()->visibleTo($user)->select('projects.id'));
    }

    /**
     * Not in a final status.
     *
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('approval_status_id'), ApprovalStatus::query()->select('id')->where('is_final', false));
    }

    /**
     * Pending and past the expected date (PRJ-BR-15).
     *
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->pending()->whereDate($this->qualifyColumn('expected_on'), '<', today());
    }

    public function isFinal(): bool
    {
        return (bool) $this->status->is_final;
    }

    public function isOverdue(): bool
    {
        return ! $this->isFinal() && $this->expected_on !== null && $this->expected_on->lt(today());
    }

    /**
     * Days since submission (or preparation) until approval or today.
     */
    public function daysElapsed(): ?int
    {
        $from = $this->submitted_on ?? $this->prepared_on;

        return $from === null ? null : (int) $from->diffInDays($this->approved_on ?? today());
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ApprovalAuthority, $this>
     */
    public function authority(): BelongsTo
    {
        return $this->belongsTo(ApprovalAuthority::class, 'approval_authority_id');
    }

    /**
     * @return BelongsTo<ApprovalType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ApprovalType::class, 'approval_type_id');
    }

    /**
     * @return BelongsTo<ApprovalStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ApprovalStatus::class, 'approval_status_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    /**
     * @return HasMany<ProjectApprovalEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ProjectApprovalEvent::class)->latest('event_date')->latest('id');
    }

    /**
     * @return HasMany<ApprovalChecklistItem, $this>
     */
    public function checklist(): HasMany
    {
        return $this->hasMany(ApprovalChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
