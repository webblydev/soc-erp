<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\TaskTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Soft-deletes a template; tasks made from it stay (spec P24).
 */
class DeleteTaskTemplate
{
    public function handle(User $actor, TaskTemplate $template): void
    {
        Gate::forUser($actor)->authorize('projects.task_templates.manage');

        DB::transaction(fn () => $template->delete());
    }
}
