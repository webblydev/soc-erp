<?php

namespace App\Modules\Estimation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Estimation\EstimateKindFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Estimate kind (docs/05 §3.1). Codes and flags are seeded and read-only (spec E23).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $has_work_lines
 * @property bool $has_material_lines
 * @property bool $is_customer_facing
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'has_work_lines', 'has_material_lines', 'is_customer_facing'])]
#[UseFactory(EstimateKindFactory::class)]
class EstimateKind extends Model
{
    /** @use HasFactory<EstimateKindFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const BOQ = 'BOQ';

    public const MATERIAL = 'MATERIAL';

    public const BLE = 'BLE';

    public const UP_SHEET = 'UP_SHEET';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['has_work_lines' => 'boolean', 'has_material_lines' => 'boolean', 'is_customer_facing' => 'boolean'];
    }
}
