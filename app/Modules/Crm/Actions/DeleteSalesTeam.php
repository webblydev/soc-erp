<?php

namespace App\Modules\Crm\Actions;

use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\SalesTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a sales team and ends its active memberships, so the members can join another
 * team. Teams that ever owned a lead are refused; deactivate them instead.
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

        DB::transaction(function () use ($team): void {
            $team->members()->whereNull('left_on')->update(['left_on' => today()]);
            $team->delete();
        });
    }
}
