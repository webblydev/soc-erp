<?php

namespace App\Modules\Projects\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\TaskPriorityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Task priority (docs/04 §3.1).
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
#[UseFactory(TaskPriorityFactory::class)]
class TaskPriority extends Model
{
    /** @use HasFactory<TaskPriorityFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const LOW = 'LOW';

    public const NORMAL = 'NORMAL';

    public const HIGH = 'HIGH';

    public const CRITICAL = 'CRITICAL';
}
