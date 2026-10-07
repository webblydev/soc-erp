<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskPriority;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskTemplate;
use App\Support\NumberSequenceService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates a project's tasks from a template (docs/04 §6.3, spec P17, PRJ-AC-01): dates from the
 * start date and item offsets, assignee from the team role (else the PM), checklist copied and
 * dependencies linked.
 */
class ApplyTaskTemplate
{
    public function __construct(private NumberSequenceService $numbers) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, TaskTemplate $template, ?string $startDate = null): int
    {
        Gate::forUser($actor)->authorize('createTask', $project);

        if (! $template->is_active) {
            throw ValidationException::withMessages(['task_template_id' => __('Choose an active template.')]);
        }

        if (! $project->isOpen()) {
            throw ValidationException::withMessages(['task_template_id' => __('Tasks cannot be added to a closed project.')]);
        }

        $start = $startDate !== null && $startDate !== '' ? Carbon::parse($startDate) : null;

        return DB::transaction(fn (): int => $this->apply($actor, $project, $template, $start));
    }

    /**
     * Create the tasks inside the caller's transaction; returns how many were created.
     */
    public function apply(User $actor, Project $project, TaskTemplate $template, ?CarbonInterface $start): int
    {
        $start = Carbon::parse($start ?? $project->start_date ?? today())->startOfDay();
        $todo = TaskStatus::idFor(TaskStatus::TODO);
        $normal = TaskPriority::idFor(TaskPriority::NORMAL);
        $assignable = Employee::query()->assignable()->pluck('id')->all();
        $team = $project->activeTeam()->orderBy('id')->get(['employee_id', 'project_role_id']);
        $taskIds = [];

        foreach ($template->items()->with('type')->get() as $item) {
            $itemStart = $start->copy()->addDays($item->offset_days_start);
            $member = $team->first(fn ($member): bool => $member->project_role_id === $item->project_role_id && in_array($member->employee_id, $assignable, true));
            $assignee = $member !== null ? $member->employee_id : $project->project_manager_id;

            $task = new Task([
                'project_phase_id' => $item->project_phase_id,
                'title' => $item->title,
                'task_priority_id' => $normal,
                'start_date' => $itemStart->toDateString(),
                'due_date' => $itemStart->copy()->addDays($item->duration_days)->toDateString(),
                'estimated_hours' => $item->estimated_hours ?? $item->type->default_estimated_hours,
                'sort_order' => $item->sort_order,
            ]);
            $task->forceFill([
                'task_number' => $this->numbers->next('task'),
                'project_id' => $project->id,
                'task_type_id' => $item->task_type_id,
                'task_status_id' => $todo,
                'assigned_by' => $actor->id,
                'assignee_employee_id' => $assignee,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            foreach ($item->checklist ?? [] as $index => $title) {
                $task->checklist()->create(['title' => $title, 'sort_order' => $index + 1]);
            }

            $taskIds[$item->id] = $task->id;

            if ($item->depends_on_item_id !== null && isset($taskIds[$item->depends_on_item_id])) {
                $task->predecessors()->attach($taskIds[$item->depends_on_item_id]);
            }
        }

        return count($taskIds);
    }
}
