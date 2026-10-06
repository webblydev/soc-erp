<?php

namespace App\Modules\Crm\Models;

use App\Modules\Crm\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Crm\LeadPriorityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * How urgent a lead is (docs/03 §3.1). NORMAL is the default system row.
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
#[UseFactory(LeadPriorityFactory::class)]
class LeadPriority extends Model
{
    /** @use HasFactory<LeadPriorityFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const NORMAL = 'NORMAL';
}
