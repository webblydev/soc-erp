<?php

namespace App\Modules\Projects\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\ProjectPhaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Project phase (docs/04 §3.1). Sort order is the phase order used by PHASE schedule triggers (spec P12).
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
#[UseFactory(ProjectPhaseFactory::class)]
class ProjectPhase extends Model
{
    /** @use HasFactory<ProjectPhaseFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const DESIGN = 'DESIGN';

    public const APPROVAL = 'APPROVAL';

    public const PRE_CONSTRUCTION = 'PRE_CONSTRUCTION';

    public const CONSTRUCTION = 'CONSTRUCTION';

    public const FINISHING = 'FINISHING';

    public const HANDOVER = 'HANDOVER';

    public const DEFECT_LIABILITY = 'DEFECT_LIABILITY';
}
