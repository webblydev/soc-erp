<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system'])]
class LocationLevel extends Model
{
    use Auditable, IsLookup;

    public const DIVISION = 'division';

    public const DISTRICT = 'district';

    public const THANA = 'thana';

    public const AREA = 'area';

    /** Top-down order of the location hierarchy (docs/01 §3.7). */
    public const ORDER = [self::DIVISION, self::DISTRICT, self::THANA, self::AREA];
}
