<?php

namespace App\Modules\Projects\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A contract amendment with its proposed service lines (spec P11). Approval applies the lines.
 *
 * @property int $id
 * @property int $project_contract_id
 * @property int $amendment_no
 * @property Carbon $amendment_date
 * @property string $reason
 * @property string $status
 * @property string|null $value_change
 * @property string|null $new_deed_amount
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $created_by
 * @property-read ProjectContract $contract
 * @property-read User|null $approver
 */
#[Fillable(['amendment_date', 'reason'])]
class ProjectContractAmendment extends Model
{
    use Auditable, SoftDeletes, TracksAuthors;

    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amendment_date' => 'date',
            'value_change' => 'decimal:2',
            'new_deed_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    /**
     * @return BelongsTo<ProjectContract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(ProjectContract::class, 'project_contract_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<ProjectContractAmendmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ProjectContractAmendmentLine::class)->orderBy('sort_order')->orderBy('id');
    }
}
