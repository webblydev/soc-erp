<?php

namespace App\Modules\Projects\Models;

use App\Modules\Projects\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\ProjectServiceStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Delivery status of a project service line (docs/04 §3.3). Seeded and fixed (spec P23).
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
#[UseFactory(ProjectServiceStatusFactory::class)]
class ProjectServiceStatus extends Model
{
    /** @use HasFactory<ProjectServiceStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const NOT_STARTED = 'NOT_STARTED';

    public const IN_PROGRESS = 'IN_PROGRESS';

    public const DELIVERED = 'DELIVERED';

    public const CANCELLED = 'CANCELLED';
}
