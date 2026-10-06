<?php

namespace App\Modules\Crm\Jobs;

use App\Models\User;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Crm\Notifications\DailyDigest;
use App\Support\Facades\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

/**
 * Sends the daily follow-up digest (docs/03 §9, spec R13). Scheduled every minute; it acts once
 * per day after notifications.daily_digest_time, so a changed time applies without a deploy.
 */
class SendDailyDigest implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $time = (string) Settings::get('notifications.daily_digest_time', '09:00');

        if (now()->format('H:i') < $time || ! Cache::add('crm:daily-digest:'.today()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        User::query()->where('is_active', true)->each(function (User $user): void {
            if (! $user->can('crm.activities.view')) {
                return;
            }

            $open = CrmActivity::query()->where('owner_user_id', $user->id)->whereNull('completed_at')
                ->whereNotNull('scheduled_at')->where('scheduled_at', '<=', today()->endOfDay())
                ->whereHasMorph('subject', [Lead::class, Customer::class])
                ->with(['subject', 'type'])->orderBy('scheduled_at')->get();

            [$overdue, $today] = $open->partition(fn (CrmActivity $activity): bool => (bool) $activity->scheduled_at?->isPast());

            $teamOverdue = $this->teamOverdue($user);

            if ($open->isEmpty() && $teamOverdue === []) {
                return;
            }

            $user->notify(new DailyDigest($today->values(), $overdue->values(), $teamOverdue));
        });
    }

    /**
     * Overdue counts of the active members of the teams the user manages, by member name.
     *
     * @return array<string, int>
     */
    private function teamOverdue(User $manager): array
    {
        $memberIds = array_values(array_diff(SalesTeam::managedMemberIds($manager), [$manager->id]));

        if ($memberIds === []) {
            return [];
        }

        $counts = CrmActivity::query()->whereIn('owner_user_id', $memberIds)->overdue()
            ->whereHasMorph('subject', [Lead::class, Customer::class])
            ->selectRaw('owner_user_id, COUNT(*) as total')->groupBy('owner_user_id')->pluck('total', 'owner_user_id');

        return User::query()->whereKey($counts->keys())->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->mapWithKeys(fn (User $member): array => [$member->name => (int) $counts[$member->id]])
            ->all();
    }
}
