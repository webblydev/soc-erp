<?php

namespace App\Modules\Projects\Listeners;

use App\Modules\Crm\Events\CustomersMerging;
use App\Modules\Projects\Models\Project;
use App\Support\AuditTrail\AuditTrail;

/**
 * Moves the duplicate customer's projects to the survivor during a merge (CRM-AC-10, Projects
 * spec P22). Runs inside the merge transaction.
 */
class MoveMergedCustomerProjects
{
    public function handle(CustomersMerging $event): void
    {
        $ids = Project::withTrashed()->where('customer_id', $event->duplicate->id)->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        Project::withTrashed()->whereKey($ids)->update(['customer_id' => $event->survivor->id]);

        AuditTrail::record($event->survivor, 'merged_projects', null, ['from_customer' => $event->duplicate->customer_number, 'projects' => $ids]);
    }
}
