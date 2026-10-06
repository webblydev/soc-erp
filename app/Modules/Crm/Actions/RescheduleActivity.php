<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Concerns\AuthorizesActivities;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Services\LeadFollowUps;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Moves an open activity to a new time; its reminder is sent again (docs/03 §5.5).
 */
class RescheduleActivity
{
    use AuthorizesActivities;

    public function __construct(private LeadFollowUps $followUps) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, CrmActivity $activity, array $input): CrmActivity
    {
        $this->authorizeActivity($actor, $activity, 'crm.activities.update');

        if (! $activity->isOpen()) {
            throw ValidationException::withMessages(['activity' => __('This activity is already done.')]);
        }

        /** @var array{scheduled_at: string, reminder_minutes?: int|string|null} $data */
        $data = Validator::make($input, [
            'scheduled_at' => ['required', 'date', 'after:now'],
            'reminder_minutes' => ['nullable', Rule::in(LogActivity::REMINDER_OPTIONS)],
        ], [], ['scheduled_at' => __('date and time')])->validate();

        return DB::transaction(function () use ($activity, $data): CrmActivity {
            $scheduledAt = Carbon::parse($data['scheduled_at']);

            $activity->forceFill([
                'scheduled_at' => $scheduledAt,
                'reminder_at' => isset($data['reminder_minutes']) ? $scheduledAt->copy()->subMinutes((int) $data['reminder_minutes']) : null,
                'reminder_sent_at' => null,
            ])->save();

            if ($activity->subject instanceof Lead) {
                $this->followUps->refresh($activity->subject);
            }

            return $activity;
        });
    }
}
