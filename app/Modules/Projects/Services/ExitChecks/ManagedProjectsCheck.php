<?php

namespace App\Modules\Projects\Services\ExitChecks;

use App\Modules\Hrm\Contracts\EmployeeExitCheck;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\ExitCheckItem;
use App\Modules\Projects\Models\Project;

/**
 * Open projects the leaving employee manages, each needing a new PM (HR-AC-03, PRJ-BR-10).
 */
class ManagedProjectsCheck implements EmployeeExitCheck
{
    public function check(Employee $employee): array
    {
        $items = [];

        foreach (Project::query()->open()->where('project_manager_id', $employee->id)->orderBy('project_number')->get(['id', 'project_number', 'name']) as $project) {
            $items[] = new ExitCheckItem(
                __('PM of :number :name: choose a new project manager', ['number' => $project->project_number, 'name' => $project->name]),
                route('projects.projects.edit', $project),
            );
        }

        return $items;
    }
}
