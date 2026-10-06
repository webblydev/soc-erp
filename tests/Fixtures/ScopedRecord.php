<?php

namespace Tests\Fixtures;

use App\Models\User;
use App\Support\DataScope\HasDataScope;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ScopedRecord extends Model
{
    use HasDataScope;

    /** @var list<int> */
    public static array $teamUserIds = [];

    /** @var (Closure(Builder<self>): mixed)|null */
    public static ?Closure $teamExtra = null;

    protected $guarded = [];

    public $timestamps = false;

    public function dataScopeOwnerColumns(): array
    {
        return ['owner_id', 'assignee_id'];
    }

    public function dataScopeTeamUserIds(User $user): array
    {
        return static::$teamUserIds;
    }

    protected function dataScopeTeamExtra(Builder $query, User $user): void
    {
        if (static::$teamExtra !== null) {
            (static::$teamExtra)($query);
        }
    }
}
