<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Crm\Models\SalesTeamMember;

/**
 * Picks the next assignee for a team (docs/03 §6.3, spec R9): the active member with the fewest
 * open leads; ties go to the member whose latest assignment is oldest (never assigned first).
 */
class RoundRobinAssigner
{
    public function pick(SalesTeam $team): ?int
    {
        $members = $team->activeMembers()->with('user')->get()
            ->filter(fn (SalesTeamMember $member): bool => $member->user->is_active && $member->user->can('crm.leads.view'))
            ->pluck('user_id');

        if ($members->isEmpty()) {
            return null;
        }

        $open = Lead::query()->open()->whereIn('assigned_to', $members)->selectRaw('assigned_to, COUNT(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $latest = Lead::query()->whereIn('assigned_to', $members)->selectRaw('assigned_to, MAX(assigned_at) as latest')->groupBy('assigned_to')->pluck('latest', 'assigned_to');

        return (int) $members
            ->sortBy(fn (int $userId): array => [(int) ($open[$userId] ?? 0), (string) ($latest[$userId] ?? '')])
            ->first();
    }
}
