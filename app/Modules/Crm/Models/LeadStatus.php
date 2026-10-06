<?php

namespace App\Modules\Crm\Models;

use App\Modules\Crm\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Crm\LeadStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A pipeline stage (docs/03 §3.1). WON, LOST and NEW are system rows; flags are set by the seeder
 * only, so statuses added in Master Data are open statuses (spec R4).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property int $probability_pct
 * @property bool $is_won
 * @property bool $is_lost
 * @property bool $is_closed
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'probability_pct', 'is_won', 'is_lost', 'is_closed'])]
#[UseFactory(LeadStatusFactory::class)]
class LeadStatus extends Model
{
    /** @use HasFactory<LeadStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const NEW = 'NEW';

    public const WON = 'WON';

    public const LOST = 'LOST';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'probability_pct' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('is_closed', false);
    }
}
