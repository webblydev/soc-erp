<?php

namespace App\Modules\Estimation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One status change of an estimate (spec E9).
 *
 * @property int $id
 * @property int $estimate_id
 * @property int $estimate_status_id
 * @property string|null $note
 * @property int|null $changed_by
 * @property Carbon $changed_at
 * @property-read EstimateStatus $status
 * @property-read User|null $changer
 */
#[Fillable(['estimate_status_id', 'note', 'changed_by', 'changed_at'])]
class EstimateStatusHistory extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<EstimateStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(EstimateStatus::class, 'estimate_status_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
