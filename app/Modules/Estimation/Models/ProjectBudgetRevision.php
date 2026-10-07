<?php

namespace App\Modules\Estimation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A logged change of a project's budget total (docs/05 §3.6, ES-BR-05).
 *
 * @property int $id
 * @property int $project_id
 * @property int $revision_no
 * @property string|null $reason
 * @property string $old_total
 * @property string $new_total
 * @property int|null $approved_by
 * @property Carbon $approved_at
 * @property-read User|null $approver
 */
#[Fillable(['revision_no', 'reason', 'old_total', 'new_total', 'approved_by', 'approved_at'])]
class ProjectBudgetRevision extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['old_total' => 'decimal:2', 'new_total' => 'decimal:2', 'approved_at' => 'datetime', 'revision_no' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
