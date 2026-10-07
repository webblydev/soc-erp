<?php

namespace App\Modules\Projects\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\ScheduleStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Payment milestone status (docs/04 §3.5). Seeded and fixed (spec P23).
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
#[UseFactory(ScheduleStatusFactory::class)]
class ScheduleStatus extends Model
{
    /** @use HasFactory<ScheduleStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const PENDING = 'PENDING';

    public const DUE = 'DUE';

    public const INVOICED = 'INVOICED';

    public const PAID = 'PAID';

    public const CANCELLED = 'CANCELLED';
}
