<?php

use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Projects\Livewire\Approvals;
use App\Modules\Projects\Livewire\Projects;
use App\Modules\Projects\Livewire\Tasks;
use App\Modules\Projects\Livewire\Templates;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Models\Task;
use Illuminate\Support\Facades\Route;

/*
| Projects routes (docs/04 §5). Each route repeats its permission or policy as `can:` middleware;
| Livewire actions and Actions authorize again. Fixed segments stay above projects/{project}.
*/

Route::middleware('app')->name('projects.')->group(function () {
    Route::prefix('projects')->group(function () {
        Route::livewire('/', Projects\Index::class)->middleware('can:viewAny,'.Project::class)->name('projects.index');
        Route::livewire('create', Projects\Form::class)->middleware('can:create,'.Project::class)->name('projects.create');

        Route::livewire('task-templates', Templates\Index::class)->middleware('can:projects.task_templates.manage')->name('templates.index');
        Route::livewire('task-templates/create', Templates\Form::class)->middleware('can:projects.task_templates.manage')->name('templates.create');
        Route::livewire('task-templates/{template}/edit', Templates\Form::class)->middleware('can:projects.task_templates.manage')->name('templates.edit');

        Route::livewire('approvals', Approvals\Index::class)->middleware('can:viewAny,'.ProjectApproval::class)->name('approvals.index');
        Route::livewire('approvals/create', Approvals\Form::class)->middleware('can:projects.approvals.manage')->name('approvals.create');
        Route::livewire('approvals/{approval}', Approvals\Show::class)->middleware('can:view,approval')->name('approvals.show');
        Route::livewire('approvals/{approval}/edit', Approvals\Form::class)->middleware('can:update,approval')->name('approvals.edit');

        Route::livewire('{project:project_number}', Projects\Show::class)->middleware('can:view,project')->name('projects.show');
        Route::livewire('{project:project_number}/edit', Projects\Form::class)->middleware('can:update,project')->name('projects.edit');
        Route::livewire('{project:project_number}/amendments/create', Projects\AmendmentForm::class)->middleware('can:manageContract,project')->name('projects.amendments.create');
        Route::livewire('{project:project_number}/amendments/{amendment}/edit', Projects\AmendmentForm::class)->middleware('can:manageContract,project')->name('projects.amendments.edit');
        Route::get('{project:project_number}/print', fn (Project $project) => view('projects.print', [
            'project' => $project->load(['customer', 'businessLine', 'type', 'status', 'phase', 'location', 'manager', 'supervisor', 'supportOfficer',
                'services.service', 'services.unit', 'services.status', 'contract.status', 'schedules.status', 'schedules.trigger', 'activeTeam.employee', 'activeTeam.role']),
            'company' => CompanyProfile::current(),
            'canSeeContract' => auth()->user()->can('viewContract', $project),
        ]))->middleware('can:view,project')->name('projects.print');
    });

    Route::livewire('tasks', Tasks\Index::class)->middleware('can:viewAny,'.Task::class)->name('tasks.index');
    Route::livewire('tasks/create', Tasks\Form::class)->middleware('can:create,'.Task::class)->name('tasks.create');
    Route::livewire('tasks/{task:task_number}', Tasks\Show::class)->middleware('can:view,task')->name('tasks.show');
    Route::livewire('tasks/{task:task_number}/edit', Tasks\Form::class)->middleware('can:update,task')->name('tasks.edit');
});
