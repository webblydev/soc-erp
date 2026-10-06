<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\Lead;

/**
 * Keeps leads.next_follow_up_at and leads.last_activity_at in step with the lead's activities
 * (docs/03 §6.2 step 4, spec R12). Callers run it inside their transaction.
 */
class LeadFollowUps
{
    public function refresh(Lead $lead): void
    {
        $lead->forceFill([
            'next_follow_up_at' => $lead->activities()->whereNull('completed_at')->whereNotNull('scheduled_at')->min('scheduled_at'),
            'last_activity_at' => $lead->activities()->max('completed_at'),
        ])->saveQuietly();
    }
}
