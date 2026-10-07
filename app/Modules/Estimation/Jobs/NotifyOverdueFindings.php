<?php

namespace App\Modules\Estimation\Jobs;

use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Estimation\Notifications\FindingOverdue;
use App\Modules\Hrm\Models\Employee;
use App\Support\Facades\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the responsible employee and the PM about overdue findings, once per overdue spell
 * (docs/05 §9, spec E17). Any status change clears overdue_notified_at; site.finding_overdue_notify
 * turns it off.
 */
class NotifyOverdueFindings implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        if (! (bool) Settings::get('site.finding_overdue_notify', true)) {
            return;
        }

        SiteInspectionFinding::query()->overdue()->whereNull('overdue_notified_at')
            ->with(['project.manager.user', 'inspection'])
            ->chunkById(100, function (Collection $findings): void {
                foreach ($findings as $finding) {
                    $responsible = $finding->responsibleEmployeeId() !== null ? Employee::query()->find($finding->responsibleEmployeeId())?->user : null;
                    $recipients = collect([$responsible, $finding->project->manager?->user])
                        ->filter(fn ($user): bool => $user?->is_active === true)
                        ->unique('id');

                    $finding->forceFill(['overdue_notified_at' => now()])->saveQuietly();

                    Notification::send($recipients, new FindingOverdue($finding));
                }
            });
    }
}
