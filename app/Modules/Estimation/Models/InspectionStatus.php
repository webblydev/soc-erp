<?php

namespace App\Modules\Estimation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Estimation\InspectionStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Site inspection status (docs/05 §6.3). Seeded and read-only (spec E23).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system'])]
#[UseFactory(InspectionStatusFactory::class)]
class InspectionStatus extends Model
{
    /** @use HasFactory<InspectionStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const DRAFT = 'DRAFT';

    public const SUBMITTED = 'SUBMITTED';

    public const CLOSED = 'CLOSED';
}
