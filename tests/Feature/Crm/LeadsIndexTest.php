<?php

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Livewire\Leads\Index;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use App\Support\Facades\Settings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(fn () => Model::preventLazyLoading());
afterEach(fn () => Model::preventLazyLoading(false));

beforeEach(function () {
    seedCrm();
    Notification::fake();
    $this->me = userWithPermissions('crm.leads.view_own', 'crm.leads.update', 'crm.activities.view_own', 'crm.activities.create');
    $this->mine = Lead::factory()->assignedTo($this->me)->create(['name' => 'My Lead', 'phone' => '01711000111', 'expected_value' => '100000']);
    $this->theirs = Lead::factory()->assignedTo(User::factory()->create())->create(['name' => 'Their Lead']);
});

test('the list needs a lead view permission and shows only visible leads (CRM-AC-02)', function () {
    $this->actingAs(userWithPermissions('crm.customers.view_all'));
    $this->get(route('crm.leads.index'))->assertForbidden();

    $this->actingAs($this->me);
    $this->get(route('crm.leads.index'))->assertOk()->assertSee('My Lead')->assertDontSee('Their Lead');
});

test('search covers number, name and phone; filters by service and status', function () {
    $service = Service::factory()->create();
    $this->mine->services()->create(['service_id' => $service->id]);
    Lead::factory()->assignedTo($this->me)->withStatus('CONTACTED')->create(['name' => 'Contacted Lead']);

    $component = Livewire::actingAs($this->me)->test(Index::class);

    $component->set('search', '01711000111')->assertSee('My Lead')->assertDontSee('Contacted Lead');
    $component->set('search', '')->set('filters.service', (string) $service->id)->assertSee('My Lead')->assertDontSee('Contacted Lead');
    $component->set('filters', [])->set('statusFilter', [(string) LeadStatus::idFor('CONTACTED')])->assertSee('Contacted Lead')->assertDontSee('My Lead');
});

test('closed leads are hidden by default and presets switch the filters', function () {
    Lead::factory()->assignedTo($this->me)->withStatus('WON')->create(['name' => 'Won Lead', 'won_at' => now()]);
    Lead::factory()->assignedTo($this->me)->create(['name' => 'Due Lead', 'next_follow_up_at' => now()->subHour()]);

    Livewire::actingAs($this->me)->test(Index::class)
        ->assertDontSee('Won Lead')
        ->call('applyPreset', 'won_month')->assertSee('Won Lead')->assertDontSee('My Lead')
        ->call('applyPreset', 'overdue')->assertSee('Due Lead')->assertDontSee('My Lead');
});

test('stale leads are flagged and filterable', function () {
    $this->travel(20)->days();
    Lead::factory()->assignedTo($this->me)->create(['name' => 'Fresh Lead', 'last_activity_at' => now()]);

    Livewire::actingAs($this->me)->test(Index::class)
        ->set('filters.stale', '1')
        ->assertSee('My Lead')->assertDontSee('Fresh Lead');
});

test('the kanban shows open statuses with counts and value totals', function () {
    Livewire::actingAs($this->me)->test(Index::class)
        ->set('view', 'kanban')
        ->assertViewHas('columns', function ($columns) {
            $new = collect($columns)->firstWhere('code', 'NEW');

            return collect($columns)->pluck('code')->doesntContain('WON')
                && $new['count'] === 1
                && $new['total'] === '100000.00';
        });
});

test('dragging to a status that needs a follow-up opens the change-status sheet instead (CRM-AC-03)', function () {
    Livewire::actingAs($this->me)->test(Index::class)
        ->call('moveLead', (string) $this->mine->id, 0, (string) LeadStatus::idFor('MEETING'))
        ->assertDispatched('crm-change-status', lead: $this->mine->lead_number, mode: 'status', status: LeadStatus::idFor('MEETING'));

    expect($this->mine->fresh()->lead_status_id)->toBe(LeadStatus::idFor('NEW'));

    CrmActivity::factory()->on($this->mine)->create(['owner_user_id' => $this->me->id]);

    Livewire::actingAs($this->me)->test(Index::class)->call('moveLead', (string) $this->mine->id, 0, (string) LeadStatus::idFor('MEETING'));

    expect($this->mine->fresh()->lead_status_id)->toBe(LeadStatus::idFor('MEETING'));
});

test('dragging to WON or onto a hidden lead does nothing', function () {
    Livewire::actingAs($this->me)->test(Index::class)
        ->call('moveLead', (string) $this->mine->id, 0, (string) LeadStatus::idFor('WON'))
        ->assertDispatched('toast')
        ->call('moveLead', (string) $this->theirs->id, 0, (string) LeadStatus::idFor('CONTACTED'));

    expect($this->mine->fresh()->lead_status_id)->toBe(LeadStatus::idFor('NEW'))
        ->and($this->theirs->fresh()->lead_status_id)->toBe(LeadStatus::idFor('NEW'));
});

test('bulk actions skip leads outside the user scope', function () {
    Settings::set('crm.follow_up_required_on_status', []);

    Livewire::actingAs($this->me)->test(Index::class)
        ->set('selected', [(string) $this->mine->id, (string) $this->theirs->id])
        ->set('bulkStatusId', (string) LeadStatus::idFor('CONTACTED'))
        ->call('bulkChangeStatus')
        ->assertDispatched('toast');

    expect($this->mine->fresh()->lead_status_id)->toBe(LeadStatus::idFor('CONTACTED'))
        ->and($this->theirs->fresh()->lead_status_id)->toBe(LeadStatus::idFor('NEW'));
});

test('export needs crm.leads.export', function () {
    Livewire::actingAs($this->me)->test(Index::class)->call('export')->assertForbidden();

    createPermissions('crm.leads.export');
    $this->me->syncDirectPermissions(['crm.leads.view_own', 'crm.leads.export']);
    Livewire::actingAs($this->me)->test(Index::class)->call('export')->assertFileDownloaded();
});
