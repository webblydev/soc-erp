<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\DataScope\HasDataScope;
use Database\Factories\Crm\CrmActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A call, meeting, note or follow-up on a lead or customer (docs/03 §3.8). Open = not completed;
 * overdue = open with scheduled_at in the past. A follow-up is any open activity with scheduled_at (spec R12).
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property int $activity_type_id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $completed_at
 * @property int|null $duration_minutes
 * @property int|null $outcome_id
 * @property int $owner_user_id
 * @property Carbon|null $reminder_at
 * @property Carbon|null $reminder_sent_at
 * @property string|null $location_text
 * @property-read Lead|Customer|null $subject
 * @property-read ActivityType $type
 * @property-read ActivityOutcome|null $outcome
 * @property-read User $owner
 */
#[Fillable(['activity_type_id', 'title', 'description', 'scheduled_at', 'completed_at', 'duration_minutes', 'outcome_id', 'owner_user_id', 'reminder_at', 'location_text'])]
#[UseFactory(CrmActivityFactory::class)]
class CrmActivity extends Model
{
    /** @use HasFactory<CrmActivityFactory> */
    use Auditable, HasDataScope, HasFactory, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function dataScopeOwnerColumns(): array
    {
        return ['owner_user_id'];
    }

    public function dataScopeTeamUserIds(User $user): array
    {
        return SalesTeam::managedMemberIds($user);
    }

    public function isOpen(): bool
    {
        return $this->completed_at === null;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->scheduled_at !== null && $this->scheduled_at->isPast();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('completed_at'));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeDone(Builder $query): void
    {
        $query->whereNotNull($this->qualifyColumn('completed_at'));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('completed_at'))
            ->whereNotNull($this->qualifyColumn('scheduled_at'))
            ->where($this->qualifyColumn('scheduled_at'), '<', now());
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<ActivityType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    /**
     * @return BelongsTo<ActivityOutcome, $this>
     */
    public function outcome(): BelongsTo
    {
        return $this->belongsTo(ActivityOutcome::class, 'outcome_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
