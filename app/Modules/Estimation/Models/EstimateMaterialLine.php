<?php

namespace App\Modules\Estimation\Models;

use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\Unit;
use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A material quantity on an estimate (docs/05 §3.5): a catalog material or a free-text name (spec E8).
 *
 * @property int $id
 * @property int $estimate_id
 * @property int|null $origin_line_id
 * @property int|null $material_id
 * @property string|null $material_name
 * @property int $unit_id
 * @property string $estimated_qty
 * @property string|null $wastage_pct
 * @property string $total_qty
 * @property string|null $rate
 * @property string $amount
 * @property string|null $purpose
 * @property int $sort_order
 * @property-read Material|null $material
 * @property-read Unit $unit
 */
#[Fillable(['origin_line_id', 'material_id', 'material_name', 'unit_id', 'estimated_qty', 'wastage_pct', 'total_qty', 'rate', 'amount', 'purpose', 'sort_order'])]
class EstimateMaterialLine extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_qty' => 'decimal:4',
            'wastage_pct' => 'decimal:4',
            'total_qty' => 'decimal:4',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public function displayName(): string
    {
        return $this->material->name ?? (string) $this->material_name;
    }

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
}
