<?php

namespace App\Modules\Estimation\Models;

use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Projects\Models\Project;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cost budget line of a project (docs/05 §3.6), built from an approved estimate or typed by hand (spec E11).
 *
 * @property int $id
 * @property int $project_id
 * @property int $cost_category_id
 * @property int|null $work_item_id
 * @property int|null $material_id
 * @property string|null $material_name
 * @property string|null $description
 * @property string|null $budget_qty
 * @property int|null $unit_id
 * @property string $budget_amount
 * @property int|null $source_estimate_id
 * @property int $sort_order
 * @property-read Project $project
 * @property-read CostCategory $costCategory
 * @property-read WorkItem|null $workItem
 * @property-read Material|null $material
 * @property-read Unit|null $unit
 * @property-read Estimate|null $sourceEstimate
 */
#[Fillable(['cost_category_id', 'work_item_id', 'material_id', 'material_name', 'description', 'budget_qty', 'unit_id', 'budget_amount', 'source_estimate_id', 'sort_order'])]
class ProjectBudgetLine extends Model
{
    use Auditable, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['budget_qty' => 'decimal:4', 'budget_amount' => 'decimal:2'];
    }

    public function isFromEstimate(): bool
    {
        return $this->source_estimate_id !== null;
    }

    public function displayName(): string
    {
        return $this->description ?? $this->material->name ?? $this->material_name ?? $this->workItem->name ?? $this->costCategory->name;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<CostCategory, $this>
     */
    public function costCategory(): BelongsTo
    {
        return $this->belongsTo(CostCategory::class);
    }

    /**
     * @return BelongsTo<WorkItem, $this>
     */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function sourceEstimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class, 'source_estimate_id');
    }
}
