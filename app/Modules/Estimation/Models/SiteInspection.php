<?php

namespace App\Modules\Estimation\Models;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use App\Support\Collaboration\HasAttachments;
use Database\Factories\Estimation\SiteInspectionFactory;
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
 * A site inspection, the v1 Project Visit (docs/05 §3.8).
 *
 * @property int $id
 * @property string $inspection_number
 * @property int $project_id
 * @property int $inspection_type_id
 * @property Carbon $inspection_date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $site_address
 * @property string|null $permittee_name
 * @property string|null $contractor_name
 * @property int|null $contractor_vendor_id
 * @property int|null $project_engineer_id
 * @property string|null $project_engineer_name
 * @property string|null $field_office_phone
 * @property string|null $weather
 * @property int|null $workers_on_site
 * @property string|null $work_progress_summary
 * @property int $inspection_status_id
 * @property string|null $client_representative
 * @property int|null $legacy_visit_ref
 * @property int|null $created_by
 * @property-read Project $project
 * @property-read InspectionType $type
 * @property-read InspectionStatus $status
 * @property-read Employee|null $engineer
 */
#[Fillable(['inspection_type_id', 'inspection_date', 'start_time', 'end_time', 'site_address', 'permittee_name', 'contractor_name', 'project_engineer_id', 'field_office_phone', 'weather', 'workers_on_site', 'work_progress_summary', 'client_representative'])]
#[UseFactory(SiteInspectionFactory::class)]
class SiteInspection extends Model implements Collaborative
{
    /** @use HasFactory<SiteInspectionFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['inspection_date' => 'date', 'workers_on_site' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'inspection_number';
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('view', $this);
    }

    /**
     * Inspections on projects the user may see (spec E4).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn($this->qualifyColumn('project_id'), Project::query()->visibleTo($user)->select('projects.id'));
    }

    public function hasStatus(string $code): bool
    {
        return $this->status->code === $code;
    }

    /**
     * The engineer's name: the employee, else the v1 free text.
     */
    public function engineerName(): ?string
    {
        return $this->engineer->full_name ?? $this->project_engineer_name;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<InspectionType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(InspectionType::class, 'inspection_type_id');
    }

    /**
     * @return BelongsTo<InspectionStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(InspectionStatus::class, 'inspection_status_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function engineer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'project_engineer_id');
    }

    /**
     * @return HasMany<SiteInspectionFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(SiteInspectionFinding::class)->orderBy('sort_order')->orderBy('id');
    }
}
