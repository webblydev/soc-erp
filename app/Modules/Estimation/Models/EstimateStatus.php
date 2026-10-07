<?php

namespace App\Modules\Estimation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Estimation\EstimateStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Estimate status (docs/05 §6.1). Seeded and read-only (spec E23).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $is_locked
 * @property bool $is_approved
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'is_locked', 'is_approved'])]
#[UseFactory(EstimateStatusFactory::class)]
class EstimateStatus extends Model
{
    /** @use HasFactory<EstimateStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const DRAFT = 'DRAFT';

    public const SUBMITTED = 'SUBMITTED';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const SUPERSEDED = 'SUPERSEDED';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_locked' => 'boolean', 'is_approved' => 'boolean'];
    }
}
