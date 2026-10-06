<?php

use App\Modules\Crm\Actions\ChangeLeadStatus;
use App\Modules\Crm\Actions\DeleteLead;
use App\Modules\Crm\Actions\MarkLeadLost;
use App\Modules\Crm\Actions\ReopenLead;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Models\LostReason;
use App\Support\Facades\Settings;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    seedCrm();
    $this->actor = userWithPermissions('crm.leads.view_own', 'crm.leads.update', 'crm.activities.view_own', 'crm.activities.create');
    $this->lead = Lead::factory()->assignedTo($this->actor)->create();
    $this->status = fn (string $code): int => LeadStatus::idFor($code);
});

test('a move to a status that needs a follow-up requires one (CRM-AC-03, CRM-BR-07)', function () {
    expectValidationError(fn () => app(ChangeLeadStatus::class)->handle($this->actor, $this->lead, ($this->status)('MEETING')), 'follow_up');

    app(ChangeLeadStatus::class)->handle($this->actor, $this->lead, ($this->status)('MEETING'), 'Met at office', [
        'activity_type_id' => ActivityType::idFor('MEETING'), 'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i'),
    ]);

    $lead = $this->lead->fresh();

    expect($lead->lead_status_id)->toBe(($this->status)('MEETING'))
        ->and($lead->next_follow_up_at)->not->toBeNull()
        ->and($lead->statusHistories()->first())->note->toBe('Met at office')->from_status_id->toBe(($this->status)('NEW'));
});

test('an existing open follow-up satisfies the rule, and backward moves are allowed', function () {
    CrmActivity::factory()->on($this->lead)->create(['owner_user_id' => $this->actor->id]);

    app(ChangeLeadStatus::class)->handle($this->actor, $this->lead, ($this->status)('PROPOSAL'));
    app(ChangeLeadStatus::class)->handle($this->actor, $this->lead->fresh(), ($this->status)('QUALIFIED'));

    expect($this->lead->fresh()->lead_status_id)->toBe(($this->status)('QUALIFIED'))
        ->and($this->lead->statusHistories()->count())->toBe(2);
});

test('closed statuses are refused, so WON comes only from conversion', function () {
    expectValidationError(fn () => app(ChangeLeadStatus::class)->handle($this->actor, $this->lead, ($this->status)('WON')), 'lead_status_id');
    expectValidationError(fn () => app(ChangeLeadStatus::class)->handle($this->actor, $this->lead, ($this->status)('LOST')), 'lead_status_id');
});

test('marking lost needs a reason when the setting says so (CRM-AC-04)', function () {
    expectValidationError(fn () => app(MarkLeadLost::class)->handle($this->actor, $this->lead, null), 'lost_reason_id');

    Settings::set('crm.lost_reason_required', false);
    app(MarkLeadLost::class)->handle($this->actor, $this->lead, null, 'Went quiet');

    expect($this->lead->fresh())->lead_status_id->toBe(($this->status)('LOST'))->lost_at->not->toBeNull()->lost_note->toBe('Went quiet');
});

test('reopening returns a lost lead to the status it had', function () {
    CrmActivity::factory()->on($this->lead)->create(['owner_user_id' => $this->actor->id]);
    app(ChangeLeadStatus::class)->handle($this->actor, $this->lead, ($this->status)('NEGOTIATION'));
    app(MarkLeadLost::class)->handle($this->actor, $this->lead->fresh(), LostReason::query()->value('id'));

    expectValidationError(fn () => app(ReopenLead::class)->handle($this->actor, $this->lead->fresh(), ''), 'reason');

    app(ReopenLead::class)->handle($this->actor, $this->lead->fresh(), 'Called back');

    expect($this->lead->fresh())->lead_status_id->toBe(($this->status)('NEGOTIATION'))->lost_at->toBeNull()->lost_reason_id->toBeNull();
});

test('converted leads refuse status changes, lost and delete', function () {
    $lead = Lead::factory()->assignedTo($this->actor)->converted(Customer::factory()->create())->create();
    $admin = superAdmin();

    expectValidationError(fn () => app(ChangeLeadStatus::class)->handle($admin, $lead, ($this->status)('CONTACTED')), 'lead');
    expectValidationError(fn () => app(MarkLeadLost::class)->handle($admin, $lead, LostReason::query()->value('id')), 'lead');
    expectValidationError(fn () => app(DeleteLead::class)->handle($admin, $lead), 'lead');
});

test('delete is soft, needs crm.leads.delete and trashes the open activities', function () {
    $open = CrmActivity::factory()->on($this->lead)->create(['owner_user_id' => $this->actor->id]);
    $done = CrmActivity::factory()->on($this->lead)->done()->create(['owner_user_id' => $this->actor->id]);

    expect(fn () => app(DeleteLead::class)->handle($this->actor, $this->lead))->toThrow(AuthorizationException::class);

    app(DeleteLead::class)->handle(userWithPermissions('crm.leads.view_all', 'crm.leads.delete'), $this->lead);

    expect(Lead::withTrashed()->find($this->lead->id)->trashed())->toBeTrue()
        ->and(CrmActivity::withTrashed()->find($open->id)->trashed())->toBeTrue()
        ->and(CrmActivity::withTrashed()->find($done->id)->trashed())->toBeFalse();
});

test('someone who cannot see the lead cannot change it', function () {
    app(ChangeLeadStatus::class)->handle(userWithPermissions('crm.leads.view_own', 'crm.leads.update'), $this->lead, ($this->status)('CONTACTED'));
})->throws(AuthorizationException::class);
