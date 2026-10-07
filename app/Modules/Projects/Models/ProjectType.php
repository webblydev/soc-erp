<?php

namespace App\Modules\Projects\Models;

use App\Modules\Projects\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\ProjectTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of project (docs/04 §3.1). INTERNAL types have no customer and are not billable (PRJ-BR-02).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $is_internal
 * @property bool $is_billable
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'is_internal', 'is_billable'])]
#[UseFactory(ProjectTypeFactory::class)]
class ProjectType extends Model
{
    /** @use HasFactory<ProjectTypeFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const RESIDENTIAL = 'RESIDENTIAL';

    public const INTERNAL = 'INTERNAL';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'is_billable' => 'boolean'];
    }
}
