<?php

use App\Models\User;
use App\Modules\Crm\Livewire\Teams\Form;
use App\Modules\Crm\Livewire\Teams\Index;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\SalesTeam;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(fn () => Model::preventLazyLoading());
afterEach(fn () => Model::preventLazyLoading(false));

beforeEach(fn () => seedCrm());

test('each teams route needs its permission', function () {
    $team = SalesTeam::factory()->create();

    $this->actingAs(userWithPermissions('crm.leads.view_own'));
    $this->get(route('crm.teams.index'))->assertForbidden();

    $this->actingAs(userWithPermissions('crm.teams.view'));
    $this->get(route('crm.teams.index'))->assertOk()->assertSee($team->name);
    $this->get(route('crm.teams.create'))->assertForbidden();

    $this->actingAs(userWithPermissions('crm.teams.view', 'crm.teams.manage'));
    $this->get(route('crm.teams.edit', $team))->assertOk();
});

test('the list shows members, open leads and won this month', function () {
    $member = User::factory()->create();
    $team = SalesTeam::factory()->withMembers($member)->create(['name' => 'Team Dhaka']);
    Lead::factory()->count(2)->create(['sales_team_id' => $team->id]);
    Lead::factory()->withStatus('WON')->create(['sales_team_id' => $team->id, 'won_at' => now()]);

    Livewire::actingAs(userWithPermissions('crm.teams.view'))->test(Index::class)
        ->assertSee('Team Dhaka')
        ->assertViewHas('rows', fn ($rows) => $rows->first()->active_members_count === 1
            && $rows->first()->open_leads_count === 2
            && $rows->first()->won_this_month_count === 1);
});

test('the form adds and removes member rows and saves', function () {
    $manager = User::factory()->create();
    $member = User::factory()->create();

    Livewire::actingAs(userWithPermissions('crm.teams.view', 'crm.teams.manage'))->test(Form::class)
        ->set('name', 'Team North')
        ->set('manager_user_id', (string) $manager->id)
        ->call('addMember')
        ->set('members.0.user_id', (string) $member->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('crm.teams.index'));

    expect(SalesTeam::query()->where('name', 'Team North')->first()->activeMembers()->pluck('user_id')->all())->toBe([$member->id]);
});

test('bulk delete skips teams that have leads and needs teams.manage', function () {
    $busy = SalesTeam::factory()->create();
    $empty = SalesTeam::factory()->create();
    Lead::factory()->create(['sales_team_id' => $busy->id]);

    Livewire::actingAs(userWithPermissions('crm.teams.view'))->test(Index::class)
        ->set('selected', [(string) $empty->id])
        ->call('deleteSelected')
        ->assertForbidden();

    Livewire::actingAs(userWithPermissions('crm.teams.view', 'crm.teams.manage'))->test(Index::class)
        ->set('selected', [(string) $busy->id, (string) $empty->id])
        ->call('deleteSelected')
        ->assertDispatched('toast', type: 'warning', description: '1 deleted, 1 skipped. Teams with leads cannot be deleted. Deactivate them instead.');

    expect(SalesTeam::query()->whereKey([$busy->id, $empty->id])->pluck('id')->all())->toBe([$busy->id]);
});
