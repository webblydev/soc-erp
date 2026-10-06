<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Concerns\AuthorizesActivities;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Services\LeadFollowUps;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Marks an open activity done, optionally scheduling the next follow-up (docs/03 §5.4).
 */
class CompleteActivity
{
    use AuthorizesActivities;

    public function __construct(private LogActivity $logActivity, private LeadFollowUps $followUps) {}

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

        /** @var array<string, mixed> $data */
        $data = Validator::make($input, [
            'outcome_id' => ['nullable', new ActiveLookup('activity_outcomes')],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => [$activity->type->requires_duration ? 'required' : 'nullable', 'integer', 'min:1', 'max:1440'],
            'next_follow_up' => ['nullable', 'array'],
            'next_follow_up.activity_type_id' => ['required_with:next_follow_up', new ActiveLookup('activity_types')],
            'next_follow_up.scheduled_at' => ['required_with:next_follow_up', 'date', 'after:now'],
        ], [], [
            'duration_minutes' => __('duration'),
            'next_follow_up.scheduled_at' => __('next follow-up time'),
        ])->validate();

        return DB::transaction(function () use ($activity, $data): CrmActivity {
            $activity->fill([
                'outcome_id' => $data['outcome_id'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'description' => filled($data['description'] ?? null) ? $data['description'] : $activity->description,
                'completed_at' => now(),
            ])->save();

            $subject = $activity->subject;

            if (isset($data['next_follow_up']) && ($subject instanceof Lead || $subject instanceof Customer)) {
                $this->logActivity->scheduleNext($subject, $activity->owner_user_id, $data['next_follow_up']);
            }

            if ($subject instanceof Lead) {
                $subject->forceFill(['stale_notified_at' => null])->saveQuietly();
                $this->followUps->refresh($subject);
            }

            return $activity;
        });
    }
}
