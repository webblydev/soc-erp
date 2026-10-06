<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use Database\Factories\Catalog\WorkItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A BOQ / measurement item (docs/02 §3.6).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $work_item_category_id
 * @property int $unit_id
 * @property MeasurementFormula $measurement_formula
 * @property string|null $specification
 * @property string|null $standard_rate
 * @property bool $is_active
 * @property int|null $created_by
 * @property-read WorkItemCategory $category
 * @property-read Unit $unit
 */
#[Fillable(['code', 'name', 'work_item_category_id', 'unit_id', 'measurement_formula', 'standard_rate', 'specification', 'is_active'])]
#[UseFactory(WorkItemFactory::class)]
class WorkItem extends Model implements Collaborative
{
    /** @use HasFactory<WorkItemFactory> */
    use Auditable, HasFactory, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measurement_formula' => MeasurementFormula::class,
            'standard_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('catalog.work_items.view');
    }

    /**
     * @return BelongsTo<WorkItemCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WorkItemCategory::class, 'work_item_category_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
