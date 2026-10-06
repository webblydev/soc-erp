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
use Database\Factories\Crm\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An enquiry moving through the pipeline (docs/03 §3.4). Status, assignment and conversion
 * columns are written only by their Actions; the denormalised follow-up columns by LeadFollowUps.
 *
 * @property int $id
 * @property string $lead_number
 * @property Carbon $lead_date
 * @property string $name
 * @property string|null $company_name
 * @property string $phone
 * @property string|null $office_phone
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $address
 * @property int|null $location_id
 * @property int $lead_source_id
 * @property string|null $referrer_type
 * @property int|null $referrer_id
 * @property string|null $referrer_name
 * @property int|null $business_line_id
 * @property int|null $lead_level_id
 * @property int $lead_status_id
 * @property int $lead_priority_id
 * @property int|null $sales_team_id
 * @property int|null $assigned_to
 * @property Carbon|null $assigned_at
 * @property string|null $expected_value
 * @property Carbon|null $expected_close_date
 * @property string|null $site_location_text
 * @property string|null $land_area
 * @property int|null $floors_planned
 * @property Carbon|null $next_follow_up_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $stale_notified_at
 * @property int|null $lost_reason_id
 * @property string|null $lost_note
 * @property Carbon|null $won_at
 * @property Carbon|null $lost_at
 * @property int|null $converted_customer_id
 * @property int|null $converted_project_id
 * @property Carbon|null $converted_at
 * @property int|null $converted_by
 * @property string|null $notes
 * @property Carbon $created_at
 * @property-read LeadStatus $status
 * @property-read LeadSource $source
 * @property-read LeadPriority $priority
 * @property-read User|null $assignee
 * @property-read SalesTeam|null $team
 * @property-read Customer|null $convertedCustomer
 */
#[Fillable([
    'lead_date', 'name', 'company_name', 'phone', 'office_phone', 'whatsapp', 'email', 'address', 'location_id',
    'lead_source_id', 'referrer_type', 'referrer_id', 'referrer_name', 'business_line_id', 'lead_level_id',
    'lead_priority_id', 'expected_value', 'expected_close_date', 'site_location_text', 'land_area', 'floors_planned', 'notes',
])]
#[UseFactory(LeadFactory::class)]
class Lead extends Model implements Collaborative
{
    /** @use HasFactory<LeadFactory> */
    use Auditable, HasAttachments, HasDataScope, HasFactory, HasNotes, SoftDeletes, TracksAuthors;

    public const REFERRER_TYPES = ['customer', 'employee', 'agent', 'other'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_date' => 'date',
            'expected_value' => 'decimal:2',
            'expected_close_date' => 'date',
            'floors_planned' => 'integer',
            'assigned_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'stale_notified_at' => 'datetime',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'lead_number';
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('view', $this);
    }

    public function dataScopeOwnerColumns(): array
    {
        return ['assigned_to'];
    }

    public function dataScopeTeamUserIds(User $user): array
    {
        return SalesTeam::managedMemberIds($user);
    }

    /**
     * A manager also sees leads filed under a team they manage, assigned or not (spec R6).
     *
     * @param  Builder<static>  $query
     */
    protected function dataScopeTeamExtra(Builder $query, User $user): void
    {
        $query->orWhereIn($this->qualifyColumn('sales_team_id'), SalesTeam::managedTeamIds($user));
    }

    public function isConverted(): bool
    {
        return $this->converted_customer_id !== null;
    }

    public function isOpen(): bool
    {
        return ! $this->status->is_closed;
    }

    /**
     * Who referred the lead: a linked customer or user, or the typed name.
     */
    public function referrerLabel(): ?string
    {
        return match ($this->referrer_type) {
            'customer' => $this->referrerCustomer->name ?? $this->referrer_name,
            'employee' => $this->referrerUser->name ?? $this->referrer_name,
            default => $this->referrer_name,
        };
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('lead_status_id'), LeadStatus::query()->select('id')->where('is_closed', false));
    }

    /**
     * Open leads with no activity (or, without any, no creation) for the given days (CRM-BR-11).
     *
     * @param  Builder<static>  $query
     */
    public function scopeStale(Builder $query, int $days): void
    {
        $cutoff = now()->subDays($days);

        $query->open()->where(fn (Builder $query) => $query
            ->where($this->qualifyColumn('last_activity_at'), '<', $cutoff)
            ->orWhere(fn (Builder $query) => $query->whereNull($this->qualifyColumn('last_activity_at'))->where($this->qualifyColumn('created_at'), '<', $cutoff)));
    }

    /**
     * @return BelongsTo<LeadStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'lead_status_id');
    }

    /**
     * @return BelongsTo<LeadSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    /**
     * @return BelongsTo<LeadPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(LeadPriority::class, 'lead_priority_id');
    }

    /**
     * @return BelongsTo<LeadLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(LeadLevel::class, 'lead_level_id');
    }

    /**
     * @return BelongsTo<BusinessLine, $this>
     */
    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<SalesTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SalesTeam::class, 'sales_team_id');
    }

    /**
     * @return BelongsTo<LostReason, $this>
     */
    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(LostReason::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    /**
     * Read only when referrer_type is customer (see referrerLabel()).
     *
     * @return BelongsTo<Customer, $this>
     */
    public function referrerCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_id');
    }

    /**
     * Read only when referrer_type is employee (see referrerLabel()).
     *
     * @return BelongsTo<User, $this>
     */
    public function referrerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * @return HasMany<LeadServiceLine, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(LeadServiceLine::class);
    }

    /**
     * @return MorphMany<CrmActivity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject');
    }

    /**
     * @return HasMany<LeadStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->latest('changed_at')->latest('id');
    }

    /**
     * @return HasMany<LeadAssignmentHistory, $this>
     */
    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(LeadAssignmentHistory::class)->latest('assigned_at')->latest('id');
    }
}
