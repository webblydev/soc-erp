<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Hrm\Rules\AssignableEmployee;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\ApprovalType;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits an approval (docs/04 §3.10, spec P18). A new approval starts PREPARING with its
 * type's default checklist; expected_on defaults to submitted_on + typical days (PRJ-BR-15).
 */
class SaveApproval
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, array $input, ?ProjectApproval $approval = null): ProjectApproval
    {
        Gate::forUser($actor)->authorize('manageApprovals', $project);

        if ($approval !== null && $approval->project_id !== $project->id) {
            throw ValidationException::withMessages(['approval' => __('This approval belongs to another project.')]);
        }

        $input = array_map(fn (mixed $value): mixed => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $input);

        if (is_string($input['authority_fee'] ?? null)) {
            $input['authority_fee'] = str_replace(',', '', $input['authority_fee']);
        }

        $data = Validator::make($input, [
            'approval_authority_id' => ['required', new ActiveLookup('approval_authorities', $approval?->approval_authority_id)],
            'approval_type_id' => ['required', new ActiveLookup('approval_types', $approval?->approval_type_id)],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'responsible_employee_id' => ['nullable', new AssignableEmployee($approval?->responsible_employee_id)],
            'prepared_on' => ['nullable', 'date'],
            'submitted_on' => ['nullable', 'date'],
            'expected_on' => ['nullable', 'date', 'after_or_equal:submitted_on'],
            'valid_until' => ['nullable', 'date'],
            'authority_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], ['approval_authority_id' => __('authority'), 'approval_type_id' => __('approval type'), 'responsible_employee_id' => __('responsible')])->validate();

        $type = ApprovalType::query()->findOrFail((int) $data['approval_type_id']);

        if (empty($data['expected_on']) && ! empty($data['submitted_on']) && $type->typical_days !== null) {
            $data['expected_on'] = Carbon::parse($data['submitted_on'])->addDays($type->typical_days)->toDateString();
        }

        return DB::transaction(function () use ($project, $approval, $data, $type): ProjectApproval {
            $isNew = $approval === null;
            $approval ??= new ProjectApproval;
            $approval->fill($data);

            if ($isNew) {
                $approval->forceFill(['project_id' => $project->id, 'approval_status_id' => ApprovalStatus::idFor(ApprovalStatus::PREPARING)]);
            }

            if ($approval->isDirty('expected_on')) {
                $approval->overdue_notified_at = null;
            }

            $approval->save();

            if ($isNew) {
                foreach (array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $type->default_checklist) ?: []))) as $index => $title) {
                    $approval->checklist()->create(['title' => $title, 'sort_order' => $index + 1]);
                }
            }

            return $approval;
        });
    }
}
