<?php

namespace App\Modules\Estimation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Estimation\InspectionTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Site inspection type (docs/05 §3.1).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $allowed_after_completion
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'allowed_after_completion'])]
#[UseFactory(InspectionTypeFactory::class)]
class InspectionType extends Model
{
    /** @use HasFactory<InspectionTypeFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const WEEKLY = 'WEEKLY';

    public const EVENT = 'EVENT';

    public const CASTING = 'CASTING';

    public const MATERIAL = 'MATERIAL';

    public const HANDOVER = 'HANDOVER';

    public const SNAG = 'SNAG';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['allowed_after_completion' => 'boolean'];
    }
}
