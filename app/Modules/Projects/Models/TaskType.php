<?php

namespace App\Modules\Projects\Models;

use App\Modules\Projects\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\TaskTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of task (docs/04 §3.1) with default estimated hours.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property int|null $default_estimated_hours
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'default_estimated_hours'])]
#[UseFactory(TaskTypeFactory::class)]
class TaskType extends Model
{
    /** @use HasFactory<TaskTypeFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const OTHER = 'OTHER';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['default_estimated_hours' => 'integer'];
    }
}
