<?php

namespace App\Modules\Projects\Livewire\Projects;

use App\Models\User;
use App\Modules\Crm\Concerns\FiltersByLocation;
use App\Modules\Crm\Models\Customer;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Projects\Actions\ChangeProjectManager;
use App\Modules\Projects\Actions\DeleteProject;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ProjectType;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Projects list (docs/04 §5.1): preset chips, filters, bulk Change PM and export. The finance
 * columns arrive with 06 / 08 (spec P1).
 */
#[Title('Projects')]
class Index extends Component
{
    use FiltersByLocation, WithBulkActions, WithListing;

    public const PRESETS = ['mine', 'active', 'on_hold', 'approvals_pending', 'handed_over'];

    public string $bulkManagerId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Project::class);
    }

    public function applyPreset(string $preset): void
    {
        if (! in_array($preset, self::PRESETS, true)) {
            return;
        }

        $this->filters = match ($preset) {
            'mine' => ['mine' => '1'],
            'active' => ['status' => (string) ProjectStatus::idFor(ProjectStatus::IN_PROGRESS)],
            'on_hold' => ['status' => (string) ProjectStatus::idFor(ProjectStatus::ON_HOLD)],
            'approvals_pending' => ['approvals' => '1'],
            'handed_over' => ['status' => (string) ProjectStatus::idFor(ProjectStatus::HANDED_OVER)],
        };
        $this->updatedFilters();
    }

    /**
     * @return Builder<Project>
     */
    protected function listingQuery(): Builder
    {
        return Project::query()
            ->visibleTo($this->actor())
            ->with(['customer:id,name,customer_number', 'businessLine:id,name', 'type:id,name', 'status:id,name,color,code', 'phase:id,name', 'manager:id,full_name,employee_status_id', 'manager.status:id,is_active_employment'])
            ->withCount([
                'tasks as open_tasks_count' => fn (Builder $query) => $query->whereIn('tasks.task_status_id', $this->openTaskStatusIds()),
                'tasks as overdue_tasks_count' => fn (Builder $query) => $query->whereIn('tasks.task_status_id', $this->openTaskStatusIds())->whereDate('tasks.due_date', '<', today()),
                'approvals as pending_approvals_count' => fn (Builder $query) => $query->whereIn('project_approvals.approval_status_id', ApprovalStatus::query()->select('id')->where('is_final', false)),
            ])
            ->latest('projects.created_at')
            ->orderByDesc('projects.id');
    }

    /**
     * @return Builder<TaskStatus>
     */
    private function openTaskStatusIds(): Builder
    {
        return TaskStatus::query()->select('id')->where('is_done', false)->where('is_cancelled', false);
    }

    protected function searchColumns(): array
    {
        return ['projects.project_number', 'projects.name', 'projects.site_address'];
    }

    protected function sortColumns(): array
    {
        return ['number' => 'projects.project_number', 'name' => 'projects.name', 'start' => 'projects.start_date', 'end' => 'projects.expected_end_date', 'value' => 'projects.contract_value'];
    }

    /**
     * @param  Builder<Project>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        foreach (['status' => 'project_status_id', 'phase' => 'project_phase_id', 'business_line' => 'business_line_id', 'type' => 'project_type_id', 'pm' => 'project_manager_id', 'customer' => 'customer_id'] as $key => $column) {
            if ($this->filterString($key) !== '') {
                $query->where('projects.'.$column, (int) $this->filterString($key));
            }
        }

        if ($this->filterString('state') === 'open') {
            $query->open();
        } elseif ($this->filterString('state') === 'closed') {
            $query->whereNotIn('projects.project_status_id', ProjectStatus::query()->select('id')->where('is_closed', false));
        }

        if ($this->filterString('mine') === '1') {
            $employeeId = $this->actor()->employee_id ?? 0;
            $query->where(fn (Builder $query) => $query->where('projects.project_manager_id', $employeeId)
                ->orWhere('projects.supervisor_id', $employeeId)
                ->orWhere('projects.support_officer_id', $employeeId)
                ->orWhereHas('activeTeam', fn (Builder $query) => $query->where('employee_id', $employeeId)));
        }

        if ($this->filterString('location') !== '') {
            $this->whereInLocation($query, 'projects.location_id', (int) $this->filterString('location'));
        }

        if (($from = $this->validDate($this->filters['start_from'] ?? null)) !== null) {
            $query->whereDate('projects.start_date', '>=', $from);
        }

        if (($to = $this->validDate($this->filters['start_to'] ?? null)) !== null) {
            $query->whereDate('projects.start_date', '<=', $to);
        }

        if ($this->filterString('overdue') === '1') {
            $query->whereIn('projects.id', Task::query()->overdue()->whereNotNull('project_id')->select('project_id'));
        }

        if ($this->filterString('approvals') === '1') {
            $query->whereIn('projects.id', ProjectApproval::query()->pending()->select('project_id'));
        }

        match ($this->filterString('kind')) {
            'internal' => $query->whereIn('projects.project_type_id', ProjectType::query()->select('id')->where('is_internal', true)),
            'billable' => $query->whereIn('projects.project_type_id', ProjectType::query()->select('id')->where('is_internal', false)->where('is_billable', true)),
            default => null,
        };

        if ($this->filterString('pm_inactive') === '1') {
            $query->open()->whereIn('projects.project_manager_id', Employee::query()->select('id')
                ->whereIn('employee_status_id', EmployeeStatus::query()->select('id')->where('is_active_employment', false)));
        }
    }

    public function bulkChangeManager(ChangeProjectManager $changeManager): void
    {
        $this->authorize('projects.projects.update');

        if ($this->bulkManagerId === '') {
            return;
        }

        $changed = 0;
        $skipped = 0;

        foreach (Project::query()->visibleTo($this->actor())->whereIn('projects.id', array_map('intval', $this->selected))->get() as $project) {
            try {
                $changeManager->handle($this->actor(), $project, (int) $this->bulkManagerId);
                $changed++;
            } catch (ValidationException|AuthorizationException) {
                $skipped++;
            }
        }

        $this->selected = [];
        $this->bulkManagerId = '';
        $this->dispatch('toast', type: $skipped > 0 ? 'warning' : 'success', description: __(':changed changed, :skipped skipped.', ['changed' => $changed, 'skipped' => $skipped]));
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('projects.projects.export');

        return ListingExport::download('projects', $this->exportQuery(), [
            'Project #' => 'project_number',
            'Name' => 'name',
            'Customer' => 'customer.name',
            'Business line' => 'businessLine.name',
            'Type' => 'type.name',
            'Status' => 'status.name',
            'Phase' => 'phase.name',
            'PM' => 'manager.full_name',
            'Start' => 'start_date',
            'Expected end' => 'expected_end_date',
            'Contract value' => 'contract_value',
            'Open tasks' => 'open_tasks_count',
            'Overdue tasks' => 'overdue_tasks_count',
            'Approvals pending' => 'pending_approvals_count',
        ]);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('projects.projects.delete');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var Project $row */
        app(DeleteProject::class)->handle($this->actor(), $row);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.projects.projects.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'statuses' => ProjectStatus::query()->active()->ordered()->get(['id', 'name']),
            'managers' => Employee::query()->whereIn('id', Project::query()->visibleTo($this->actor())->whereNotNull('project_manager_id')->select('project_manager_id'))->orderBy('full_name')->get(['id', 'full_name']),
            'customers' => Customer::query()->whereIn('id', Project::query()->visibleTo($this->actor())->whereNotNull('customer_id')->select('customer_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
