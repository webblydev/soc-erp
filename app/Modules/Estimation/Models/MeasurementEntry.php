<?php

namespace App\Modules\Estimation\Models;

use App\Models\User;
use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use App\Support\Collaboration\HasAttachments;
use Database\Factories\Estimation\MeasurementEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A Measurement Book entry (docs/05 §3.7). Work order and running bill ids wait for 06 / 07 (spec E2).
 *
 * @property int $id
 * @property string $mb_number
 * @property int $project_id
 * @property int $mb_direction_id
 * @property int|null $work_order_id
 * @property int|null $work_order_item_id
 * @property int|null $estimate_line_id
 * @property string|null $mb_book_no
 * @property string|null $mb_page_no
 * @property Carbon $measured_on
 * @property int $measured_by
 * @property int|null $work_item_id
 * @property string $description
 * @property string|null $location
 * @property MeasurementFormula $measurement_formula
 * @property string|null $nos
 * @property string|null $length
 * @property string|null $width
 * @property string|null $height
 * @property int $unit_id
 * @property string $quantity
 * @property string $rate
 * @property string $amount
 * @property string|null $achievement_pct
 * @property int $mb_status_id
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $rejection_reason
 * @property int|null $running_bill_line_id
 * @property string|null $remarks
 * @property int|null $created_by
 * @property-read Project $project
 * @property-read MbDirection $direction
 * @property-read MbStatus $status
 * @property-read EstimateLine|null $estimateLine
 * @property-read WorkItem|null $workItem
 * @property-read Unit $unit
 * @property-read Employee $measurer
 * @property-read User|null $verifier
 */
#[Fillable(['estimate_line_id', 'mb_book_no', 'mb_page_no', 'measured_on', 'measured_by', 'work_item_id', 'description', 'location', 'measurement_formula', 'nos', 'length', 'width', 'height', 'unit_id', 'quantity', 'rate', 'amount', 'achievement_pct', 'remarks'])]
#[UseFactory(MeasurementEntryFactory::class)]
class MeasurementEntry extends Model implements Collaborative
{
    /** @use HasFactory<MeasurementEntryFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            'measurement_formula' => MeasurementFormula::class,
            'nos' => 'decimal:4',
            'length' => 'decimal:4',
            'width' => 'decimal:4',
            'height' => 'decimal:4',
            'quantity' => 'decimal:4',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'achievement_pct' => 'decimal:4',
            'verified_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'mb_number';
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('view', $this);
    }

    /**
     * Entries on projects the user may see (spec E4).
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
    public function scopeWithStatus(Builder $query, string $code): void
    {
        $query->where($this->qualifyColumn('mb_status_id'), MbStatus::idFor($code));
    }

    public function hasStatus(string $code): bool
    {
        return $this->status->code === $code;
    }

    /**
     * Selectable into a running bill (ES-BR-09).
     */
    public function isBillable(): bool
    {
        return (bool) $this->status->is_billable && $this->running_bill_line_id === null;
    }

    public function isLocked(): bool
    {
        return (bool) $this->status->is_locked || $this->running_bill_line_id !== null;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<MbDirection, $this>
     */
    public function direction(): BelongsTo
    {
        return $this->belongsTo(MbDirection::class, 'mb_direction_id');
    }

    /**
     * @return BelongsTo<MbStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(MbStatus::class, 'mb_status_id');
    }

    /**
     * @return BelongsTo<EstimateLine, $this>
     */
    public function estimateLine(): BelongsTo
    {
        return $this->belongsTo(EstimateLine::class);
    }

    /**
     * @return BelongsTo<WorkItem, $this>
     */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function measurer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'measured_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
