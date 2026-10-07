<?php

namespace App\Modules\Projects\Models;

use App\Modules\Projects\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\ProjectStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Project life-cycle status (docs/04 §6.1). Flags are set by the seeder on system rows only (spec P23); behaviour follows the code.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $is_open
 * @property bool $is_closed
 * @property bool $allows_billing
 * @property bool $allows_costing
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'is_open', 'is_closed', 'allows_billing', 'allows_costing'])]
#[UseFactory(ProjectStatusFactory::class)]
class ProjectStatus extends Model
{
    /** @use HasFactory<ProjectStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const ENQUIRY = 'ENQUIRY';

    public const CONTRACTED = 'CONTRACTED';

    public const IN_PROGRESS = 'IN_PROGRESS';

    public const ON_HOLD = 'ON_HOLD';

    public const HANDED_OVER = 'HANDED_OVER';

    public const COMPLETED = 'COMPLETED';

    public const CANCELLED = 'CANCELLED';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'is_closed' => 'boolean', 'allows_billing' => 'boolean', 'allows_costing' => 'boolean'];
    }
}
