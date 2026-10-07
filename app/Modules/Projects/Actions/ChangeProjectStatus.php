<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Events\ProjectPhaseChanged;
use App\Modules\Projects\Events\ProjectStatusChanged;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Services\ProjectCompletionChecks;
use App\Modules\Projects\Services\ProjectTransitions;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Moves a project to another status and / or phase (docs/04 §5.4, §6.1, spec P9) with the
 * conditional fields, completion checks (PRJ-BR-08) and history.
 */
class ChangeProjectStatus
{
    public function __construct(private ProjectTransitions $transitions, private ProjectCompletionChecks $completionChecks) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, array $input): Project
    {
        Gate::forUser($actor)->authorize('changeStatus', $project);

        $data = Validator::make($input, [
            'project_status_id' => ['nullable', 'integer', 'exists:project_statuses,id'],
            'project_phase_id' => ['nullable', new ActiveLookup('project_phases', $project->project_phase_id)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'hold_reason_id' => ['nullable', new ActiveLookup('hold_reasons', $project->hold_reason_id)],
            'cancel_reason' => ['nullable', 'string', 'max:2000'],
            'handover_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ], [], ['project_status_id' => __('status'), 'project_phase_id' => __('phase'), 'hold_reason_id' => __('hold reason')])->validate();

        $from = $project->status;
        $to = ! empty($data['project_status_id']) ? ProjectStatus::query()->findOrFail((int) $data['project_status_id']) : $from;
        $phaseId = array_key_exists('project_phase_id', $input) ? ($data['project_phase_id'] !== null ? (int) $data['project_phase_id'] : null) : $project->project_phase_id;

        if ($to->id === $from->id && $phaseId === $project->project_phase_id) {
            throw ValidationException::withMessages(['project_status_id' => __('Choose a new status or phase.')]);
        }

        $attributes = $to->id !== $from->id ? $this->statusAttributes($actor, $project, $from, $to, $data) : [];
        $reason = $data['override_reason'] ?? $data['cancel_reason'] ?? $data['reason'] ?? null;

        return DB::transaction(fn (): Project => $this->apply($actor, $project, $to->id, $phaseId, $reason, $attributes));
    }

    /**
     * Write the change, its history row and events (no checks; caller's transaction). Used by
     * SignContract to move an enquiry to CONTRACTED.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function apply(User $actor, Project $project, int $toStatusId, ?int $toPhaseId, ?string $reason, array $attributes = []): Project
    {
        $fromStatusId = $project->project_status_id;
        $fromPhaseId = $project->project_phase_id;

        $project->forceFill([...$attributes, 'project_status_id' => $toStatusId, 'project_phase_id' => $toPhaseId, 'updated_by' => $actor->id])->save();

        $project->statusHistories()->create([
            'from_status_id' => $fromStatusId,
            'to_status_id' => $toStatusId,
            'from_phase_id' => $fromPhaseId,
            'to_phase_id' => $toPhaseId,
            'reason' => $reason,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);

        $project->unsetRelation('status')->unsetRelation('phase');

        if ($fromStatusId !== $toStatusId) {
            ProjectStatusChanged::dispatch($project, $fromStatusId, $toStatusId);
        }

        if ($fromPhaseId !== $toPhaseId) {
            ProjectPhaseChanged::dispatch($project, $fromPhaseId, $toPhaseId);
        }

        return $project;
    }

    /**
     * The checks and conditional fields of the target status.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function statusAttributes(User $actor, Project $project, ProjectStatus $from, ProjectStatus $to, array $data): array
    {
        if ($from->is_closed) {
            Gate::forUser($actor)->authorize('reopen', $project);

            if ($to->code !== ProjectStatus::IN_PROGRESS) {
                throw ValidationException::withMessages(['project_status_id' => __('A closed project can only be reopened to In Progress.')]);
            }

            if (trim((string) ($data['reason'] ?? '')) === '') {
                throw ValidationException::withMessages(['reason' => __('Say why the project is reopened.')]);
            }

            return ['actual_end_date' => null, 'cancel_reason' => null];
        }

        if (! $this->transitions->allows($from, $to)) {
            throw ValidationException::withMessages(['project_status_id' => __('A project cannot move from :from to :to.', ['from' => $from->name, 'to' => $to->name])]);
        }

        $attributes = $from->code === ProjectStatus::ON_HOLD ? ['hold_reason_id' => null] : [];

        return match ($to->code) {
            ProjectStatus::ON_HOLD => [...$attributes, 'hold_reason_id' => $this->required($data, 'hold_reason_id', __('Choose why the project is on hold.'))],
            ProjectStatus::CANCELLED => [...$attributes, 'cancel_reason' => $this->required($data, 'cancel_reason', __('Say why the project is cancelled.'))],
            ProjectStatus::HANDED_OVER => [...$attributes, 'handover_date' => $this->required($data, 'handover_date', __('Enter the handover date.'))],
            ProjectStatus::COMPLETED => [...$attributes, ...$this->completion($actor, $project, $data)],
            default => $attributes,
        };
    }

    /**
     * PRJ-BR-08: completion checks pass, or a view_all user with close overrides with a reason.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function completion(User $actor, Project $project, array $data): array
    {
        $end = $this->required($data, 'actual_end_date', __('Enter the actual end date.'));
        $problems = $this->completionChecks->run($project);

        if ($problems !== []) {
            $canOverride = Gate::forUser($actor)->allows('close', $project) && $actor->can('projects.projects.view_all');

            if (! $canOverride || trim((string) ($data['override_reason'] ?? '')) === '') {
                throw ValidationException::withMessages(['completion' => [
                    ...$problems,
                    $canOverride ? __('Give an override reason to complete anyway.') : __('Only management can complete a project with open items.'),
                ]]);
            }
        }

        return ['actual_end_date' => $end, 'completion_pct' => $problems === [] ? 100 : $project->completion_pct];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function required(array $data, string $key, string $message): mixed
    {
        if (($data[$key] ?? null) === null || $data[$key] === '') {
            throw ValidationException::withMessages([$key => $message]);
        }

        return $data[$key];
    }
}
