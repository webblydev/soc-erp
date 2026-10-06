<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Concerns\AuthorizesActivities;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Services\LeadFollowUps;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes an activity and refreshes its lead's follow-up columns (spec R12).
 */
class DeleteActivity
{
    use AuthorizesActivities;

    public function __construct(private LeadFollowUps $followUps) {}

    public function handle(User $actor, CrmActivity $activity): void
    {
        $this->authorizeActivity($actor, $activity, 'crm.activities.delete');

        DB::transaction(function () use ($activity): void {
            $activity->delete();

            if ($activity->subject instanceof Lead) {
                $this->followUps->refresh($activity->subject);
            }
        });
    }
}
