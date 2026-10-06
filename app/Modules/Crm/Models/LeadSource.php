<?php

namespace App\Modules\Crm\Models;

use App\Modules\Crm\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Crm\LeadSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Where an enquiry came from (docs/03 §3.1). EXISTING is a system row used by "Add as new enquiry".
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $requires_referrer
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'requires_referrer'])]
#[UseFactory(LeadSourceFactory::class)]
class LeadSource extends Model
{
    /** @use HasFactory<LeadSourceFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const EXISTING = 'EXISTING';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requires_referrer' => 'boolean'];
    }
}
