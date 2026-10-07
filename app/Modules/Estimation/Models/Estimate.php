<?php

namespace App\Modules\Estimation\Models;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use App\Support\Collaboration\HasAttachments;
use App\Support\Collaboration\HasNotes;
use Database\Factories\Estimation\EstimateFactory;
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
 * A work (BOQ) or material estimate and one of its revisions (docs/05 §3.2). Revision 0 is the root;
 * later revisions point at it through root_estimate_id (spec E5).
 *
 * @property int $id
 * @property string $estimate_number
 * @property int $estimate_kind_id
 * @property int $project_id
 * @property string $title
 * @property string|null $site_address
 * @property Carbon $estimate_date
 * @property int $revision_no
 * @property int|null $root_estimate_id
 * @property int|null $revised_from_id
 * @property string|null $revision_purpose
 * @property int $estimate_status_id
 * @property int $prepared_by
 * @property int|null $checked_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string $subtotal
 * @property string|null $overhead_pct
 * @property string $overhead_amount
 * @property string|null $profit_pct
 * @property string $profit_amount
 * @property string|null $vat_pct
 * @property string $vat_amount
 * @property string $total_amount
 * @property bool $is_customer_facing
 * @property string|null $notes
 * @property string|null $rejection_note
 * @property string|null $legacy_ref
 * @property int|null $created_by
 * @property-read EstimateKind $kind
 * @property-read EstimateStatus $status
 * @property-read Project $project
 * @property-read Estimate|null $root
 * @property-read Estimate|null $previousRevision
 * @property-read Employee $preparer
 * @property-read Employee|null $checker
 * @property-read User|null $approver
 */
#[Fillable(['title', 'site_address', 'estimate_date', 'prepared_by', 'checked_by', 'overhead_pct', 'profit_pct', 'vat_pct', 'notes'])]
#[UseFactory(EstimateFactory::class)]
class Estimate extends Model implements Collaborative
{
    /** @use HasFactory<EstimateFactory> */
    use Auditable, HasAttachments, HasFactory, HasNotes, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimate_date' => 'date',
            'revision_no' => 'integer',
            'approved_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'overhead_pct' => 'decimal:4',
            'overhead_amount' => 'decimal:2',
            'profit_pct' => 'decimal:4',
            'profit_amount' => 'decimal:2',
            'vat_pct' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'is_customer_facing' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'estimate_number';
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('view', $this);
    }

    /**
     * Estimates on projects the user may see (spec E4).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn($this->qualifyColumn('project_id'), Project::query()->visibleTo($user)->select('projects.id'));
    }

    /**
     * Only the newest revision of each root.
     *
     * @param  Builder<static>  $query
     */
    public function scopeLatestRevisions(Builder $query): void
    {
        $query->whereNotExists(function ($later): void {
            $later->from('estimates as later')
                ->whereNull('later.deleted_at')
                ->whereRaw('coalesce(later.root_estimate_id, later.id) = coalesce(estimates.root_estimate_id, estimates.id)')
                ->whereColumn('later.revision_no', '>', 'estimates.revision_no');
        });
    }

    /**
     * Every revision sharing this estimate's root, this one included.
     *
     * @param  Builder<static>  $query
     */
    public function scopeFamilyOf(Builder $query, Estimate $estimate): void
    {
        $rootId = $estimate->rootId();

        $query->where(fn (Builder $family) => $family->whereKey($rootId)->orWhere('root_estimate_id', $rootId));
    }

    public function rootId(): int
    {
        return $this->root_estimate_id ?? $this->id;
    }

    public function isLocked(): bool
    {
        return (bool) $this->status->is_locked;
    }

    public function hasStatus(string $code): bool
    {
        return $this->status->code === $code;
    }

    public function isLatestRevision(): bool
    {
        return ! static::query()->familyOf($this)->where('revision_no', '>', $this->revision_no)->exists();
    }

    public function hasWorkLines(): bool
    {
        return (bool) $this->kind->has_work_lines;
    }

    public function hasMaterialLines(): bool
    {
        return (bool) $this->kind->has_material_lines;
    }

    /**
     * Material lines add to the total only on kinds without work lines (spec E7).
     */
    public function totalsFromMaterials(): bool
    {
        return ! $this->hasWorkLines();
    }

    /**
     * @return BelongsTo<EstimateKind, $this>
     */
    public function kind(): BelongsTo
    {
        return $this->belongsTo(EstimateKind::class, 'estimate_kind_id');
    }

    /**
     * @return BelongsTo<EstimateStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(EstimateStatus::class, 'estimate_status_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function root(): BelongsTo
    {
        return $this->belongsTo(Estimate::class, 'root_estimate_id');
    }

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function previousRevision(): BelongsTo
    {
        return $this->belongsTo(Estimate::class, 'revised_from_id');
    }

    /**
     * @return HasMany<Estimate, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(Estimate::class, 'root_estimate_id')->orderBy('revision_no');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'checked_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<EstimateSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(EstimateSection::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<EstimateLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(EstimateLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<EstimateMaterialLine, $this>
     */
    public function materialLines(): HasMany
    {
        return $this->hasMany(EstimateMaterialLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<EstimateStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(EstimateStatusHistory::class)->latest('changed_at')->latest('id');
    }
}
