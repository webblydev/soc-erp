<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One status change of a lead (docs/03 §3.6, CRM-BR-06).
 *
 * @property int $id
 * @property int $lead_id
 * @property int|null $from_status_id
 * @property int $to_status_id
 * @property int|null $changed_by
 * @property Carbon $changed_at
 * @property string|null $note
 * @property-read LeadStatus|null $fromStatus
 * @property-read LeadStatus $toStatus
 * @property-read User|null $changer
 */
#[Fillable(['lead_id', 'from_status_id', 'to_status_id', 'changed_by', 'changed_at', 'note'])]
class LeadStatusHistory extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<LeadStatus, $this>
     */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'from_status_id');
    }

    /**
     * @return BelongsTo<LeadStatus, $this>
     */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'to_status_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
