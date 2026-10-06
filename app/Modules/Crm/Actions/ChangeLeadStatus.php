<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Services\LeadFollowUps;
use App\Support\Facades\Settings;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Moves an open lead to another open status (docs/03 §5.6, §6.1, CRM-BR-06, CRM-BR-07).
 * Closed statuses go through MarkLeadLost and ConvertLead.
 */
class ChangeLeadStatus
{
    public function __construct(private LogActivity $logActivity, private LeadFollowUps $followUps) {}

    /**
     * @param  array{activity_type_id?: int|string, scheduled_at?: string}|null  $followUp
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Lead $lead, int $statusId, ?string $note = null, ?array $followUp = null): Lead
    {
        Gate::forUser($actor)->authorize('changeStatus', $lead);
        LeadState::ensureOpen($lead);

        $status = LeadStatus::query()->where('is_active', true)->where('is_closed', false)->find($statusId);

        if ($status === null || $status->id === $lead->lead_status_id) {
            throw ValidationException::withMessages(['lead_status_id' => __('Choose a different open status.')]);
        }

        Validator::make(['note' => $note, 'follow_up' => $followUp], [
            'note' => ['nullable', 'string', 'max:2000'],
            'follow_up' => ['nullable', 'array'],
            'follow_up.activity_type_id' => ['required_with:follow_up', new ActiveLookup('activity_types')],
            'follow_up.scheduled_at' => ['required_with:follow_up', 'date', 'after:now'],
        ], [], [
            'follow_up.activity_type_id' => __('follow-up type'),
            'follow_up.scheduled_at' => __('follow-up time'),
        ])->validate();

        if ($followUp === null && self::needsFollowUp($status) && ! $lead->activities()->whereNull('completed_at')->whereNotNull('scheduled_at')->exists()) {
            throw ValidationException::withMessages(['follow_up' => __(':status needs a scheduled follow-up.', ['status' => $status->name])]);
        }

        return DB::transaction(function () use ($actor, $lead, $status, $note, $followUp): Lead {
            LeadState::recordStatus($actor, $lead, $status->id, $note);

            if ($followUp !== null) {
                /** @var array{activity_type_id: int|string, scheduled_at: string} $followUp */
                $this->logActivity->scheduleNext($lead, $lead->assigned_to ?? $actor->id, $followUp);
                $this->followUps->refresh($lead);
            }

            return $lead->refresh();
        });
    }

    /**
     * Whether moving to the status needs an open follow-up (CRM-BR-07).
     */
    public static function needsFollowUp(LeadStatus $status): bool
    {
        return in_array($status->code, (array) Settings::get('crm.follow_up_required_on_status', []), true);
    }
}
