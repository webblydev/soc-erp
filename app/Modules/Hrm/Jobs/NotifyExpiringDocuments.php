<?php

namespace App\Modules\Hrm\Jobs;

use App\Models\User;
use App\Modules\Hrm\Models\EmployeeDocument;
use App\Modules\Hrm\Notifications\DocumentExpiring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells HR and the employee about documents expiring within 30 days, once per expiry date
 * (HR-AC-05, spec H14). Saving a new expiry date clears expiry_notified_at.
 */
class NotifyExpiringDocuments implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $managers = User::query()->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->can('hrm.documents.manage'));

        EmployeeDocument::query()
            ->whereNull('expiry_notified_at')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', today()->addDays(EmployeeDocument::EXPIRING_DAYS))
            ->whereHas('employee', fn (Builder $query) => $query->assignable())
            ->with(['employee.user', 'type'])
            ->chunkById(100, function (Collection $documents) use ($managers): void {
                foreach ($documents as $document) {
                    $recipients = collect([...$managers->all(), $document->employee->user])
                        ->filter(fn (?User $user): bool => $user?->is_active === true)
                        ->unique('id');

                    $document->forceFill(['expiry_notified_at' => now()])->saveQuietly();

                    Notification::send($recipients, new DocumentExpiring($document));
                }
            });
    }
}
