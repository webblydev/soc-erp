<?php

use App\Models\User;
use App\Modules\Crm\Jobs\FlagStaleLeads;
use App\Modules\Crm\Jobs\SendDailyDigest;
use App\Modules\Crm\Jobs\SendDueReminders;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Crm\Notifications\DailyDigest;
use App\Modules\Crm\Notifications\FollowUpReminder;
use App\Modules\Crm\Notifications\LeadStale;
use App\Support\Facades\Settings;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedCrm();
    Notification::fake();
    $this->travelTo(now()->setDateTime(2026, 10, 7, 14, 0));
    $this->owner = userWithPermissions('crm.leads.view_own', 'crm.activities.view_own');
    $this->lead = Lead::factory()->assignedTo($this->owner)->create();
});

test('a 15:00 follow-up with a 30 minute reminder notifies at 14:30, once (CRM-AC-07)', function () {
    CrmActivity::factory()->on($this->lead)->create([
        'owner_user_id' => $this->owner->id, 'scheduled_at' => now()->setTime(15, 0), 'reminder_at' => now()->setTime(14, 30),
    ]);

    $this->travelTo(now()->setTime(14, 25));
    (new SendDueReminders)->handle();
    Notification::assertNothingSent();

    $this->travelTo(now()->setTime(14, 30));
    (new SendDueReminders)->handle();
    (new SendDueReminders)->handle();

    Notification::assertSentToTimes($this->owner, FollowUpReminder::class, 1);
});

test('completed activities and activities of a deleted lead get no reminder', function () {
    CrmActivity::factory()->on($this->lead)->done()->create(['owner_user_id' => $this->owner->id, 'reminder_at' => now()->subMinute()]);

    $gone = Lead::factory()->assignedTo($this->owner)->create();
    CrmActivity::factory()->on($gone)->create(['owner_user_id' => $this->owner->id, 'reminder_at' => now()->subMinute()]);
    $gone->delete();

    (new SendDueReminders)->handle();

    Notification::assertNothingSent();
});

test('the digest goes out once a day after the set time, with a team summary for managers', function () {
    Settings::set('notifications.daily_digest_time', '09:00');
    $manager = userWithPermissions('crm.leads.view_team', 'crm.activities.view_team');
    SalesTeam::factory()->managedBy($manager)->withMembers($this->owner)->create();

    CrmActivity::factory()->on($this->lead)->create(['owner_user_id' => $this->owner->id, 'scheduled_at' => now()->subDay()]);
    CrmActivity::factory()->on($this->lead)->create(['owner_user_id' => $this->owner->id, 'scheduled_at' => now()->addHours(2)]);

    $this->travelTo(now()->setTime(8, 59));
    (new SendDailyDigest)->handle();
    Notification::assertNothingSent();

    $this->travelTo(now()->setTime(9, 0));
    (new SendDailyDigest)->handle();
    (new SendDailyDigest)->handle();

    Notification::assertSentToTimes($this->owner, DailyDigest::class, 1);
    Notification::assertSentTo($this->owner, DailyDigest::class, fn (DailyDigest $digest) => $digest->overdue->count() === 1 && $digest->today->count() === 1);
    Notification::assertSentTo($manager, DailyDigest::class, fn (DailyDigest $digest) => $digest->teamOverdue === [$this->owner->name => 1]);
});

test('stale leads notify the owner and team manager once', function () {
    $manager = User::factory()->create();
    $team = SalesTeam::factory()->managedBy($manager)->withMembers($this->owner)->create();
    $this->lead->forceFill(['sales_team_id' => $team->id])->save();

    $this->travel(15)->days();
    (new FlagStaleLeads)->handle();
    (new FlagStaleLeads)->handle();

    Notification::assertSentToTimes($this->owner, LeadStale::class, 1);
    Notification::assertSentToTimes($manager, LeadStale::class, 1);
    expect($this->lead->fresh()->stale_notified_at)->not->toBeNull();
});

test('the jobs are scheduled', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('crm:reminders')->expectsOutputToContain('crm:daily-digest')->expectsOutputToContain('crm:stale-leads');
});
