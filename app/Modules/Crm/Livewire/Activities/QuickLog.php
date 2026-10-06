<?php

namespace App\Modules\Crm\Livewire\Activities;

use App\Models\User;
use App\Modules\Crm\Actions\CompleteActivity;
use App\Modules\Crm\Actions\LogActivity;
use App\Modules\Crm\Actions\RescheduleActivity;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Support\Facades\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The activity quick-log sheet (docs/03 §5.4): log a done activity, schedule a follow-up,
 * mark one done or reschedule it. Mounted once per page; opened with `crm-log-activity`.
 */
class QuickLog extends Component
{
    public const MODES = ['log', 'schedule', 'complete', 'reschedule'];

    public const SUBJECT_TYPES = ['lead', 'customer'];

    #[Locked]
    public string $subjectType = '';

    #[Locked]
    public int $subjectId = 0;

    #[Locked]
    public string $mode = 'log';

    #[Locked]
    public ?int $activityId = null;

    public int|string|null $activity_type_id = null;

    public string $title = '';

    public string $description = '';

    public bool $done = true;

    public string $scheduled_at = '';

    public string $duration_minutes = '';

    public int|string|null $outcome_id = null;

    public int|string|null $owner_user_id = null;

    public string $reminder_minutes = '';

    public string $location_text = '';

    public bool $scheduleNext = false;

    public int|string|null $next_type_id = null;

    public string $next_at = '';

    /** The title last filled in from the type, so a typed title is never overwritten. */
    public string $autoTitle = '';

    #[On('crm-log-activity')]
    public function open(string $subjectType, int $subjectId, string $mode = 'log', ?int $activity = null): void
    {
        $this->reset();
        $this->resetErrorBag();

        $subject = $this->findSubject($subjectType, $subjectId);
        $this->authorize('view', $subject);

        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->mode = in_array($mode, self::MODES, true) ? $mode : 'log';
        $this->done = $this->mode === 'log';
        $this->owner_user_id = $this->actor()->id;
        $this->reminder_minutes = $this->defaultReminder();

        if (in_array($this->mode, ['complete', 'reschedule'], true)) {
            $model = $this->findActivity($subject, (int) $activity);
            $this->activityId = $model->id;
            $this->activity_type_id = $model->activity_type_id;
            $this->scheduled_at = $model->scheduled_at?->format('Y-m-d\TH:i') ?? '';
            $this->done = $this->mode === 'complete';
        }

        $this->dispatch('open-sheet-quick-log');
    }

    public function updatedActivityTypeId(): void
    {
        if ($this->title !== '' && $this->title !== $this->autoTitle) {
            return;
        }

        $this->title = $this->autoTitle = (string) ActivityType::query()->whereKey($this->activity_type_id)->value('name');
    }

    public function save(LogActivity $logActivity, CompleteActivity $completeActivity, RescheduleActivity $rescheduleActivity): void
    {
        $this->resetErrorBag();

        $subject = $this->findSubject($this->subjectType, $this->subjectId);
        $this->authorize('view', $subject);

        $next = $this->scheduleNext && ($this->done || $this->mode === 'complete')
            ? ['activity_type_id' => $this->next_type_id, 'scheduled_at' => $this->next_at]
            : null;

        match ($this->mode) {
            'complete' => $completeActivity->handle($this->actor(), $this->findActivity($subject, (int) $this->activityId), $this->blankToNull([
                'outcome_id' => $this->outcome_id,
                'description' => $this->description,
                'duration_minutes' => $this->duration_minutes,
                'next_follow_up' => $next,
            ])),
            'reschedule' => $rescheduleActivity->handle($this->actor(), $this->findActivity($subject, (int) $this->activityId), $this->blankToNull([
                'scheduled_at' => $this->scheduled_at,
                'reminder_minutes' => $this->reminder_minutes,
            ])),
            default => $logActivity->handle($this->actor(), $subject, $this->blankToNull([
                'activity_type_id' => $this->activity_type_id,
                'title' => $this->title,
                'description' => $this->description,
                'done' => $this->done,
                'scheduled_at' => $this->done ? null : $this->scheduled_at,
                'reminder_minutes' => $this->done ? null : $this->reminder_minutes,
                'duration_minutes' => $this->done ? $this->duration_minutes : null,
                'outcome_id' => $this->done ? $this->outcome_id : null,
                'owner_user_id' => $this->owner_user_id,
                'location_text' => $this->location_text,
                'next_follow_up' => $next,
            ])),
        };

        $this->dispatch('close-sheet-quick-log');
        $this->dispatch('crm-activity-saved');
        $this->dispatch('toast', type: 'success', description: __('Activity saved.'));
    }

    /**
     * Empty form strings become null so the Actions see "not given".
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function blankToNull(array $values): array
    {
        return array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $values);
    }

    private function defaultReminder(): string
    {
        $minutes = (int) Settings::get('crm.reminder_lead_minutes', 30);

        return in_array($minutes, LogActivity::REMINDER_OPTIONS, true) ? (string) $minutes : '';
    }

    private function findSubject(string $type, int $id): Lead|Customer
    {
        abort_unless(in_array($type, self::SUBJECT_TYPES, true), 404);

        /** @var class-string<Lead|Customer> $class */
        $class = Relation::getMorphedModel($type);

        return $class::query()->findOrFail($id);
    }

    private function findActivity(Lead|Customer $subject, int $id): CrmActivity
    {
        return $subject->activities()->findOrFail($id);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $type = $this->activity_type_id ? ActivityType::query()->find($this->activity_type_id) : null;

        return view('livewire.crm.activities.quick-log', [
            'type' => $type,
            'reminderOptions' => [15 => __('15 min'), 30 => __('30 min'), 60 => __('1 h'), 1440 => __('1 day')],
            'owners' => $this->subjectType === '' ? collect() : User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->filter(fn (User $user): bool => $user->can('crm.activities.view'))->values(),
        ]);
    }
}
