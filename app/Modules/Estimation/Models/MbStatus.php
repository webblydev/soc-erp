<?php

namespace App\Modules\Estimation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Estimation\MbStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Measurement Book status (docs/05 §6.2). Seeded and read-only (spec E23).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $is_billable
 * @property bool $is_locked
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'is_billable', 'is_locked'])]
#[UseFactory(MbStatusFactory::class)]
class MbStatus extends Model
{
    /** @use HasFactory<MbStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const RECORDED = 'RECORDED';

    public const VERIFIED = 'VERIFIED';

    public const BILLED = 'BILLED';

    public const REJECTED = 'REJECTED';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_billable' => 'boolean', 'is_locked' => 'boolean'];
    }
}
