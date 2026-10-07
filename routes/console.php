<?php

use App\Modules\Crm\Jobs\FlagStaleLeads;
use App\Modules\Crm\Jobs\SendDailyDigest;
use App\Modules\Crm\Jobs\SendDueReminders;
use App\Modules\Estimation\Jobs\NotifyOverdueFindings;
use App\Modules\Estimation\Jobs\SendMbVerificationDigest;
use App\Modules\Hrm\Jobs\NotifyExpiringDocuments;
use App\Modules\Hrm\Jobs\NotifyProbationEnding;
use App\Modules\Projects\Jobs\ArchiveDoneTasks;
use App\Modules\Projects\Jobs\EvaluateScheduleTriggers;
use App\Modules\Projects\Jobs\NotifyOverdueApprovals;
use App\Modules\Projects\Jobs\SendTaskAlerts;
use App\Support\Imports\ImportStorage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => ImportStorage::prune())->daily()->name('imports:prune');

Schedule::job(new SendDueReminders)->everyFiveMinutes()->name('crm:reminders')->withoutOverlapping();
Schedule::job(new SendDailyDigest)->everyMinute()->name('crm:daily-digest')->withoutOverlapping();
Schedule::job(new FlagStaleLeads)->dailyAt('08:00')->name('crm:stale-leads')->withoutOverlapping();

Schedule::job(new NotifyExpiringDocuments)->dailyAt('08:15')->name('hrm:expiring-documents')->withoutOverlapping();
Schedule::job(new NotifyProbationEnding)->dailyAt('08:20')->name('hrm:probation-ending')->withoutOverlapping();

Schedule::job(new EvaluateScheduleTriggers)->dailyAt('07:00')->name('projects:schedule-triggers')->withoutOverlapping();
Schedule::job(new SendTaskAlerts)->dailyAt('08:30')->name('projects:task-alerts')->withoutOverlapping();
Schedule::job(new NotifyOverdueApprovals)->dailyAt('08:35')->name('projects:overdue-approvals')->withoutOverlapping();
Schedule::job(new ArchiveDoneTasks)->dailyAt('02:00')->name('projects:archive-done-tasks')->withoutOverlapping();

Schedule::job(new SendMbVerificationDigest)->dailyAt('08:40')->name('site:mb-digest')->withoutOverlapping();
Schedule::job(new NotifyOverdueFindings)->dailyAt('08:45')->name('site:overdue-findings')->withoutOverlapping();
