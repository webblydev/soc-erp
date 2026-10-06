<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Moves a lost lead back to the open status it had before LOST (spec R11).
 */
class ReopenLead
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Lead $lead, string $reason): Lead
    {
        Gate::forUser($actor)->authorize('changeStatus', $lead);

        $lostId = LeadStatus::idFor(LeadStatus::LOST);

        if ($lead->isConverted() || $lead->lead_status_id !== $lostId) {
            throw ValidationException::withMessages(['lead' => __('Only lost leads can be reopened.')]);
        }

        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:255']])->validate();

        $previous = $lead->statusHistories()->where('to_status_id', $lostId)->value('from_status_id');
        $target = LeadStatus::query()->whereKey($previous)->where('is_active', true)->where('is_closed', false)->value('id')
            ?? LeadStatus::idFor(LeadStatus::NEW);

        return DB::transaction(function () use ($actor, $lead, $target, $reason): Lead {
            LeadState::recordStatus($actor, $lead, (int) $target, $reason, ['lost_reason_id' => null, 'lost_note' => null, 'lost_at' => null]);

            return $lead->refresh();
        });
    }
}
