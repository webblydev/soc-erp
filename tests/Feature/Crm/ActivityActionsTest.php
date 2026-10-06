<?php

use App\Models\User;
use App\Modules\Crm\Actions\CompleteActivity;
use App\Modules\Crm\Actions\DeleteActivity;
use App\Modules\Crm\Actions\LogActivity;
use App\Modules\Crm\Actions\RescheduleActivity;
use App\Modules\Crm\Models\ActivityOutcome;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    seedCrm();
    $this->travelTo(now()->setDateTime(2026, 10, 7, 10, 0));
    $this->actor = userWithPermissions('crm.leads.view_own', 'crm.customers.view_own', 'crm.activities.view_own', 'crm.activities.create', 'crm.activities.update', 'crm.activities.delete');
    $this->lead = Lead::factory()->assignedTo($this->actor)->create(['stale_notified_at' => now()->subDay()]);
    $this->call = ActivityType::idFor('CALL');
});

function logInput(array $overrides = []): array
{
    return ['activity_type_id' => test()->call, 'title' => null, 'description' => null, 'done' => false, 'scheduled_at' => '2026-10-08 15:00', 'reminder_minutes' => 30, ...$overrides];
}

test('scheduling sets the reminder and the next follow-up on the lead', function () {
    $activity = app(LogActivity::class)->handle($this->actor, $this->lead, logInput());

    expect($activity->title)->toBe('Call')
        ->and($activity->owner_user_id)->toBe($this->actor->id)
        ->and($activity->reminder_at->format('Y-m-d H:i'))->toBe('2026-10-08 14:30')
        ->and($this->lead->fresh()->next_follow_up_at->format('Y-m-d H:i'))->toBe('2026-10-08 15:00');
});

test('logging a done activity moves last activity, clears stale and can schedule the next one', function () {
    app(LogActivity::class)->handle($this->actor, $this->lead, logInput([
        'done' => true, 'scheduled_at' => null, 'outcome_id' => ActivityOutcome::idFor('INTERESTED'),
        'next_follow_up' => ['activity_type_id' => ActivityType::idFor('FOLLOW_UP'), 'scheduled_at' => '2026-10-10 11:00'],
    ]));

    $lead = $this->lead->fresh();

    expect($lead->last_activity_at->format('Y-m-d H:i'))->toBe('2026-10-07 10:00')
        ->and($lead->stale_notified_at)->toBeNull()
        ->and($lead->next_follow_up_at->format('Y-m-d H:i'))->toBe('2026-10-10 11:00')
        ->and($lead->activities()->count())->toBe(2)
        ->and($lead->activities()->whereNull('completed_at')->first()->reminder_at->format('H:i'))->toBe('10:30');
});

test('a meeting logged as done needs a duration; a planned one needs a future time', function () {
    expectValidationError(fn () => app(LogActivity::class)->handle($this->actor, $this->lead, logInput(['activity_type_id' => ActivityType::idFor('MEETING'), 'done' => true, 'scheduled_at' => null])), 'duration_minutes');
    expectValidationError(fn () => app(LogActivity::class)->handle($this->actor, $this->lead, logInput(['scheduled_at' => '2026-10-06 09:00'])), 'scheduled_at');
    expectValidationError(fn () => app(LogActivity::class)->handle($this->actor, $this->lead, logInput(['reminder_minutes' => 7])), 'reminder_minutes');
});

test('completing and deleting refresh the lead columns', function () {
    $activity = app(LogActivity::class)->handle($this->actor, $this->lead, logInput());

    $this->travel(1)->days();
    app(CompleteActivity::class)->handle($this->actor, $activity, ['outcome_id' => ActivityOutcome::idFor('CALL_BACK')]);

    expect($this->lead->fresh())->next_follow_up_at->toBeNull()
        ->and($this->lead->fresh()->last_activity_at->format('Y-m-d'))->toBe('2026-10-08');

    expectValidationError(fn () => app(CompleteActivity::class)->handle($this->actor, $activity->fresh(), []), 'activity');

    $planned = app(LogActivity::class)->handle($this->actor, $this->lead, logInput(['scheduled_at' => '2026-10-12 10:00']));
    app(DeleteActivity::class)->handle($this->actor, $planned);

    expect($this->lead->fresh()->next_follow_up_at)->toBeNull();
});

test('rescheduling clears the sent reminder', function () {
    $activity = app(LogActivity::class)->handle($this->actor, $this->lead, logInput());
    $activity->forceFill(['reminder_sent_at' => now()])->save();

    app(RescheduleActivity::class)->handle($this->actor, $activity, ['scheduled_at' => '2026-10-09 15:00', 'reminder_minutes' => 60]);

    expect($activity->fresh())->reminder_sent_at->toBeNull()
        ->and($activity->fresh()->reminder_at->format('Y-m-d H:i'))->toBe('2026-10-09 14:00')
        ->and($this->lead->fresh()->next_follow_up_at->format('Y-m-d H:i'))->toBe('2026-10-09 15:00');
});

test('customer activities never touch leads', function () {
    $customer = Customer::factory()->managedBy($this->actor)->create();

    app(LogActivity::class)->handle($this->actor, $customer, logInput());

    expect($this->lead->fresh()->next_follow_up_at)->toBeNull()->and($customer->activities()->count())->toBe(1);
});

test('converted leads still accept activities (CRM-BR-10)', function () {
    $lead = Lead::factory()->assignedTo($this->actor)->converted(Customer::factory()->create())->create();

    expect(app(LogActivity::class)->handle($this->actor, $lead, logInput())->subject_id)->toBe($lead->id);
});

test('a hidden subject or a missing permission is refused', function () {
    $hidden = Lead::factory()->assignedTo(User::factory()->create())->create();

    expect(fn () => app(LogActivity::class)->handle($this->actor, $hidden, logInput()))->toThrow(AuthorizationException::class);

    $viewer = userWithPermissions('crm.leads.view_all', 'crm.activities.view_all');
    expect(fn () => app(LogActivity::class)->handle($viewer, $this->lead, logInput()))->toThrow(AuthorizationException::class);
});

test('the owner must be an active user who can see activities', function () {
    $outsider = User::factory()->create();

    expectValidationError(fn () => app(LogActivity::class)->handle($this->actor, $this->lead, logInput(['owner_user_id' => $outsider->id])), 'owner_user_id');
});
