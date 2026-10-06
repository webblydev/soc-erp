<?php

namespace App\Modules\Hrm\Jobs;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeType;
use App\Modules\Hrm\Notifications\ProbationEnding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells HR and the manager when a probationer's confirmation date is within 15 days, once per
 * date (docs/09 §7, spec H14). Changing confirmation_date clears probation_notified_at.
 */
class NotifyProbationEnding implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const DAYS = 15;

    public function handle(): void
    {
        $hr = User::query()->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->can('hrm.employees.update'));

        Employee::query()
            ->assignable()
            ->where('employee_type_id', EmployeeType::idFor(EmployeeType::PROBATION))
            ->whereNull('probation_notified_at')
            ->whereNotNull('confirmation_date')
            ->whereDate('confirmation_date', '>=', today())
            ->whereDate('confirmation_date', '<=', today()->addDays(self::DAYS))
            ->with('manager.user')
            ->chunkById(100, function (Collection $employees) use ($hr): void {
                foreach ($employees as $employee) {
                    $recipients = collect([...$hr->all(), $employee->manager?->user])
                        ->filter(fn (?User $user): bool => $user?->is_active === true)
                        ->unique('id');

                    $employee->forceFill(['probation_notified_at' => now()])->saveQuietly();

                    Notification::send($recipients, new ProbationEnding($employee));
                }
            });
    }
}
