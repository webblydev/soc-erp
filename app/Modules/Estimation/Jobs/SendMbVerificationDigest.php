<?php

namespace App\Modules\Estimation\Jobs;

use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Notifications\MeasurementsAwaitingVerification;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

/**
 * Tells each PM how many MB entries on their projects have waited more than a day for
 * verification (docs/05 §9, spec E17, E18). A cache key per date stops a rerun from sending twice.
 */
class SendMbVerificationDigest implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        if (! Cache::add('site:mb-digest:'.today()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        $counts = MeasurementEntry::query()
            ->join('projects', 'projects.id', '=', 'measurement_entries.project_id')
            ->where('measurement_entries.mb_status_id', MbStatus::idFor(MbStatus::RECORDED))
            ->where('measurement_entries.created_at', '<', now()->subDay())
            ->whereNotNull('projects.project_manager_id')
            ->groupBy('projects.project_manager_id')
            ->selectRaw('projects.project_manager_id as manager_id, count(*) as waiting')
            ->pluck('waiting', 'manager_id');

        foreach (Employee::query()->with('user')->whereKey($counts->keys())->get() as $manager) {
            if ($manager->user?->is_active === true) {
                $manager->user->notify(new MeasurementsAwaitingVerification((int) $counts[$manager->id]));
            }
        }
    }
}
