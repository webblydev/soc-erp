<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Projects\Concerns\ValidatesProjectInput;
use App\Modules\Projects\Events\ProjectCreated;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\TaskTemplate;
use App\Modules\Projects\Services\ServiceLines;
use App\Support\NumberSequenceService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Opens a project file (docs/04 §5.2): number from the business line prefix (PRJ-BR-01),
 * services and contract value (PRJ-BR-03), key people on the team (spec P6) and an optional
 * task template (spec P17). CRM conversion calls it with $viaConversion (spec P21).
 */
class CreateProject
{
    use ValidatesProjectInput;

    public function __construct(
        private NumberSequenceService $numbers,
        private ServiceLines $serviceLines,
        private SyncProjectPeople $syncPeople,
        private ApplyTaskTemplate $applyTemplate,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $input, bool $viaConversion = false): Project
    {
        if (! $viaConversion) {
            Gate::forUser($actor)->authorize('create', Project::class);
        }

        $data = $this->validateProject($this->normaliseProject($input), null);
        $extra = Validator::make($input, [
            'project_status_id' => ['nullable', Rule::exists('project_statuses', 'id')->where('is_closed', false)->whereNull('deleted_at')],
            'project_phase_id' => ['nullable', Rule::exists('project_phases', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'source_lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'task_template_id' => ['nullable', Rule::exists('task_templates', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ], [], ['project_status_id' => __('status'), 'project_phase_id' => __('phase'), 'task_template_id' => __('task template')])->validate();
        $lines = $this->serviceLines->validate($input['services'] ?? []);

        return DB::transaction(function () use ($actor, $data, $extra, $lines): Project {
            $line = BusinessLine::query()->findOrFail((int) $data['business_line_id']);

            $project = new Project(Arr::only($data, (new Project)->getFillable()));
            $project->forceFill([
                'project_number' => $this->numbers->next('project', ['bl_prefix' => $line->project_prefix]),
                'business_line_id' => $line->id,
                'customer_id' => $data['customer_id'] ?? null,
                'project_type_id' => (int) $data['project_type_id'],
                'project_status_id' => (int) ($extra['project_status_id'] ?? ProjectStatus::idFor(ProjectStatus::ENQUIRY)),
                'project_phase_id' => $extra['project_phase_id'] ?? null,
                'source_lead_id' => $extra['source_lead_id'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            $this->serviceLines->apply($project, $lines);
            $this->syncPeople->handle($actor, $project, Arr::only($data, ['project_manager_id', 'supervisor_id', 'support_officer_id']));

            $project->statusHistories()->create([
                'to_status_id' => $project->project_status_id,
                'to_phase_id' => $project->project_phase_id,
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            ProjectCreated::dispatch($project);

            if (! empty($extra['task_template_id'])) {
                $this->applyTemplate->apply($actor, $project, TaskTemplate::query()->findOrFail((int) $extra['task_template_id']), $project->start_date);
            }

            return $project->refresh();
        });
    }
}
