<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a lead (CRM-BR-18): converted leads are refused. Its open activities are
 * soft-deleted too, so no reminder or digest fires for a deleted lead.
 */
class DeleteLead
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Lead $lead): void
    {
        if ($lead->isConverted()) {
            throw ValidationException::withMessages(['lead' => __('Converted leads cannot be deleted.')]);
        }

        Gate::forUser($actor)->authorize('delete', $lead);

        DB::transaction(function () use ($lead): void {
            $lead->activities()->whereNull('completed_at')->get()->each->delete();
            $lead->delete();
        });
    }
}
