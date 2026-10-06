<?php

namespace App\Modules\Crm\Actions;

use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\SalesTeam;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a sales team and its memberships. Teams that ever owned a lead are refused; deactivate them instead.
 */
class DeleteSalesTeam
{
    /**
     * @throws ValidationException
     */
    public function handle(SalesTeam $team): void
    {
        if (Lead::withTrashed()->where('sales_team_id', $team->id)->exists()) {
            throw ValidationException::withMessages(['team' => __('Teams with leads cannot be deleted. Deactivate them instead.')]);
        }

        $team->delete();
    }
}
