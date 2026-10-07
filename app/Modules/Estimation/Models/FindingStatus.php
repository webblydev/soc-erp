<?php

namespace App\Modules\Estimation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Estimation\FindingStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Finding status (docs/05 §6.3). Seeded and read-only (spec E23).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $is_closed
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'is_closed'])]
#[UseFactory(FindingStatusFactory::class)]
class FindingStatus extends Model
{
    /** @use HasFactory<FindingStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const OPEN = 'OPEN';

    public const IN_PROGRESS = 'IN_PROGRESS';

    public const RESOLVED = 'RESOLVED';

    public const ACCEPTED = 'ACCEPTED';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_closed' => 'boolean'];
    }
}
