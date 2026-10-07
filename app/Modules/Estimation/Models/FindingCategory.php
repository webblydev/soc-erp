<?php

namespace App\Modules\Estimation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Estimation\FindingCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Finding category, the v1 "Regarding" list (docs/05 §3.1).
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
#[UseFactory(FindingCategoryFactory::class)]
class FindingCategory extends Model
{
    /** @use HasFactory<FindingCategoryFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const QUALITY = 'QUALITY';

    public const SAFETY = 'SAFETY';

    public const PROGRESS = 'PROGRESS';

    public const MATERIAL = 'MATERIAL';

    public const DESIGN_DEVIATION = 'DESIGN_DEVIATION';

    public const WORKMANSHIP = 'WORKMANSHIP';

    public const OTHER = 'OTHER';
}
