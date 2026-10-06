<?php

use App\Modules\Crm\Livewire\Leads\ChangeStatus;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Models\LostReason;
use Livewire\Livewire;

beforeEach(function () {
    seedCrm();
    $this->owner = userWithPermissions('crm.leads.view_own', 'crm.leads.update', 'crm.activities.view_own', 'crm.activities.create');
    $this->lead = Lead::factory()->assignedTo($this->owner)->create();
});

test('opening shows follow-up fields when the chosen status needs one', function () {
    Livewire::actingAs($this->owner)->test(ChangeStatus::class)
        ->dispatch('crm-change-status', lead: $this->lead->lead_number, mode: 'status', status: LeadStatus::idFor('MEETING'))
        ->assertSet('statusId', LeadStatus::idFor('MEETING'))
        ->assertSet('needsFollowUp', true)
        ->call('save')
        ->assertHasErrors('follow_up');
});

test('saving with a follow-up changes the status and announces it', function () {
    Livewire::actingAs($this->owner)->test(ChangeStatus::class)
        ->dispatch('crm-change-status', lead: $this->lead->lead_number, mode: 'status', status: LeadStatus::idFor('MEETING'))
        ->set('followUpTypeId', (string) ActivityType::idFor('MEETING'))
        ->set('followUpAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('crm-lead-updated');

    expect($this->lead->fresh()->lead_status_id)->toBe(LeadStatus::idFor('MEETING'));
});

test('lost mode needs a reason; reopen mode needs one too', function () {
    $component = Livewire::actingAs($this->owner)->test(ChangeStatus::class)
        ->dispatch('crm-change-status', lead: $this->lead->lead_number, mode: 'lost')
        ->call('save')->assertHasErrors('lost_reason_id')
        ->set('lostReasonId', (string) LostReason::query()->value('id'))
        ->call('save')->assertHasNoErrors();

    $component->dispatch('crm-change-status', lead: $this->lead->lead_number, mode: 'reopen')
        ->call('save')->assertHasErrors('reason')
        ->set('reason', 'Called back')->call('save')->assertHasNoErrors();

    expect($this->lead->fresh()->lead_status_id)->toBe(LeadStatus::idFor('NEW'));
});

test('a lead the user cannot see cannot be opened', function () {
    Livewire::actingAs(userWithPermissions('crm.leads.view_own', 'crm.leads.update'))->test(ChangeStatus::class)
        ->dispatch('crm-change-status', lead: $this->lead->lead_number, mode: 'status')
        ->assertForbidden();
});
