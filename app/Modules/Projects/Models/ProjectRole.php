<?php

namespace App\Modules\Projects\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\ProjectRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A role on a project team (docs/04 §3.1).
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
#[UseFactory(ProjectRoleFactory::class)]
class ProjectRole extends Model
{
    /** @use HasFactory<ProjectRoleFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const PM = 'PM';

    public const SUPERVISOR = 'SUPERVISOR';

    public const SUPPORT_OFFICER = 'SUPPORT_OFFICER';
}
