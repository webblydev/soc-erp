<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One assignment change of a lead (docs/03 §3.7, CRM-BR-06).
 *
 * @property int $id
 * @property int $lead_id
 * @property int|null $from_user_id
 * @property int|null $to_user_id
 * @property int|null $assigned_by
 * @property Carbon $assigned_at
 * @property string|null $reason
 * @property-read User|null $fromUser
 * @property-read User|null $toUser
 * @property-read User|null $assigner
 */
#[Fillable(['lead_id', 'from_user_id', 'to_user_id', 'assigned_by', 'assigned_at', 'reason'])]
class LeadAssignmentHistory extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
