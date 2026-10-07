<?php

namespace App\Modules\Estimation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Estimation\FindingSeverityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Finding severity (docs/05 §3.1). HIGH and CRITICAL need a responsible and a due date (ES-BR-11).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $requires_follow_up
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'requires_follow_up'])]
#[UseFactory(FindingSeverityFactory::class)]
class FindingSeverity extends Model
{
    /** @use HasFactory<FindingSeverityFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const LOW = 'LOW';

    public const MEDIUM = 'MEDIUM';

    public const HIGH = 'HIGH';

    public const CRITICAL = 'CRITICAL';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requires_follow_up' => 'boolean'];
    }
}
