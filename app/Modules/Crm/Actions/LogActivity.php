<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Services\LeadFollowUps;
use App\Support\Facades\Settings;
use App\Support\Lookups\ActiveLookup;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Logs a done activity or schedules a planned one on a lead or customer (docs/03 §5.4).
 */
class LogActivity
{
    public const REMINDER_OPTIONS = [15, 30, 60, 1440];

    public function __construct(private LeadFollowUps $followUps) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Lead|Customer $subject, array $input): CrmActivity
    {
        Gate::forUser($actor)->authorize('crm.activities.create');
        Gate::forUser($actor)->authorize('view', $subject);

        $done = (bool) ($input['done'] ?? false);
        $type = ActivityType::query()->find($input['activity_type_id'] ?? null);

        /** @var array<string, mixed> $data */
        $data = Validator::make($input, [
            'activity_type_id' => ['required', new ActiveLookup('activity_types')],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'done' => ['boolean'],
            'scheduled_at' => $done ? ['nullable'] : ['required', 'date', 'after:now'],
            'reminder_minutes' => $done ? ['nullable'] : ['nullable', Rule::in(self::REMINDER_OPTIONS)],
            'duration_minutes' => [$done && $type?->requires_duration ? 'required' : 'nullable', 'integer', 'min:1', 'max:1440'],
            'outcome_id' => ['nullable', new ActiveLookup('activity_outcomes')],
            'owner_user_id' => ['nullable', 'integer', $this->ownerRule()],
            'next_follow_up' => ['nullable', 'array'],
            'next_follow_up.activity_type_id' => ['required_with:next_follow_up', new ActiveLookup('activity_types')],
            'next_follow_up.scheduled_at' => ['required_with:next_follow_up', 'date', 'after:now'],
        ], [], [
            'activity_type_id' => __('type'),
            'scheduled_at' => __('date and time'),
            'duration_minutes' => __('duration'),
            'owner_user_id' => __('owner'),
            'next_follow_up.scheduled_at' => __('next follow-up time'),
        ])->validate();

        return DB::transaction(function () use ($actor, $subject, $data, $done, $type): CrmActivity {
            $scheduledAt = $done ? null : Carbon::parse($data['scheduled_at']);

            $activity = $this->create($subject, [
                'activity_type_id' => $data['activity_type_id'],
                'title' => filled($data['title'] ?? null) ? $data['title'] : $type?->name,
                'description' => $data['description'] ?? null,
                'location_text' => $data['location_text'] ?? null,
                'scheduled_at' => $scheduledAt,
                'completed_at' => $done ? now() : null,
                'duration_minutes' => $done ? ($data['duration_minutes'] ?? null) : null,
                'outcome_id' => $done ? ($data['outcome_id'] ?? null) : null,
                'owner_user_id' => $data['owner_user_id'] ?? $actor->id,
                'reminder_at' => $scheduledAt !== null && isset($data['reminder_minutes']) ? $scheduledAt->copy()->subMinutes((int) $data['reminder_minutes']) : null,
            ]);

            if ($done && isset($data['next_follow_up'])) {
                $this->scheduleNext($subject, $activity->owner_user_id, $data['next_follow_up']);
            }

            if ($subject instanceof Lead) {
                if ($done) {
                    $subject->forceFill(['stale_notified_at' => null])->saveQuietly();
                }

                $this->followUps->refresh($subject);
            }

            return $activity;
        });
    }

    /**
     * Creates the open follow-up that "Schedule next follow-up" asks for (docs/03 §5.4).
     *
     * @param  array{activity_type_id: int|string, scheduled_at: string}  $next
     */
    public function scheduleNext(Lead|Customer $subject, int $ownerId, array $next): CrmActivity
    {
        $scheduledAt = Carbon::parse($next['scheduled_at']);
        $minutes = (int) Settings::get('crm.reminder_lead_minutes', 30);

        return $this->create($subject, [
            'activity_type_id' => (int) $next['activity_type_id'],
            'title' => ActivityType::query()->whereKey($next['activity_type_id'])->value('name'),
            'scheduled_at' => $scheduledAt,
            'owner_user_id' => $ownerId,
            'reminder_at' => $minutes > 0 ? $scheduledAt->copy()->subMinutes($minutes) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(Lead|Customer $subject, array $attributes): CrmActivity
    {
        $activity = new CrmActivity($attributes);
        $activity->subject()->associate($subject);
        $activity->save();

        return $activity;
    }

    private function ownerRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $owner = User::query()->where('is_active', true)->find($value);

            if ($owner === null || ! $owner->can('crm.activities.view')) {
                $fail(__('The selected owner cannot take CRM activities.'));
            }
        };
    }
}
