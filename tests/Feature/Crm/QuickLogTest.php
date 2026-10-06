<?php

use App\Modules\Crm\Livewire\Activities\QuickLog;
use App\Modules\Crm\Models\ActivityOutcome;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Lead;
use Livewire\Livewire;

beforeEach(function () {
    seedCrm();
    $this->owner = userWithPermissions('crm.leads.view_own', 'crm.activities.view_own', 'crm.activities.create', 'crm.activities.update');
    $this->lead = Lead::factory()->assignedTo($this->owner)->create();
});

test('logging a done call with a next follow-up', function () {
    Livewire::actingAs($this->owner)->test(QuickLog::class)
        ->dispatch('crm-log-activity', subjectType: 'lead', subjectId: $this->lead->id, mode: 'log')
        ->assertSet('done', true)
        ->set('activity_type_id', (string) ActivityType::idFor('CALL'))
        ->set('outcome_id', (string) ActivityOutcome::idFor('INTERESTED'))
        ->set('scheduleNext', true)
        ->set('next_type_id', (string) ActivityType::idFor('FOLLOW_UP'))
        ->set('next_at', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('crm-activity-saved');

    expect($this->lead->activities()->count())->toBe(2)->and($this->lead->fresh()->next_follow_up_at)->not->toBeNull();
});

test('the type title fills itself and schedule mode needs a time', function () {
    Livewire::actingAs($this->owner)->test(QuickLog::class)
        ->dispatch('crm-log-activity', subjectType: 'lead', subjectId: $this->lead->id, mode: 'schedule')
        ->assertSet('done', false)
        ->set('activity_type_id', (string) ActivityType::idFor('MEETING'))
        ->assertSet('title', 'Meeting')
        ->call('save')
        ->assertHasErrors('scheduled_at');
});

test('complete mode finishes an open activity', function () {
    $activity = CrmActivity::factory()->on($this->lead)->create(['owner_user_id' => $this->owner->id, 'activity_type_id' => ActivityType::idFor('CALL')]);

    Livewire::actingAs($this->owner)->test(QuickLog::class)
        ->dispatch('crm-log-activity', subjectType: 'lead', subjectId: $this->lead->id, mode: 'complete', activity: $activity->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($activity->fresh()->completed_at)->not->toBeNull();
});

test('a tampered subject id is refused', function () {
    $hidden = Lead::factory()->create();

    Livewire::actingAs($this->owner)->test(QuickLog::class)
        ->dispatch('crm-log-activity', subjectType: 'lead', subjectId: $hidden->id, mode: 'log')
        ->assertForbidden();
});
