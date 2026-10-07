<?php

namespace App\Modules\Projects\Livewire\Projects;

use App\Models\User;
use App\Modules\Projects\Actions\ApplyTaskTemplate;
use App\Modules\Projects\Actions\ChangeTaskStatus;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskTemplate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tasks tab of the project page (docs/04 §5.3 tab 4): list, board by status and a start–due
 * timeline grouped by phase, plus "Apply template" (spec P17).
 */
class TasksTab extends Component
{
    public const VIEWS = ['list', 'board', 'timeline'];

    public Project $project;

    #[Url(as: 'task_view', except: 'list')]
    public string $view = 'list';

    public string $assignee = '';

    public string $phase = '';

    public string $status = '';

    public int|string|null $templateId = null;

    public string $templateStart = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->templateStart = (string) ($project->start_date?->toDateString() ?? today()->toDateString());
    }

    public function moveTask(string $taskId, int $position, string $statusId, ChangeTaskStatus $changeTaskStatus): void
    {
        $task = $this->tasks()->firstWhere('id', (int) $taskId);

        if ($task === null || (int) $statusId === $task->task_status_id) {
            return;
        }

        try {
            $warnings = $changeTaskStatus->handle($this->actor(), $task, (int) $statusId);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        } catch (AuthorizationException) {
            $this->dispatch('toast', type: 'error', description: __('You cannot change this task.'));

            return;
        }

        $this->dispatch('toast', type: $warnings === [] ? 'success' : 'warning', description: $warnings === [] ? __('Task moved.') : implode(' ', $warnings));
    }

    public function applyTemplate(ApplyTaskTemplate $applyTaskTemplate): void
    {
        $template = TaskTemplate::query()->find((int) $this->templateId);

        if ($template === null) {
            throw ValidationException::withMessages(['templateId' => __('Choose a template.')]);
        }

        try {
            $count = $applyTaskTemplate->handle($this->actor(), $this->project, $template, $this->templateStart);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['templateId' => collect($exception->errors())->flatten()->all()]);
        }

        $this->templateId = null;
        $this->dispatch('close-sheet-apply-template');
        $this->dispatch('toast', type: 'success', description: trans_choice(':count task created.|:count tasks created.', $count));
    }

    /**
     * The project's tasks the user can see, filtered.
     *
     * @return Collection<int, Task>
     */
    private function tasks(): Collection
    {
        return $this->project->tasks()->visibleTo($this->actor())
            ->whereNull('archived_at')
            ->with(['status:id,name,color,code,is_done,is_cancelled', 'priority:id,name,color', 'phase:id,name,sort_order', 'assignee:id,full_name', 'type:id,name'])
            ->withCount(['checklist', 'checklist as checklist_done_count' => fn ($query) => $query->where('is_done', true)])
            ->when($this->assignee !== '', fn ($query) => $query->where('assignee_employee_id', (int) $this->assignee))
            ->when($this->phase !== '', fn ($query) => $query->where('project_phase_id', (int) $this->phase))
            ->when($this->status !== '', fn ($query) => $query->where('task_status_id', (int) $this->status))
            ->orderBy('sort_order')->orderBy('due_date')->orderBy('id')
            ->get();
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'list';
        }

        $tasks = $this->tasks();
        $statuses = TaskStatus::query()->active()->ordered()->get(['id', 'name', 'color', 'code']);
        $dated = $tasks->filter(fn (Task $task): bool => $task->start_date !== null || $task->due_date !== null);
        $from = $dated->map(fn (Task $task) => $task->start_date ?? $task->due_date)->min();
        $to = $dated->map(fn (Task $task) => $task->due_date ?? $task->start_date)->max();

        return view('livewire.projects.projects.tasks-tab', [
            'tasks' => $tasks,
            'statuses' => $statuses,
            'columns' => $statuses->map(fn (TaskStatus $status): array => ['status' => $status, 'tasks' => $tasks->where('task_status_id', $status->id)->values()]),
            'phases' => $tasks->groupBy(fn (Task $task): string => $task->project_phase_id === null ? (string) __('No phase') : $task->phase->name)
                ->sortBy(fn ($group) => $group->first()->project_phase_id === null ? PHP_INT_MAX : $group->first()->phase->sort_order),
            'range' => $from !== null ? ['from' => $from, 'days' => max(1, (int) $from->diffInDays($to) + 1)] : null,
            'assignees' => $this->project->team()->with('employee:id,full_name')->get()->pluck('employee')->unique('id')->sortBy('full_name')->values(),
            'templates' => TaskTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canCreate' => $this->actor()->can('createTask', $this->project) && $this->project->isOpen(),
        ]);
    }
}
