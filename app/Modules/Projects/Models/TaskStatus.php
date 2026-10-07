<?php

namespace App\Modules\Projects\Models;

use App\Modules\Projects\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\TaskStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Task status (docs/04 §6.2). Flags are set by the seeder on system rows only (spec P23).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $is_done
 * @property bool $is_cancelled
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'is_done', 'is_cancelled'])]
#[UseFactory(TaskStatusFactory::class)]
class TaskStatus extends Model
{
    /** @use HasFactory<TaskStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const TODO = 'TODO';

    public const IN_PROGRESS = 'IN_PROGRESS';

    public const REVIEW = 'REVIEW';

    public const BLOCKED = 'BLOCKED';

    public const DONE = 'DONE';

    public const CANCELLED = 'CANCELLED';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'is_cancelled' => 'boolean'];
    }
}
