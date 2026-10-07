<?php

namespace App\Modules\Estimation\Models;

use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Support\AuditTrail\Auditable;
use Database\Factories\Estimation\EstimateLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A measurement sheet line (docs/05 §3.4). origin_line_id follows the BOQ item across revisions (spec E6).
 *
 * @property int $id
 * @property int $estimate_id
 * @property int|null $estimate_section_id
 * @property int|null $origin_line_id
 * @property string $line_no
 * @property int|null $work_item_id
 * @property string $description
 * @property string|null $level
 * @property string|null $location
 * @property MeasurementFormula $measurement_formula
 * @property string $nos
 * @property string|null $length
 * @property string|null $width
 * @property string|null $height
 * @property bool $deduction
 * @property int $unit_id
 * @property string $quantity
 * @property bool $quantity_is_manual
 * @property string|null $rate
 * @property string $amount
 * @property int|null $cost_category_id
 * @property string|null $remarks
 * @property int $sort_order
 * @property-read Estimate $estimate
 * @property-read EstimateSection|null $section
 * @property-read WorkItem|null $workItem
 * @property-read Unit $unit
 * @property-read CostCategory|null $costCategory
 */
#[Fillable(['estimate_section_id', 'origin_line_id', 'line_no', 'work_item_id', 'description', 'level', 'location', 'measurement_formula', 'nos', 'length', 'width', 'height', 'deduction', 'unit_id', 'quantity', 'quantity_is_manual', 'rate', 'amount', 'cost_category_id', 'remarks', 'sort_order'])]
#[UseFactory(EstimateLineFactory::class)]
class EstimateLine extends Model
{
    /** @use HasFactory<EstimateLineFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measurement_formula' => MeasurementFormula::class,
            'nos' => 'decimal:4',
            'length' => 'decimal:4',
            'width' => 'decimal:4',
            'height' => 'decimal:4',
            'deduction' => 'boolean',
            'quantity' => 'decimal:4',
            'quantity_is_manual' => 'boolean',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * The first line of this BOQ item; lines copied by a revision share it.
     */
    public function originId(): int
    {
        return $this->origin_line_id ?? $this->id;
    }

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    /**
     * @return BelongsTo<EstimateSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(EstimateSection::class, 'estimate_section_id');
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
     * @return BelongsTo<CostCategory, $this>
     */
    public function costCategory(): BelongsTo
    {
        return $this->belongsTo(CostCategory::class);
    }

    /**
     * @return HasMany<MeasurementEntry, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(MeasurementEntry::class);
    }
}
