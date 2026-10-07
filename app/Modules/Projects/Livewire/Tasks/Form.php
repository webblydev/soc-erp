<?php

namespace App\Modules\Projects\Livewire\Tasks;

use App\Models\User;
use App\Modules\Foundation\Concerns\SavesFromDetailModal;
use App\Modules\Projects\Actions\CreateTask;
use App\Modules\Projects\Actions\UpdateTask;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskPriority;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * New / edit task (docs/04 §5.6, spec P14). Opens in the detail modal on desktop.
 */
class Form extends Component
{
    use SavesFromDetailModal;

    /**
     * Fields copied to and from the task.
     */
    private const FIELDS = ['project_id', 'parent_id', 'task_type_id', 'project_phase_id', 'assignee_employee_id', 'support_officer_id', 'reviewer_employee_id', 'task_priority_id'];

    public ?Task $task = null;

    #[Url(as: 'project', except: '')]
    public string $projectNumber = '';

    #[Url(as: 'parent', except: '')]
    public string $parentNumber = '';

    public int|string|null $project_id = null;

    public int|string|null $parent_id = null;

    public int|string|null $task_type_id = null;

    public int|string|null $project_phase_id = null;

    public string $title = '';

    public string $description = '';

    public string $file_number = '';

    public int|string|null $assignee_employee_id = null;

    public int|string|null $support_officer_id = null;

    public int|string|null $reviewer_employee_id = null;

    public int|string|null $task_priority_id = null;

    public bool $is_important = false;

    public string $start_date = '';

    public string $due_date = '';

    public string $estimated_hours = '';

    /** @var list<array{id: int|null, title: string}> */
    public array $checklist = [];

    /** @var list<int|string> */
    public array $watcher_ids = [];

    public function mount(?Task $task = null): void
    {
        if ($task === null || ! $task->exists) {
            $this->authorize('create', Task::class);

            $this->task_priority_id = TaskPriority::query()->where('code', TaskPriority::NORMAL)->value('id');
            $this->assignee_employee_id = $this->actor()->employee_id;
            $this->start_date = today()->toDateString();

            $project = $this->projectNumber !== '' ? Project::query()->where('project_number', $this->projectNumber)->first() : null;

            if ($project !== null && $this->actor()->can('createTask', $project)) {
                $this->project_id = $project->id;
                $this->project_phase_id = $project->project_phase_id;
            }

            $parent = $this->parentNumber !== '' ? Task::query()->where('task_number', $this->parentNumber)->first() : null;

            if ($parent !== null && $this->actor()->can('view', $parent)) {
                $this->parent_id = $parent->id;
                $this->project_id = $parent->project_id;
            }

            return;
        }

        $this->authorize('update', $task);

        $this->task = $task;

        foreach (self::FIELDS as $field) {
            $this->{$field} = $task->getAttribute($field);
        }

        $this->title = $task->title;
        $this->description = (string) $task->description;
        $this->file_number = (string) $task->file_number;
        $this->is_important = (bool) $task->is_important;
        $this->start_date = (string) $task->start_date?->toDateString();
        $this->due_date = (string) $task->due_date?->toDateString();
        $this->estimated_hours = $task->estimated_hours !== null ? rtrim(rtrim($task->estimated_hours, '0'), '.') : '';
        $this->checklist = array_values($task->checklist()->get(['id', 'title'])->map(fn ($item): array => ['id' => $item->id, 'title' => $item->title])->all());
        $this->watcher_ids = array_values($task->watchers()->pluck('users.id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    public function updatedProjectId(): void
    {
        $this->parent_id = null;
    }

    public function addChecklistItem(): void
    {
        $this->checklist = [...$this->checklist, ['id' => null, 'title' => '']];
    }

    public function removeChecklistItem(int $index): void
    {
        $this->checklist = array_values(array_filter($this->checklist, fn (int $key): bool => $key !== $index, ARRAY_FILTER_USE_KEY));
    }

    public function save(CreateTask $createTask, UpdateTask $updateTask): void
    {
        $this->task === null ? $this->authorize('create', Task::class) : $this->authorize('update', $this->task);
        $this->resetErrorBag();

        $input = $this->only([...self::FIELDS, 'title', 'description', 'file_number', 'is_important', 'start_date', 'due_date', 'estimated_hours', 'checklist', 'watcher_ids']);

        $task = $this->task === null ? $createTask->handle($this->actor(), $input) : $updateTask->handle($this->actor(), $this->task, $input);

        $this->redirectAfterSave($this->task === null ? __('Task :number created.', ['number' => $task->task_number]) : __('Task saved.'), 'projects.tasks.show', $task);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $projectId = is_numeric($this->project_id) ? (int) $this->project_id : null;

        return view('livewire.projects.tasks.form', [
            'projects' => Project::query()->visibleTo($this->actor())
                ->where(fn ($query) => $query->open()->when($projectId !== null, fn ($query) => $query->orWhere('projects.id', $projectId)))
                ->orderBy('project_number')->get(['id', 'project_number', 'name']),
            'parents' => $projectId !== null
                ? Task::query()->where('project_id', $projectId)->whereNull('parent_id')->when($this->task, fn ($query) => $query->whereKeyNot($this->task->id))->orderBy('sort_order')->get(['id', 'task_number', 'title'])
                : collect(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canAssign' => $this->actor()->can('projects.tasks.assign'),
        ])
            ->title($this->task === null ? __('New task') : __('Edit task'))
            ->layoutData(['back' => $this->task === null ? route('projects.tasks.index') : route('projects.tasks.show', $this->task), 'bottomNav' => false]);
    }
}
