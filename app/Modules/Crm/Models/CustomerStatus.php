<?php

namespace App\Modules\Crm\Models;

use App\Modules\Crm\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Crm\CustomerStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer standing (docs/03 §3.1). ACTIVE, INACTIVE and BLOCKED are system rows; is_blocked is set by the seeder only (spec R4).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_blocked
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'is_blocked'])]
#[UseFactory(CustomerStatusFactory::class)]
class CustomerStatus extends Model
{
    /** @use HasFactory<CustomerStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const ACTIVE = 'ACTIVE';

    public const INACTIVE = 'INACTIVE';

    public const BLOCKED = 'BLOCKED';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_blocked' => 'boolean'];
    }
}
