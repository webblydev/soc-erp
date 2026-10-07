<?php

namespace App\Modules\Projects\Models;

use App\Modules\Projects\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\ScheduleTriggerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What makes a payment milestone due (docs/04 §3.5). Seeded and fixed (spec P23).
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
#[UseFactory(ScheduleTriggerFactory::class)]
class ScheduleTrigger extends Model
{
    /** @use HasFactory<ScheduleTriggerFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const DATE = 'DATE';

    public const PHASE = 'PHASE';

    public const APPROVAL = 'APPROVAL';

    public const TASK = 'TASK';

    public const MANUAL = 'MANUAL';
}
