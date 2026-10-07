<?php

namespace App\Modules\Projects;

use App\Models\User;
use App\Modules\Projects\Events\ApprovalStatusChanged;
use App\Modules\Projects\Events\ProjectPhaseChanged;
use App\Modules\Projects\Events\TaskCompleted;
use App\Modules\Projects\Listeners\EvaluateScheduleTriggers;
use App\Modules\Projects\Models\ApprovalAuthority;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\ApprovalType;
use App\Modules\Projects\Models\ContractStatus;
use App\Modules\Projects\Models\HoldReason;
use App\Modules\Projects\Models\PaymentSchedule;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Models\ProjectContract;
use App\Modules\Projects\Models\ProjectContractAmendment;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Modules\Projects\Models\ProjectPhase;
use App\Modules\Projects\Models\ProjectRole;
use App\Modules\Projects\Models\ProjectService;
use App\Modules\Projects\Models\ProjectServiceStatus;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ProjectType;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Models\ScheduleTrigger;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskComment;
use App\Modules\Projects\Models\TaskPriority;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskTemplate;
use App\Modules\Projects\Models\TaskTimeLog;
use App\Modules\Projects\Models\TaskType;
use App\Modules\Projects\Policies\ProjectApprovalPolicy;
use App\Modules\Projects\Policies\ProjectPolicy;
use App\Modules\Projects\Policies\TaskPolicy;
use App\Modules\Projects\Services\CompletionChecks\OpenApprovalsCheck;
use App\Modules\Projects\Services\CompletionChecks\OpenTasksCheck;
use App\Modules\Projects\Services\ProjectCompletionChecks;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ProjectsServiceProvider extends ServiceProvider
{
    /**
     * Register Projects services. Other modules add their completion checks (spec P2).
     */
    public function register(): void
    {
        $this->app->singleton(ProjectCompletionChecks::class, function (): ProjectCompletionChecks {
            $checks = new ProjectCompletionChecks;
            $checks->register(OpenTasksCheck::class);
            $checks->register(OpenApprovalsCheck::class);

            return $checks;
        });
    }

    /**
     * Bootstrap Projects services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/projects'));

        Relation::morphMap([
            'project_type' => ProjectType::class,
            'project_status' => ProjectStatus::class,
            'project_phase' => ProjectPhase::class,
            'project_role' => ProjectRole::class,
            'task_type' => TaskType::class,
            'task_status' => TaskStatus::class,
            'task_priority' => TaskPriority::class,
            'approval_authority' => ApprovalAuthority::class,
            'approval_type' => ApprovalType::class,
            'approval_status' => ApprovalStatus::class,
            'hold_reason' => HoldReason::class,
            'project_service_status' => ProjectServiceStatus::class,
            'contract_status' => ContractStatus::class,
            'schedule_trigger' => ScheduleTrigger::class,
            'schedule_status' => ScheduleStatus::class,
            'project' => Project::class,
            'project_service' => ProjectService::class,
            'project_contract' => ProjectContract::class,
            'project_contract_amendment' => ProjectContractAmendment::class,
            'payment_schedule' => PaymentSchedule::class,
            'project_employee' => ProjectEmployee::class,
            'task' => Task::class,
            'task_comment' => TaskComment::class,
            'task_time_log' => TaskTimeLog::class,
            'task_template' => TaskTemplate::class,
            'project_approval' => ProjectApproval::class,
        ]);

        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(ProjectApproval::class, ProjectApprovalPolicy::class);

        Gate::define('projects.projects.view', fn (User $user): bool => $user->hasPermission('projects.projects.view_own')
            || $user->hasPermission('projects.projects.view_all'));
        Gate::define('projects.tasks.view', fn (User $user): bool => $user->hasPermission('projects.tasks.view_own')
            || $user->hasPermission('projects.tasks.view_project')
            || $user->hasPermission('projects.tasks.view_all'));

        Event::listen([ProjectPhaseChanged::class, ApprovalStatusChanged::class, TaskCompleted::class], EvaluateScheduleTriggers::class);

        Livewire::addLocation(classNamespace: 'App\\Modules\\Projects\\Livewire');
    }
}
