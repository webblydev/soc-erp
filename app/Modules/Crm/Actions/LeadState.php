<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use Illuminate\Validation\ValidationException;

/**
 * Lead state checks and the status write shared by the status Actions and ConvertLead.
 */
final class LeadState
{
    /**
     * @throws ValidationException
     */
    public static function ensureOpen(Lead $lead): void
    {
        if ($lead->isConverted()) {
            throw ValidationException::withMessages(['lead' => __('Converted leads are read-only.')]);
        }

        if (! $lead->isOpen()) {
            throw ValidationException::withMessages(['lead' => __('This lead is closed.')]);
        }
    }

    /**
     * Sets the status and writes its history row (CRM-BR-06). Runs inside the caller's transaction.
     *
     * @param  array<string, mixed>  $extra  more lead columns to set with the status
     */
    public static function recordStatus(User $actor, Lead $lead, int $statusId, ?string $note, array $extra = []): void
    {
        $from = $lead->lead_status_id;

        $lead->forceFill(['lead_status_id' => $statusId, ...$extra])->save();

        $lead->statusHistories()->create([
            'from_status_id' => $from,
            'to_status_id' => $statusId,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'note' => $note,
        ]);
    }
}
