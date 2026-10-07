<?php

namespace App\Modules\Estimation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Estimation\CostCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cost category for budgets (docs/05 §3.1). The GL account link waits for 08 (spec E2).
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
#[UseFactory(CostCategoryFactory::class)]
class CostCategory extends Model
{
    /** @use HasFactory<CostCategoryFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const MATERIAL = 'MATERIAL';

    public const LABOUR = 'LABOUR';

    public const SUBCONTRACT = 'SUBCONTRACT';

    public const EQUIPMENT = 'EQUIPMENT';

    public const TRANSPORT = 'TRANSPORT';

    public const APPROVAL_FEE = 'APPROVAL_FEE';

    public const CONSULTANT = 'CONSULTANT';

    public const OVERHEAD = 'OVERHEAD';

    public const OTHER = 'OTHER';
}
