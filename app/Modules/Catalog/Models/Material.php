<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use Database\Factories\Catalog\MaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A construction material (docs/02 §3.8). expense_account_id is filled by Accounting (spec C1).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $material_category_id
 * @property int $unit_id
 * @property string|null $standard_rate
 * @property bool $is_active
 * @property int|null $created_by
 * @property-read MaterialCategory $category
 * @property-read Unit $unit
 */
#[Fillable(['code', 'name', 'material_category_id', 'unit_id', 'standard_rate', 'is_active'])]
#[UseFactory(MaterialFactory::class)]
class Material extends Model implements Collaborative
{
    /** @use HasFactory<MaterialFactory> */
    use Auditable, HasFactory, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
        return $user->can('catalog.materials.view');
    }

    /**
     * @return BelongsTo<MaterialCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
