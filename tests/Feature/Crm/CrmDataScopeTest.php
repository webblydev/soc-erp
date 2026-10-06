<?php

use App\Models\User;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\SalesTeam;

beforeEach(function () {
    seedCrm();
    seedAccessControl();

    $this->manager = User::factory()->create();
    $this->manager->assignRole('sales_manager');
    $this->executive = User::factory()->create();
    $this->executive->assignRole('sales_executive');
    $this->outsider = User::factory()->create();
    $this->outsider->assignRole('sales_executive');
    $this->director = User::factory()->create();
    $this->director->assignRole('management');

    $this->team = SalesTeam::factory()->managedBy($this->manager)->withMembers($this->executive)->create();

    $this->own = Lead::factory()->assignedTo($this->executive)->create(['sales_team_id' => $this->team->id]);
    $this->teamUnassigned = Lead::factory()->create(['sales_team_id' => $this->team->id]);
    $this->other = Lead::factory()->assignedTo($this->outsider)->create();
    $this->orphan = Lead::factory()->create();
});

function visibleLeadIds(User $user): array
{
    return Lead::query()->visibleTo($user, 'crm.leads')->orderBy('id')->pluck('id')->all();
}

test('a sales executive sees only their own leads (CRM-AC-02)', function () {
    expect(visibleLeadIds($this->executive))->toBe([$this->own->id]);
});

test('a manager sees their team leads, including unassigned team leads (spec R6)', function () {
    expect(visibleLeadIds($this->manager))->toBe([$this->own->id, $this->teamUnassigned->id]);
});

test('management sees every lead, unassigned ones included', function () {
    expect(visibleLeadIds($this->director))->toHaveCount(4);
});

test('a member who left the team drops out of the manager scope', function () {
    $this->team->members()->where('user_id', $this->executive->id)->update(['left_on' => today()]);
    $this->own->forceFill(['sales_team_id' => null])->save();

    expect(visibleLeadIds($this->manager))->toBe([$this->teamUnassigned->id]);
});

test('the lead policy follows the same scope for single records', function () {
    expect($this->executive->can('view', $this->own))->toBeTrue()
        ->and($this->executive->can('view', $this->other))->toBeFalse()
        ->and($this->executive->can('update', $this->own))->toBeTrue()
        ->and($this->executive->can('assign', $this->own))->toBeFalse()
        ->and($this->manager->can('assign', $this->teamUnassigned))->toBeTrue()
        ->and($this->director->can('delete', $this->orphan))->toBeTrue();
});

test('converted leads cannot be updated, assigned, deleted or converted again', function () {
    $lead = Lead::factory()->assignedTo($this->executive)->converted(Customer::factory()->create())->create();

    expect($this->executive->can('view', $lead))->toBeTrue()
        ->and($this->executive->can('update', $lead))->toBeFalse()
        ->and($this->executive->can('convert', $lead))->toBeFalse()
        ->and($this->director->can('assign', $lead))->toBeFalse()
        ->and($this->director->can('delete', $lead))->toBeFalse();
});

test('customers are scoped by account manager and acquirer', function () {
    $managed = Customer::factory()->managedBy($this->executive)->create();
    $acquired = Customer::factory()->create(['acquired_by_user_id' => $this->executive->id]);
    $foreign = Customer::factory()->managedBy($this->outsider)->create();

    expect(Customer::query()->visibleTo($this->executive, 'crm.customers')->pluck('id')->sort()->values()->all())->toBe([$managed->id, $acquired->id])
        ->and(Customer::query()->visibleTo($this->manager, 'crm.customers')->pluck('id')->sort()->values()->all())->toBe([$managed->id, $acquired->id])
        ->and($this->executive->can('view', $foreign))->toBeFalse()
        ->and($this->director->can('merge', $foreign))->toBeTrue();
});

test('activities are scoped by owner', function () {
    $mine = CrmActivity::factory()->on($this->own)->create(['owner_user_id' => $this->executive->id]);
    CrmActivity::factory()->on($this->other)->create(['owner_user_id' => $this->outsider->id]);

    expect(CrmActivity::query()->visibleTo($this->executive, 'crm.activities')->pluck('id')->all())->toBe([$mine->id])
        ->and(CrmActivity::query()->visibleTo($this->manager, 'crm.activities')->pluck('id')->all())->toBe([$mine->id]);
});

test('open and stale scopes', function () {
    $this->travel(20)->days();
    $fresh = Lead::factory()->create(['last_activity_at' => now()->subDays(2)]);
    $lost = Lead::factory()->withStatus('LOST')->create(['last_activity_at' => now()->subDays(30)]);

    $stale = Lead::query()->stale(14)->pluck('id')->all();

    expect($stale)->toContain($this->own->id, $this->orphan->id)
        ->not->toContain($fresh->id, $lost->id)
        ->and(Lead::query()->open()->pluck('id')->all())->not->toContain($lost->id);
});
