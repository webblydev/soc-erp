<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Foundation\Models\Location;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use App\Support\Collaboration\HasAttachments;
use App\Support\Collaboration\HasNotes;
use App\Support\DataScope\HasDataScope;
use Database\Factories\Crm\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A person or organisation we work for (docs/03 §3.9). Finance fields (payment term, credit
 * limit, TIN, BIN) and attribution are not fillable: their Actions set them (spec R15, CRM-BR-16).
 *
 * @property int $id
 * @property string $customer_number
 * @property int $customer_type_id
 * @property string $name
 * @property string|null $company_name
 * @property string $phone
 * @property string|null $alternate_phone
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $address
 * @property int|null $location_id
 * @property string|null $nid_or_reg_no
 * @property string|null $tin
 * @property string|null $bin
 * @property int|null $business_line_id
 * @property int|null $account_manager_user_id
 * @property int|null $source_lead_id
 * @property int|null $lead_source_id
 * @property int|null $acquired_by_user_id
 * @property int|null $payment_term_id
 * @property string|null $credit_limit
 * @property int|null $receivable_account_id
 * @property int $customer_status_id
 * @property bool $is_also_vendor
 * @property int|null $merged_into_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property-read CustomerType $type
 * @property-read CustomerStatus $status
 * @property-read User|null $accountManager
 */
#[Fillable([
    'customer_type_id', 'name', 'company_name', 'phone', 'alternate_phone', 'whatsapp', 'email', 'address', 'location_id',
    'nid_or_reg_no', 'business_line_id', 'account_manager_user_id', 'customer_status_id', 'is_also_vendor', 'notes',
])]
#[UseFactory(CustomerFactory::class)]
class Customer extends Model implements Collaborative
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, HasAttachments, HasDataScope, HasFactory, HasNotes, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'is_also_vendor' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'customer_number';
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('view', $this);
    }

    public function dataScopeOwnerColumns(): array
    {
        return ['account_manager_user_id', 'acquired_by_user_id'];
    }

    public function dataScopeTeamUserIds(User $user): array
    {
        return SalesTeam::managedMemberIds($user);
    }

    public function isBlocked(): bool
    {
        return (bool) $this->status->is_blocked;
    }

    /**
     * Activities on the customer and on its converted and referred leads (spec R17).
     *
     * @return Builder<CrmActivity>
     */
    public function timeline(): Builder
    {
        $leadIds = Lead::query()->select('id')
            ->where('converted_customer_id', $this->id)
            ->orWhere(fn (Builder $query) => $query->where('referrer_type', 'customer')->where('referrer_id', $this->id));

        return CrmActivity::query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('subject_type', $this->getMorphClass())->where('subject_id', $this->id))
            ->orWhere(fn (Builder $query) => $query->where('subject_type', (new Lead)->getMorphClass())->whereIn('subject_id', $leadIds)));
    }

    /**
     * @return BelongsTo<CustomerType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class, 'customer_type_id');
    }

    /**
     * @return BelongsTo<CustomerStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(CustomerStatus::class, 'customer_status_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<BusinessLine, $this>
     */
    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acquiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acquired_by_user_id');
    }

    /**
     * @return BelongsTo<LeadSource, $this>
     */
    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    /**
     * @return BelongsTo<PaymentTerm, $this>
     */
    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function sourceLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'source_lead_id');
    }

    /**
     * @return HasMany<CustomerContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    /**
     * @return HasOne<CustomerContact, $this>
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(CustomerContact::class)->where('is_primary', true);
    }

    /**
     * @return MorphMany<CrmActivity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject');
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function convertedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'converted_customer_id');
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function referredLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'referrer_id')->where('referrer_type', 'customer');
    }
}
