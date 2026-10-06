<?php

namespace App\Modules\Crm\Jobs;

use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Notifications\FollowUpReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Sends follow-up reminders that are due (docs/03 §6.2 step 2). Each row is claimed with a
 * conditional update first, so overlapping or repeated runs never send twice. Activities whose
 * lead or customer is gone are skipped.
 */
class SendDueReminders implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        CrmActivity::query()
            ->whereNull('completed_at')
            ->whereNotNull('reminder_at')
            ->whereNull('reminder_sent_at')
            ->where('reminder_at', '<=', now())
            ->whereHasMorph('subject', [Lead::class, Customer::class])
            ->with(['owner', 'subject', 'type'])
            ->chunkById(100, function (Collection $activities): void {
                foreach ($activities as $activity) {
                    $claimed = CrmActivity::query()->whereKey($activity->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);

                    if ($claimed === 1 && $activity->owner->is_active) {
                        $activity->owner->notify(new FollowUpReminder($activity));
                    }
                }
            });
    }
}
