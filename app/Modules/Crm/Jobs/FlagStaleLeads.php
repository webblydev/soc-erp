<?php

namespace App\Modules\Crm\Jobs;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Notifications\LeadStale;
use App\Support\Facades\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies the owner and team manager of stale leads, once per stale spell (CRM-BR-11, spec R13).
 * Logging a done activity clears stale_notified_at.
 */
class FlagStaleLeads implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $days = (int) Settings::get('crm.stale_lead_days', 14);

        Lead::query()->stale($days)->whereNull('stale_notified_at')->with(['assignee', 'team.manager'])
            ->chunkById(100, function (Collection $leads) use ($days): void {
                foreach ($leads as $lead) {
                    $recipients = collect([$lead->assignee, $lead->team?->manager])
                        ->filter(fn (?User $user): bool => $user?->is_active === true)
                        ->unique('id');

                    $lead->forceFill(['stale_notified_at' => now()])->saveQuietly();

                    Notification::send($recipients, new LeadStale($lead, $days));
                }
            });
    }
}
