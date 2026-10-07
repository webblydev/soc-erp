<?php

namespace App\Modules\Projects\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\ApprovalStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Approval status (docs/04 §3.1). Flags are set by the seeder on system rows only (spec P23).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $is_final
 * @property bool $is_success
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'is_final', 'is_success'])]
#[UseFactory(ApprovalStatusFactory::class)]
class ApprovalStatus extends Model
{
    /** @use HasFactory<ApprovalStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const PREPARING = 'PREPARING';

    public const SUBMITTED = 'SUBMITTED';

    public const QUERY = 'QUERY';

    public const RESUBMITTED = 'RESUBMITTED';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const WITHDRAWN = 'WITHDRAWN';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_final' => 'boolean', 'is_success' => 'boolean'];
    }
}
