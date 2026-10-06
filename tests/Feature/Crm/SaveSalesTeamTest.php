<?php

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Crm\Actions\SaveSalesTeam;
use App\Modules\Crm\Models\SalesTeam;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->actor = userWithPermissions('crm.teams.view', 'crm.teams.manage');
    $this->manager = User::factory()->create();
    $this->rahim = User::factory()->create();
    $this->karim = User::factory()->create();
});

function teamInput(array $overrides = []): array
{
    return [
        'name' => 'Team A', 'manager_user_id' => test()->manager->id, 'business_line_id' => null,
        'monthly_target_amount' => '500000', 'is_active' => true,
        'members' => [['user_id' => test()->rahim->id, 'joined_on' => '2026-10-01']],
        ...$overrides,
    ];
}

test('a team is created with its members', function () {
    $team = app(SaveSalesTeam::class)->handle($this->actor, teamInput());

    expect($team->name)->toBe('Team A')
        ->and($team->activeMembers()->pluck('user_id')->all())->toBe([$this->rahim->id])
        ->and($team->monthly_target_amount)->toBe('500000.00');
});

test('removing a member sets the left date and keeps the row', function () {
    $team = app(SaveSalesTeam::class)->handle($this->actor, teamInput());

    app(SaveSalesTeam::class)->handle($this->actor, teamInput(['members' => [['user_id' => $this->karim->id, 'joined_on' => '2026-10-05']]]), $team);

    expect($team->members()->where('user_id', $this->rahim->id)->value('left_on'))->not->toBeNull()
        ->and($team->activeMembers()->pluck('user_id')->all())->toBe([$this->karim->id]);
});

test('a user can be in only one active team (spec R7)', function () {
    app(SaveSalesTeam::class)->handle($this->actor, teamInput());

    expectValidationError(fn () => app(SaveSalesTeam::class)->handle($this->actor, teamInput(['name' => 'Team B'])), 'members.0.user_id');

    SalesTeam::query()->where('name', 'Team A')->first()->members()->update(['left_on' => today()]);

    expect(app(SaveSalesTeam::class)->handle($this->actor, teamInput(['name' => 'Team B']))->activeMembers()->count())->toBe(1);
});

test('names are unique and the manager is required', function () {
    app(SaveSalesTeam::class)->handle($this->actor, teamInput(['members' => []]));

    expectValidationError(fn () => app(SaveSalesTeam::class)->handle($this->actor, teamInput(['members' => []])), 'name');
    expectValidationError(fn () => app(SaveSalesTeam::class)->handle($this->actor, teamInput(['name' => 'Team C', 'manager_user_id' => null, 'members' => []])), 'manager_user_id');
});

test('the same user twice in one list is refused', function () {
    expectValidationError(fn () => app(SaveSalesTeam::class)->handle($this->actor, teamInput(['members' => [
        ['user_id' => $this->rahim->id, 'joined_on' => '2026-10-01'],
        ['user_id' => $this->rahim->id, 'joined_on' => '2026-10-02'],
    ]])), 'members.1.user_id');
});

test('internal business lines and inactive users are refused', function () {
    $internal = BusinessLine::factory()->create(['is_internal' => true]);
    $inactive = User::factory()->inactive()->create();

    expectValidationError(fn () => app(SaveSalesTeam::class)->handle($this->actor, teamInput(['business_line_id' => $internal->id])), 'business_line_id');
    expectValidationError(fn () => app(SaveSalesTeam::class)->handle($this->actor, teamInput(['manager_user_id' => $inactive->id])), 'manager_user_id');
});

test('managing teams needs crm.teams.manage', function () {
    app(SaveSalesTeam::class)->handle(userWithPermissions('crm.teams.view'), teamInput());
})->throws(AuthorizationException::class);
