<?php

namespace App\Modules\Projects\Models;

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Location;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Services\ProjectAccess;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use App\Support\Collaboration\HasAttachments;
use App\Support\Collaboration\HasNotes;
use Database\Factories\Projects\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * SOC's job file (docs/04 §3.2). Number, business line, status, contract value and the
 * people columns are written only by their Actions.
 *
 * @property int $id
 * @property string $project_number
 * @property string $name
 * @property int|null $customer_id
 * @property int $business_line_id
 * @property int $project_type_id
 * @property int $project_status_id
 * @property int|null $project_phase_id
 * @property int|null $branch_id
 * @property int|null $source_lead_id
 * @property string|null $description
 * @property string|null $site_address
 * @property int|null $location_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $plot_no
 * @property string|null $land_area
 * @property int|null $land_area_unit_id
 * @property int|null $floors
 * @property int|null $basements
 * @property string|null $built_up_area_sft
 * @property int|null $project_manager_id
 * @property int|null $supervisor_id
 * @property int|null $support_officer_id
 * @property Carbon|null $start_date
 * @property Carbon|null $expected_end_date
 * @property Carbon|null $handover_date
 * @property Carbon|null $actual_end_date
 * @property string $contract_value
 * @property string $budget_cost
 * @property string|null $retention_pct
 * @property int|null $hold_reason_id
 * @property string|null $cancel_reason
 * @property string $completion_pct
 * @property list<string>|null $legacy_project_ids
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read Customer|null $customer
 * @property-read BusinessLine $businessLine
 * @property-read ProjectType $type
 * @property-read ProjectStatus $status
 * @property-read ProjectPhase|null $phase
 * @property-read Employee|null $manager
 * @property-read ProjectContract|null $contract
 */
#[Fillable([
    'name', 'description', 'site_address', 'location_id', 'latitude', 'longitude', 'plot_no', 'land_area', 'land_area_unit_id',
    'floors', 'basements', 'built_up_area_sft', 'branch_id', 'start_date', 'expected_end_date', 'handover_date',
    'retention_pct', 'notes',
])]
#[UseFactory(ProjectFactory::class)]
class Project extends Model implements Collaborative
{
    /** @use HasFactory<ProjectFactory> */
    use Auditable, HasAttachments, HasFactory, HasNotes, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'land_area' => 'decimal:4',
            'floors' => 'integer',
            'basements' => 'integer',
            'built_up_area_sft' => 'decimal:2',
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'handover_date' => 'date',
            'actual_end_date' => 'date',
            'contract_value' => 'decimal:2',
            'budget_cost' => 'decimal:2',
            'retention_pct' => 'decimal:4',
            'completion_pct' => 'decimal:2',
            'legacy_project_ids' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'project_number';
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('view', $this);
    }

    /**
     * Projects the user may see (spec P7).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        app(ProjectAccess::class)->scopeProjects($query, $user);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('project_status_id'), ProjectStatus::query()->select('id')->where('is_closed', false));
    }

    public function isInternal(): bool
    {
        return (bool) $this->type->is_internal;
    }

    public function isOpen(): bool
    {
        return ! $this->status->is_closed;
    }

    /**
     * Billing allowed by the status and the type (PRJ-BR-02, PRJ-BR-07); for 06.
     */
    public function allowsBilling(): bool
    {
        return $this->status->allows_billing && $this->type->is_billable && ! $this->type->is_internal;
    }

    /**
     * Costing allowed by the status (PRJ-BR-07); for 07 and 08.
     */
    public function allowsCosting(): bool
    {
        return (bool) $this->status->allows_costing;
    }

    /**
     * A signed or amended contract (PRJ-BR-04, PRJ-BR-06).
     */
    public function hasSignedContract(): bool
    {
        return $this->contract !== null && $this->contract->isSigned();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<BusinessLine, $this>
     */
    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    /**
     * @return BelongsTo<ProjectType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    /**
     * @return BelongsTo<ProjectStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'project_status_id');
    }

    /**
     * @return BelongsTo<ProjectPhase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'project_phase_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function landAreaUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'land_area_unit_id');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function sourceLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'source_lead_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'project_manager_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function supportOfficer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'support_officer_id');
    }

    /**
     * @return BelongsTo<HoldReason, $this>
     */
    public function holdReason(): BelongsTo
    {
        return $this->belongsTo(HoldReason::class);
    }

    /**
     * @return HasMany<ProjectService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(ProjectService::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasOne<ProjectContract, $this>
     */
    public function contract(): HasOne
    {
        return $this->hasOne(ProjectContract::class);
    }

    /**
     * @return HasMany<PaymentSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProjectEmployee, $this>
     */
    public function team(): HasMany
    {
        return $this->hasMany(ProjectEmployee::class);
    }

    /**
     * @return HasMany<ProjectEmployee, $this>
     */
    public function activeTeam(): HasMany
    {
        return $this->hasMany(ProjectEmployee::class)->where('is_active', true);
    }

    /**
     * @return HasMany<ProjectStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProjectStatusHistory::class)->latest('changed_at')->latest('id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<ProjectApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ProjectApproval::class);
    }

    /**
     * @return MorphMany<CrmActivity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject');
    }
}
