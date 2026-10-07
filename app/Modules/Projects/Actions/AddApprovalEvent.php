<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Foundation\Actions\UploadAttachment;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Events\ApprovalStatusChanged;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Models\ProjectApprovalEvent;
use App\Modules\Projects\Notifications\ApprovalStatusUpdated;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Records a step in an approval's trail and moves its status (docs/04 §5.9, spec P18). APPROVED
 * needs the approval date and a letter on file (PRJ-BR-16); final statuses are terminal.
 */
class AddApprovalEvent
{
    public function __construct(private UploadAttachment $uploadAttachment) {}

    /**
     * @param  array{approval_status_id?: mixed, event_date?: mixed, note?: mixed}  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, ProjectApproval $approval, array $input, ?UploadedFile $file = null): ProjectApprovalEvent
    {
        Gate::forUser($actor)->authorize('update', $approval);

        if ($approval->isFinal()) {
            throw ValidationException::withMessages(['approval_status_id' => __('This approval is closed (:status).', ['status' => $approval->status->name])]);
        }

        $data = Validator::make($input, [
            'approval_status_id' => ['required', 'integer', Rule::exists('approval_statuses', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:5000'],
        ], [], ['approval_status_id' => __('status'), 'event_date' => __('date')])->validate();

        $to = ApprovalStatus::query()->findOrFail((int) $data['approval_status_id']);

        if ($to->code === ApprovalStatus::APPROVED && $file === null && ! $approval->attachments()->exists()) {
            throw ValidationException::withMessages(['file' => __('Attach the approval letter.')]);
        }

        $fromStatusId = $approval->approval_status_id;

        $event = DB::transaction(function () use ($actor, $approval, $data, $file, $to, $fromStatusId): ProjectApprovalEvent {
            $attachment = $file !== null ? $this->uploadAttachment->handle($approval, $file, $actor, ['title' => $to->name]) : null;

            $event = $approval->events()->create([
                'approval_status_id' => $to->id,
                'event_date' => $data['event_date'],
                'note' => $data['note'] ?? null,
                'attachment_id' => $attachment?->id,
                'created_by' => $actor->id,
            ]);

            $approval->forceFill([
                'approval_status_id' => $to->id,
                'overdue_notified_at' => null,
                ...match ($to->code) {
                    ApprovalStatus::SUBMITTED, ApprovalStatus::RESUBMITTED => ['submitted_on' => $approval->submitted_on ?? $data['event_date']],
                    ApprovalStatus::APPROVED => ['approved_on' => $data['event_date']],
                    default => [],
                },
            ]);

            if ($approval->expected_on === null && $approval->submitted_on !== null && $approval->type->typical_days !== null) {
                $approval->expected_on = $approval->submitted_on->copy()->addDays($approval->type->typical_days);
            }

            $approval->save();
            $approval->unsetRelation('status');

            if ($fromStatusId !== $to->id) {
                ApprovalStatusChanged::dispatch($approval, $fromStatusId, $to->id);
            }

            return $event;
        });

        if ($fromStatusId !== $to->id) {
            Notification::send($this->recipients($actor, $approval), new ApprovalStatusUpdated($approval));
        }

        return $event;
    }

    /**
     * The PM's login and the customer's account manager (docs/04 §10).
     *
     * @return Collection<int, User>
     */
    private function recipients(User $actor, ProjectApproval $approval): Collection
    {
        $project = $approval->project;
        $ids = array_filter([
            $project->project_manager_id !== null ? Employee::query()->find($project->project_manager_id)?->user?->id : null,
            $project->customer?->account_manager_user_id,
        ]);

        return User::query()->whereIn('id', $ids)->where('is_active', true)->whereKeyNot($actor->id)->get();
    }
}
