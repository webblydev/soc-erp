<?php

namespace Database\Seeders\Legacy;

use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Crm\Models\SalesTeamMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * v1 team letters (tbl_user.team_name) → sales teams "Team A" … (legacy seed spec L7). The
 * manager is the team's first active team-manager user; members are its active users who added
 * clients, joined on their first client's date. A user is in one team only (CRM R7).
 */
class ImportSalesTeams
{
    public function run(LegacyContext $context): int
    {
        $created = 0;
        $firstClientAt = $context->legacy()->table('tbl_client')
            ->selectRaw('add_by, min(add_time) as first_at')->groupBy('add_by')->pluck('first_at', 'add_by');

        /** @var Collection<string, Collection<int, object>> $teams */
        $teams = $context->legacy()->table('tbl_user')->where('status', 'a')->orderBy('id')->get()
            ->filter(fn (object $row): bool => preg_match('/^[a-z]$/i', trim((string) $row->team_name)) === 1)
            ->groupBy(fn (object $row): string => Str::upper(trim((string) $row->team_name)))
            ->sortKeys();

        foreach ($teams as $letter => $rows) {
            $manager = $rows->first(fn (object $row): bool => $row->type === 't');
            $team = SalesTeam::query()->firstWhere('name', "Team {$letter}");

            if ($team === null) {
                $team = SalesTeam::query()->create([
                    'name' => "Team {$letter}",
                    'manager_user_id' => $manager !== null ? ($context->users[(int) $manager->id] ?? null) : null,
                    'is_active' => true,
                ]);
                $created++;
            }

            foreach ($rows as $row) {
                $userId = $context->users[(int) $row->id] ?? null;
                $firstAt = LegacyMap::date($firstClientAt[$row->id] ?? null);

                if ($userId === null) {
                    continue;
                }

                if ($firstAt !== null && ! SalesTeamMember::query()->where('user_id', $userId)->whereNull('left_on')->exists()) {
                    SalesTeamMember::query()->create(['sales_team_id' => $team->id, 'user_id' => $userId, 'joined_on' => Carbon::parse($firstAt)]);
                }

                $memberOf = SalesTeamMember::query()->where('user_id', $userId)->whereNull('left_on')->value('sales_team_id');
                $teamId = $memberOf ?? ($team->manager_user_id === $userId ? $team->id : null);

                if ($teamId !== null && ! isset($context->teamOfUser[$userId])) {
                    $context->teamOfUser[$userId] = (int) $teamId;
                }
            }
        }

        return $created;
    }
}
