<?php

namespace App\Modules\Projects\Livewire\Tasks;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Actions\ArchiveTask;
use App\Modules\Projects\Actions\ChangeTaskStatus;
use App\Modules\Projects\Actions\DeleteTask;
use App\Modules\Projects\Actions\ReassignTask;
use App\Modules\Projects\Actions\RescheduleTask;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * All task screens in one (docs/04 §5.5): the legacy My Task, Task Record, Completed and
 * Archived menus are preset chips; list and board views; bulk reassign, status, due date and archive.
 */
#[Title('Tasks')]
class Index extends Component
{
    use WithBulkActions, WithListing;

    public const PRESETS = ['mine', 'assigned_by_me', 'all', 'overdue', 'completed', 'archived'];

    public const BOARD_CARDS_PER_COLUMN = 50;

    #[Url(except: 'mine')]
    public string $preset = 'mine';

    #[Url(except: 'list')]
    public string $view = 'list';

    public string $bulkAssigneeId = '';

    public string $bulkStatusId = '';

    public string $bulkDueDate = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Task::class);

        if (! in_array($this->preset, self::PRESETS, true)) {
            $this->preset = 'mine';
        }
    }

    public function applyPreset(string $preset): void
    {
        if (in_array($preset, self::PRESETS, true)) {
            $this->preset = $preset;
            $this->updatedFilters();
        }
    }

    /**
     * @return Builder<Task>
     */
    protected function listingQuery(): Builder
    {
        return Task::query()
            ->visibleTo($this->actor())
            ->with([
                'project:id,project_number,name,customer_id,project_type_id', 'project.customer:id,name,customer_number', 'project.type:id,name',
                'status:id,name,color,code,is_done,is_cancelled', 'priority:id,name,color', 'type:id,name',
                'assignee:id,full_name', 'supportOfficer:id,full_name', 'assigner:id,name', 'completer:id,name',
            ])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('tasks.due_date')
            ->orderByDesc('tasks.id');
    }

    protected function searchColumns(): array
    {
        return ['tasks.task_number', 'tasks.title', 'tasks.file_number'];
    }

    protected function sortColumns(): array
    {
        return ['number' => 'tasks.task_number', 'entry' => 'tasks.created_at', 'title' => 'tasks.title', 'due' => 'tasks.due_date', 'completed' => 'tasks.completed_at'];
    }

    /**
     * @param  Builder<Task>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        $employeeId = $this->actor()->employee_id ?? 0;

        match ($this->preset) {
            'mine' => $query->open()->whereNull('tasks.archived_at')->where(fn (Builder $query) => $query->where('tasks.assignee_employee_id', $employeeId)->orWhere('tasks.support_officer_id', $employeeId)),
            'assigned_by_me' => $query->whereNull('tasks.archived_at')->where('tasks.assigned_by', $this->actor()->id),
            'overdue' => $query->overdue()->whereNull('tasks.archived_at'),
            'completed' => $query->whereNull('tasks.archived_at')->whereIn('tasks.task_status_id', TaskStatus::query()->select('id')->where('is_done', true)),
            'archived' => $query->whereNotNull('tasks.archived_at'),
            default => $query->whereNull('tasks.archived_at'),
        };

        foreach (['status' => 'task_status_id', 'assignee' => 'assignee_employee_id', 'project' => 'project_id', 'type' => 'task_type_id', 'phase' => 'project_phase_id', 'priority' => 'task_priority_id'] as $key => $column) {
            if ($this->filterString($key) !== '') {
                $query->where('tasks.'.$column, (int) $this->filterString($key));
            }
        }

        if (($from = $this->validDate($this->filters['due_from'] ?? null)) !== null) {
            $query->whereDate('tasks.due_date', '>=', $from);
        }

        if (($to = $this->validDate($this->filters['due_to'] ?? null)) !== null) {
            $query->whereDate('tasks.due_date', '<=', $to);
        }

        if ($this->filterString('important') === '1') {
            $query->where('tasks.is_important', true);
        }
    }

    public function moveTask(string $taskId, int $position, string $statusId, ChangeTaskStatus $changeTaskStatus): void
    {
        $task = Task::query()->visibleTo($this->actor())->find((int) $taskId);

        if ($task === null || (int) $statusId === $task->task_status_id) {
            return;
        }

        try {
            $warnings = $changeTaskStatus->handle($this->actor(), $task, (int) $statusId);
            $this->dispatch('toast', type: $warnings === [] ? 'success' : 'warning', description: $warnings === [] ? __('Task moved.') : implode(' ', $warnings));
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());
        } catch (AuthorizationException) {
            $this->dispatch('toast', type: 'error', description: __('You cannot change this task.'));
        }
    }

    public function bulkReassign(ReassignTask $reassignTask): void
    {
        $this->authorize('projects.tasks.assign');

        if ($this->bulkAssigneeId !== '') {
            $this->runBulk(fn (Task $task) => $reassignTask->handle($this->actor(), $task, (int) $this->bulkAssigneeId));
        }
    }

    public function bulkChangeStatus(ChangeTaskStatus $changeTaskStatus): void
    {
        if ($this->bulkStatusId !== '') {
            $this->runBulk(fn (Task $task) => $changeTaskStatus->handle($this->actor(), $task, (int) $this->bulkStatusId));
        }
    }

    public function bulkSetDueDate(RescheduleTask $rescheduleTask): void
    {
        if ($this->validDate($this->bulkDueDate) !== null) {
            $this->runBulk(fn (Task $task) => $rescheduleTask->handle($this->actor(), $task, $this->bulkDueDate));
        }
    }

    public function bulkArchive(ArchiveTask $archiveTask): void
    {
        $this->authorize('projects.tasks.archive');

        $this->runBulk(fn (Task $task) => $archiveTask->handle($this->actor(), $task, $this->preset !== 'archived'));
    }

    /**
     * Run a change on each selected visible task; refused rows are counted, not fatal.
     *
     * @param  Closure(Task): mixed  $change
     */
    private function runBulk(Closure $change): void
    {
        $changed = 0;
        $skipped = 0;

        foreach (Task::query()->visibleTo($this->actor())->whereIn('tasks.id', array_map('intval', $this->selected))->get() as $task) {
            try {
                $change($task);
                $changed++;
            } catch (ValidationException|AuthorizationException) {
                $skipped++;
            }
        }

        $this->selected = [];
        $this->reset('bulkAssigneeId', 'bulkStatusId', 'bulkDueDate');
        $this->dispatch('toast', type: $skipped > 0 ? 'warning' : 'success', description: __(':changed changed, :skipped skipped.', ['changed' => $changed, 'skipped' => $skipped]));
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('viewAny', Task::class);

        return ListingExport::download('tasks', $this->exportQuery(), [
            'Task #' => 'task_number',
            'File no.' => 'file_number',
            'Customer' => 'project.customer.name',
            'Entry date' => 'created_at',
            'Project' => 'project.project_number',
            'Project type' => 'project.type.name',
            'Title' => 'title',
            'Type' => 'type.name',
            'Assignee' => 'assignee.full_name',
            'Support officer' => 'supportOfficer.full_name',
            'Assigned by' => 'assigner.name',
            'Priority' => 'priority.name',
            'Due' => 'due_date',
            'Status' => 'status.name',
            'Completed' => 'completed_at',
            'Completed by' => 'completer.name',
        ]);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('projects.tasks.delete');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var Task $row */
        app(DeleteTask::class)->handle($this->actor(), $row);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        if (! in_array($this->view, ['list', 'board'], true)) {
            $this->view = 'list';
        }

        $statuses = TaskStatus::query()->active()->ordered()->get(['id', 'name', 'color', 'code']);

        return view('livewire.projects.tasks.index', [
            'rows' => $this->view === 'list' ? $this->paginatedRows() : null,
            'mobileRows' => $this->mobileRows(),
            'statuses' => $statuses,
            'columns' => $this->view === 'board' ? $statuses->map(fn (TaskStatus $status): array => [
                'status' => $status,
                'count' => (clone $this->filteredQuery())->where('tasks.task_status_id', $status->id)->count(),
                'tasks' => (clone $this->filteredQuery())->where('tasks.task_status_id', $status->id)->limit(self::BOARD_CARDS_PER_COLUMN)->get(),
            ]) : collect(),
            'employees' => Employee::query()->assignable()->orderBy('full_name')->get(['id', 'full_name']),
            'projects' => Project::query()->visibleTo($this->actor())->orderBy('project_number')->get(['id', 'project_number', 'name']),
        ]);
    }
}
