<?php

namespace App\Modules\Estimation\Services;

use App\Models\User;
use App\Modules\Estimation\Events\FindingAssigned;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Estimation\Notifications\FindingAssignedToYou;
use App\Modules\Hrm\Models\Employee;

/**
 * Tells a finding's responsible employee about it (site.findings.assigned, spec E15, E17).
 */
final class FindingNotifier
{
    public function assigned(SiteInspectionFinding $finding, ?User $actor): void
    {
        $employeeId = $finding->responsibleEmployeeId();

        if ($employeeId === null) {
            return;
        }

        FindingAssigned::dispatch($finding);

        $user = Employee::query()->find($employeeId)?->user;

        if ($user !== null && $user->is_active && ! $user->is($actor)) {
            $user->notify(new FindingAssignedToYou($finding));
        }
    }
}
