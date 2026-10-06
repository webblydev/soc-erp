<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's membership of a sales team (docs/03 §3.3). Leaving sets left_on; rows are never deleted.
 *
 * @property int $id
 * @property int $sales_team_id
 * @property int $user_id
 * @property Carbon $joined_on
 * @property Carbon|null $left_on
 * @property-read SalesTeam $team
 * @property-read User $user
 */
#[Fillable(['sales_team_id', 'user_id', 'joined_on', 'left_on'])]
class SalesTeamMember extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['joined_on' => 'date', 'left_on' => 'date'];
    }

    /**
     * @return BelongsTo<SalesTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SalesTeam::class, 'sales_team_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
