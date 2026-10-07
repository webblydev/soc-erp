<?php

namespace App\Modules\Projects\Models;

use App\Modules\Projects\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\ApprovalTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of approval (docs/04 §3.1) with its typical duration and default checklist, one item per line (spec P18).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property int|null $typical_days
 * @property string|null $default_checklist
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'typical_days', 'default_checklist'])]
#[UseFactory(ApprovalTypeFactory::class)]
class ApprovalType extends Model
{
    /** @use HasFactory<ApprovalTypeFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const LUC = 'LUC';

    public const BP = 'BP';

    public const OC = 'OC';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['typical_days' => 'integer'];
    }
}
