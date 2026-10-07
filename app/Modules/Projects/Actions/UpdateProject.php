<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Concerns\ValidatesProjectInput;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a project (docs/04 §5.2). The number and business line never change (PRJ-BR-01);
 * status and phase change through ChangeProjectStatus; services through SaveProjectServices,
 * which the form calls when it sends them (spec P5).
 */
class UpdateProject
{
    use ValidatesProjectInput;

    public function __construct(private SyncProjectPeople $syncPeople, private SaveProjectServices $saveServices) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, array $input): Project
    {
        Gate::forUser($actor)->authorize('update', $project);

        $data = $this->validateProject($this->normaliseProject($input), $project);

        return DB::transaction(function () use ($actor, $project, $data, $input): Project {
            $project->fill(Arr::only($data, $project->getFillable()));
            $project->forceFill([
                'customer_id' => $data['customer_id'] ?? null,
                'project_type_id' => (int) $data['project_type_id'],
                'updated_by' => $actor->id,
            ])->save();

            $this->syncPeople->handle($actor, $project, Arr::only($data, ['project_manager_id', 'supervisor_id', 'support_officer_id']));

            if (array_key_exists('services', $input)) {
                $this->saveServices->handle($actor, $project, $input['services']);
            }

            return $project->refresh();
        });
    }
}
