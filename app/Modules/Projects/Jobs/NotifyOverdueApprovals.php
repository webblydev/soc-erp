<?php

namespace App\Modules\Projects\Jobs;

use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Notifications\ApprovalOverdue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the responsible employee and the PM about overdue approvals, once per overdue spell
 * (PRJ-BR-15, spec P19). Any new event clears overdue_notified_at.
 */
class NotifyOverdueApprovals implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        ProjectApproval::query()->overdue()->whereNull('overdue_notified_at')
            ->whereHas('project', fn ($query) => $query->open())
            ->with(['responsible.user', 'project.manager.user', 'type', 'authority'])
            ->chunkById(100, function (Collection $approvals): void {
                foreach ($approvals as $approval) {
                    $recipients = collect([$approval->responsible?->user, $approval->project->manager?->user])
                        ->filter(fn ($user): bool => $user?->is_active === true)
                        ->unique('id');

                    $approval->forceFill(['overdue_notified_at' => now()])->saveQuietly();

                    Notification::send($recipients, new ApprovalOverdue($approval));
                }
            });
    }
}
