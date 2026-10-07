<?php

namespace App\Modules\Estimation\Models;

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use App\Support\Collaboration\HasAttachments;
use Database\Factories\Estimation\SiteInspectionFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A finding of a site inspection, followed up until closed (docs/05 §3.9, spec E16).
 *
 * @property int $id
 * @property int $site_inspection_id
 * @property int $project_id
 * @property string|null $location
 * @property string $description
 * @property string|null $finding
 * @property int|null $finding_category_id
 * @property int $finding_severity_id
 * @property string|null $action_required
 * @property string|null $responsible_type
 * @property int|null $responsible_id
 * @property Carbon|null $due_date
 * @property string|null $found_by_name
 * @property int $finding_status_id
 * @property Carbon|null $closed_on
 * @property int|null $closed_by
 * @property string|null $closure_note
 * @property Carbon|null $overdue_notified_at
 * @property int|null $legacy_detail_ref
 * @property int $sort_order
 * @property-read SiteInspection $inspection
 * @property-read Project $project
 * @property-read FindingCategory|null $category
 * @property-read FindingSeverity $severity
 * @property-read FindingStatus $status
 * @property-read Employee|null $responsibleEmployee
 */
#[Fillable(['location', 'description', 'finding', 'finding_category_id', 'finding_severity_id', 'action_required', 'responsible_type', 'responsible_id', 'due_date', 'found_by_name', 'sort_order'])]
#[UseFactory(SiteInspectionFindingFactory::class)]
class SiteInspectionFinding extends Model implements Collaborative
{
    /** @use HasFactory<SiteInspectionFindingFactory> */
    use Auditable, HasAttachments, HasFactory, TracksAuthors;

    public const RESPONSIBLE_EMPLOYEE = 'employee';

    public const RESPONSIBLE_CUSTOMER = 'customer';

    public const RESPONSIBLE_CONTRACTOR = 'contractor';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_date' => 'date', 'closed_on' => 'date', 'overdue_notified_at' => 'datetime'];
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('view', $this->inspection);
    }

    /**
     * Findings on projects the user may see (spec E4).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn($this->qualifyColumn('project_id'), Project::query()->visibleTo($user)->select('projects.id'));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('finding_status_id'), FindingStatus::query()->select('id')->where('is_closed', false));
    }

    /**
     * Open, HIGH or CRITICAL.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSerious(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('finding_severity_id'), FindingSeverity::query()->select('id')->where('requires_follow_up', true));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereDate($this->qualifyColumn('due_date'), '<', today());
    }

    public function isClosed(): bool
    {
        return (bool) $this->status->is_closed;
    }

    public function isOverdue(): bool
    {
        return ! $this->isClosed() && $this->due_date !== null && $this->due_date->lt(today());
    }

    public function requiresFollowUp(): bool
    {
        return (bool) $this->severity->requires_follow_up;
    }

    public function responsibleEmployeeId(): ?int
    {
        return $this->responsible_type === self::RESPONSIBLE_EMPLOYEE ? $this->responsible_id : null;
    }

    /**
     * Who must act, as text for lists and prints.
     */
    public function responsibleName(): ?string
    {
        return match ($this->responsible_type) {
            self::RESPONSIBLE_EMPLOYEE => $this->responsibleEmployee?->full_name,
            self::RESPONSIBLE_CUSTOMER => Customer::query()->whereKey($this->responsible_id)->value('name'),
            self::RESPONSIBLE_CONTRACTOR => $this->inspection->contractor_name ?? __('Contractor'),
            default => null,
        };
    }

    /**
     * @return BelongsTo<SiteInspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(SiteInspection::class, 'site_inspection_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<FindingCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FindingCategory::class, 'finding_category_id');
    }

    /**
     * @return BelongsTo<FindingSeverity, $this>
     */
    public function severity(): BelongsTo
    {
        return $this->belongsTo(FindingSeverity::class, 'finding_severity_id');
    }

    /**
     * @return BelongsTo<FindingStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(FindingStatus::class, 'finding_status_id');
    }

    /**
     * The responsible employee; meaningful only when responsible_type is employee.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
