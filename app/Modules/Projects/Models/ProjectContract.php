<?php

namespace App\Modules\Projects\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\Projects\ProjectContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The project's contract / deed (docs/04 §3.4). Status and deed amount are written by the
 * contract Actions only (spec P10, P11).
 *
 * @property int $id
 * @property int $project_id
 * @property string|null $contract_number
 * @property Carbon $agreement_date
 * @property string $deed_amount
 * @property bool $vat_inclusive
 * @property string|null $advance_pct
 * @property string|null $retention_pct
 * @property int|null $defect_liability_months
 * @property string|null $signed_by_customer
 * @property int|null $signed_by_company_user_id
 * @property Carbon|null $signed_at
 * @property int $contract_status_id
 * @property string|null $terms
 * @property Carbon|null $terminated_at
 * @property string|null $termination_reason
 * @property-read ContractStatus $status
 * @property-read Project $project
 */
#[Fillable(['contract_number', 'agreement_date', 'vat_inclusive', 'advance_pct', 'retention_pct', 'defect_liability_months', 'signed_by_customer', 'signed_by_company_user_id', 'terms'])]
#[UseFactory(ProjectContractFactory::class)]
class ProjectContract extends Model
{
    /** @use HasFactory<ProjectContractFactory> */
    use Auditable, HasFactory, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agreement_date' => 'date',
            'deed_amount' => 'decimal:2',
            'vat_inclusive' => 'boolean',
            'advance_pct' => 'decimal:4',
            'retention_pct' => 'decimal:4',
            'defect_liability_months' => 'integer',
            'signed_at' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status->code === ContractStatus::DRAFT;
    }

    /**
     * SIGNED or AMENDED: services and deed amount are locked (PRJ-BR-04).
     */
    public function isSigned(): bool
    {
        return in_array($this->status->code, [ContractStatus::SIGNED, ContractStatus::AMENDED], true);
    }

    public function isTerminated(): bool
    {
        return $this->status->code === ContractStatus::TERMINATED;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ContractStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ContractStatus::class, 'contract_status_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_company_user_id');
    }

    /**
     * @return HasMany<ProjectContractAmendment, $this>
     */
    public function amendments(): HasMany
    {
        return $this->hasMany(ProjectContractAmendment::class)->orderBy('amendment_no');
    }
}
