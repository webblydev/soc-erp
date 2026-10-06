<?php

namespace App\Modules\Crm\Models;

use App\Modules\Crm\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Crm\ActivityTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of CRM activity (docs/03 §3.1) with its lucide icon and duration / contact rules.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property string|null $icon
 * @property bool $requires_duration
 * @property bool $counts_as_contact
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'icon', 'requires_duration', 'counts_as_contact'])]
#[UseFactory(ActivityTypeFactory::class)]
class ActivityType extends Model
{
    /** @use HasFactory<ActivityTypeFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requires_duration' => 'boolean', 'counts_as_contact' => 'boolean'];
    }
}
