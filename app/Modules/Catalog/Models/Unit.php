<?php

namespace App\Modules\Catalog\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Catalog\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A unit of measure (docs/02 §3.4).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $unit_kind_id
 * @property bool $is_active
 * @property bool $is_system
 * @property-read UnitKind $kind
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'symbol', 'unit_kind_id'])]
#[UseFactory(UnitFactory::class)]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use Auditable, HasFactory, IsLookup;

    /**
     * @return BelongsTo<UnitKind, $this>
     */
    public function kind(): BelongsTo
    {
        return $this->belongsTo(UnitKind::class, 'unit_kind_id');
    }
}
