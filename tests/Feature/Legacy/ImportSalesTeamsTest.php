<?php

use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Crm\Models\SalesTeamMember;
use Database\Seeders\Legacy\ImportSalesTeams;
use Database\Seeders\Legacy\ImportUsers;
use Database\Seeders\Legacy\LegacyContext;

beforeEach(function () {
    useLegacyDatabase();
    seedAccessControl();

    legacyRow('tbl_user', ['id' => 9, 'name' => 'POD', 'user_name' => 'pod', 'type' => 't', 'team_name' => 'a', 'status' => 'a']);
    legacyRow('tbl_user', ['id' => 16, 'name' => 'MSD', 'user_name' => 'msd', 'type' => 't', 'team_name' => 'a', 'status' => 'a']);
    legacyRow('tbl_user', ['id' => 21, 'name' => 'Robin', 'user_name' => 'robin', 'type' => 'u', 'team_name' => 'a', 'status' => 'a']);
    legacyRow('tbl_user', ['id' => 12, 'name' => 'Khokon', 'user_name' => 'khokon', 'type' => 't', 'team_name' => 'b', 'status' => 'a']);
    legacyRow('tbl_user', ['id' => 14, 'name' => 'Riaz', 'user_name' => 'riaz', 'type' => 'u', 'team_name' => 'b', 'status' => 'a']);
    legacyRow('tbl_user', ['id' => 5, 'name' => 'Ashraful', 'user_name' => 'ashraful', 'type' => 'u', 'team_name' => 'b', 'status' => 'd']);

    foreach ([[1, 16, '2024-01-10 10:00:00'], [2, 16, '2023-12-01 09:00:00'], [3, 21, '2024-03-05 11:00:00'], [4, 14, '2024-02-02 12:00:00'], [5, 5, '2023-07-01 10:00:00']] as [$id, $by, $at]) {
        legacyRow('tbl_client', ['id' => $id, 'client_id' => 'SOC-CON-'.$id, 'client_name' => 'Client '.$id, 'phone' => '0171100000'.$id, 'add_by' => $by, 'add_time' => $at]);
    }

    $this->context = new LegacyContext;
    app(ImportUsers::class)->run($this->context);
});

test('team letters become teams with a manager and members who own clients', function () {
    expect(app(ImportSalesTeams::class)->run($this->context))->toBe(2);

    $teamA = SalesTeam::query()->where('name', 'Team A')->with('manager')->sole();
    $teamB = SalesTeam::query()->where('name', 'Team B')->with('manager')->sole();
    $members = fn (SalesTeam $team) => SalesTeamMember::query()->where('sales_team_id', $team->id)->with('user')->get()->mapWithKeys(fn ($m) => [$m->user->username => $m->joined_on->toDateString()])->all();

    expect($teamA->manager->username)->toBe('pod')
        ->and($members($teamA))->toBe(['msd' => '2023-12-01', 'robin' => '2024-03-05'])
        ->and($teamB->manager->username)->toBe('khokon')
        ->and($members($teamB))->toBe(['riaz' => '2024-02-02'])
        ->and($this->context->teamOfUser[$this->context->users[21]])->toBe($teamA->id);
});

test('re-running adds nothing', function () {
    app(ImportSalesTeams::class)->run($this->context);

    expect(app(ImportSalesTeams::class)->run($this->context))->toBe(0)
        ->and(SalesTeamMember::query()->count())->toBe(3);
});
