<?php

namespace Tests\Fixtures;

use App\Models\User;
use App\Support\DataScope\HasDataScope;
use Illuminate\Database\Eloquent\Model;

class ScopedRecord extends Model
{
    use HasDataScope;

    /** @var list<int> */
    public static array $teamUserIds = [];

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
}
