<?php

use App\Modules\Crm\Jobs\FlagStaleLeads;
use App\Modules\Crm\Jobs\SendDailyDigest;
use App\Modules\Crm\Jobs\SendDueReminders;
use App\Modules\Hrm\Jobs\NotifyExpiringDocuments;
use App\Modules\Hrm\Jobs\NotifyProbationEnding;
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
