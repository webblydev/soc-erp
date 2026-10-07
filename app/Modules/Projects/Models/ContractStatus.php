<?php

namespace App\Modules\Projects\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\HasCodeLookup;
use App\Support\Lookups\IsLookup;
use Database\Factories\Projects\ContractStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract status (docs/04 §3.4). Seeded and fixed (spec P23).
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
#[UseFactory(ContractStatusFactory::class)]
class ContractStatus extends Model
{
    /** @use HasFactory<ContractStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const DRAFT = 'DRAFT';

    public const SIGNED = 'SIGNED';

    public const AMENDED = 'AMENDED';

    public const TERMINATED = 'TERMINATED';
}
