<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\Crm\SalesTeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of sales staff under a manager (docs/03 §3.2). A user has at most one active
 * membership (spec R7); a manager need not be a member and may manage several teams.
 *
 * @property int $id
 * @property string $name
 * @property int|null $manager_user_id
 * @property int|null $business_line_id
 * @property string|null $monthly_target_amount
 * @property bool $is_active
 * @property-read User|null $manager
 * @property-read BusinessLine|null $businessLine
 */
#[Fillable(['name', 'manager_user_id', 'business_line_id', 'monthly_target_amount', 'is_active'])]
#[UseFactory(SalesTeamFactory::class)]
class SalesTeam extends Model
{
    /** @use HasFactory<SalesTeamFactory> */
    use Auditable, HasFactory, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['monthly_target_amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /**
     * Teams the user manages.
     *
     * @return list<int>
     */
    public static function managedTeamIds(User $user): array
    {
        return static::query()->where('manager_user_id', $user->id)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Active members of every team the user manages.
     *
     * @return list<int>
     */
    public static function managedMemberIds(User $user): array
    {
        return SalesTeamMember::query()
            ->whereNull('left_on')
            ->whereIn('sales_team_id', static::query()->select('id')->where('manager_user_id', $user->id))
            ->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
    }

    public static function activeTeamIdFor(int $userId): ?int
    {
        $id = SalesTeamMember::query()->where('user_id', $userId)->whereNull('left_on')->value('sales_team_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /**
     * @return BelongsTo<BusinessLine, $this>
     */
    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    /**
     * @return HasMany<SalesTeamMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(SalesTeamMember::class);
    }

    /**
     * @return HasMany<SalesTeamMember, $this>
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('left_on');
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
