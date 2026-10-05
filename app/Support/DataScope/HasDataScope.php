<?php

namespace App\Support\DataScope;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data-scope visibility for list queries (docs/00 §6, CM-BR-08):
 * {resource}.view_all → everything, .view_team → user + team, .view_own → user, none → nothing.
 */
trait HasDataScope
{
    /**
     * Columns holding the owner / assignee / creator user id.
     *
     * @return list<string>
     */
    abstract public function dataScopeOwnerColumns(): array;

    /**
     * User ids whose records the given user may see under view_team.
     *
     * @return list<int>
     */
    abstract public function dataScopeTeamUserIds(User $user): array;

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, string $resource): void
    {
        if ($user->hasRole(Role::SUPER_ADMIN) || $user->hasPermission("{$resource}.view_all")) {
            return;
        }

        $userIds = match (true) {
            $user->hasPermission("{$resource}.view_team") => array_values(array_unique([$user->id, ...$this->dataScopeTeamUserIds($user)])),
            $user->hasPermission("{$resource}.view_own") => [$user->id],
            default => [],
        };

        if ($userIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($userIds): void {
            foreach ($this->dataScopeOwnerColumns() as $column) {
                $query->orWhereIn($this->qualifyColumn($column), $userIds);
            }
        });
    }
}
