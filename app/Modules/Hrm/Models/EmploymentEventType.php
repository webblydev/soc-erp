<?php

namespace App\Modules\Hrm\Models;

use App\Modules\Hrm\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Hrm\EmploymentEventTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Kind of employment event (docs/09 §3.4). All seeded rows are system rows; RETIRED is added so every exit status has an event (spec H6).
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
#[UseFactory(EmploymentEventTypeFactory::class)]
class EmploymentEventType extends Model
{
    /** @use HasFactory<EmploymentEventTypeFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const JOINED = 'JOINED';

    public const CONFIRMED = 'CONFIRMED';

    public const PROMOTED = 'PROMOTED';

    public const TRANSFERRED = 'TRANSFERRED';

    public const SALARY_REVISED = 'SALARY_REVISED';

    public const DESIGNATION_CHANGED = 'DESIGNATION_CHANGED';

    public const RESIGNED = 'RESIGNED';

    public const TERMINATED = 'TERMINATED';

    public const RETIRED = 'RETIRED';

    public const REJOINED = 'REJOINED';

    /**
     * Types written only by their own Actions (create, exit, rejoin), not by RecordEmploymentEvent.
     */
    public const RESERVED = [self::JOINED, self::RESIGNED, self::TERMINATED, self::RETIRED, self::REJOINED];
}
