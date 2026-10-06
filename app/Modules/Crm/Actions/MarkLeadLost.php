<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use App\Support\Facades\Settings;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Closes an open lead as lost, with a reason when crm.lost_reason_required (CRM-BR-08, CRM-AC-04).
 */
class MarkLeadLost
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Lead $lead, ?int $lostReasonId, ?string $note = null): Lead
    {
        Gate::forUser($actor)->authorize('changeStatus', $lead);
        LeadState::ensureOpen($lead);

        Validator::make(['lost_reason_id' => $lostReasonId, 'note' => $note], [
            'lost_reason_id' => [Settings::get('crm.lost_reason_required', true) ? 'required' : 'nullable', new ActiveLookup('lost_reasons')],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['lost_reason_id' => __('lost reason')])->validate();

        return DB::transaction(function () use ($actor, $lead, $lostReasonId, $note): Lead {
            LeadState::recordStatus($actor, $lead, LeadStatus::idFor(LeadStatus::LOST), $note, [
                'lost_reason_id' => $lostReasonId,
                'lost_note' => $note,
                'lost_at' => now(),
            ]);

            return $lead->refresh();
        });
    }
}
