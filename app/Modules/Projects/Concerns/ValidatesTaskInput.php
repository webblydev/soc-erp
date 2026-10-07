<?php

namespace App\Modules\Projects\Concerns;

use App\Models\User;
use App\Modules\Hrm\Rules\AssignableEmployee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Task field rules shared by CreateTask and UpdateTask (PRJ-BR-11, spec P14).
 */
trait ValidatesTaskInput
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normaliseTask(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        $input['is_important'] = (bool) ($input['is_important'] ?? false);
        $input['checklist'] = array_values(array_filter(array_map(fn (mixed $item): ?array => is_array($item) && is_string($item['title'] ?? null) && trim($item['title']) !== ''
            ? ['id' => is_numeric($item['id'] ?? null) ? (int) $item['id'] : null, 'title' => trim($item['title'])]
            : null, is_array($input['checklist'] ?? null) ? $input['checklist'] : [])));
        $input['watcher_ids'] = array_values(array_unique(array_map('intval', array_filter(is_array($input['watcher_ids'] ?? null) ? $input['watcher_ids'] : [], 'is_numeric'))));

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateTask(User $actor, array $input, ?Task $task): array
    {
        $validator = Validator::make($input, [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'parent_id' => ['nullable', 'integer'],
            'task_type_id' => ['required', new ActiveLookup('task_types', $task?->task_type_id)],
            'project_phase_id' => ['nullable', new ActiveLookup('project_phases', $task?->project_phase_id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'file_number' => ['nullable', 'string', 'max:60'],
            'assignee_employee_id' => ['nullable', new AssignableEmployee($task?->assignee_employee_id)],
            'support_officer_id' => ['nullable', new AssignableEmployee($task?->support_officer_id)],
            'reviewer_employee_id' => ['nullable', new AssignableEmployee($task?->reviewer_employee_id)],
            'task_priority_id' => ['required', new ActiveLookup('task_priorities', $task?->task_priority_id)],
            'is_important' => ['boolean'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'checklist' => ['array', 'max:50'],
            'checklist.*.id' => ['nullable', 'integer'],
            'checklist.*.title' => ['required', 'string', 'max:255'],
            'watcher_ids' => ['array', 'max:30'],
            'watcher_ids.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
        ], [
            'due_date.after_or_equal' => __('The due date cannot be before the start date.'),
        ], [
            'task_type_id' => __('type'), 'project_phase_id' => __('phase'), 'task_priority_id' => __('priority'),
            'assignee_employee_id' => __('assignee'), 'support_officer_id' => __('support officer'), 'reviewer_employee_id' => __('reviewer'),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($actor, $input, $task): void {
            $this->validateProjectAndParent($validator, $actor, $input, $task);
            $this->validateAssignee($validator, $actor, $input, $task);
        });

        /** @var array<string, mixed> */
        return $validator->validate();
    }

    /**
     * A project task needs a visible, open project; sub-tasks go one level deep on the same project.
     *
     * @param  array<string, mixed>  $input
     */
    private function validateProjectAndParent(ValidatorInstance $validator, User $actor, array $input, ?Task $task): void
    {
        $projectId = is_numeric($input['project_id'] ?? null) ? (int) $input['project_id'] : null;

        if ($projectId !== null && $projectId !== $task?->project_id) {
            $project = Project::query()->with('status')->find($projectId);

            if ($project === null || ! Gate::forUser($actor)->allows('createTask', $project)) {
                $validator->errors()->add('project_id', __('You cannot add tasks to this project.'));
            } elseif (! $project->isOpen()) {
                $validator->errors()->add('project_id', __('Tasks cannot be added to a closed project.'));
            }
        }

        if (empty($input['parent_id'])) {
            return;
        }

        $parent = Task::query()->find((int) $input['parent_id']);

        if ($parent === null || $parent->parent_id !== null || $parent->project_id !== $projectId || $parent->id === $task?->id) {
            $validator->errors()->add('parent_id', __('A sub-task needs a main task of the same project (one level only).'));
        } elseif ($task !== null && $task->subtasks()->exists()) {
            $validator->errors()->add('parent_id', __('A task with sub-tasks cannot become a sub-task.'));
        }
    }

    /**
     * Without assign permission the assignee can only be the actor's own employee (spec P14).
     *
     * @param  array<string, mixed>  $input
     */
    private function validateAssignee(ValidatorInstance $validator, User $actor, array $input, ?Task $task): void
    {
        $assignee = is_numeric($input['assignee_employee_id'] ?? null) ? (int) $input['assignee_employee_id'] : null;

        if ($assignee === null || $assignee === $task?->assignee_employee_id || $assignee === $actor->employee_id) {
            return;
        }

        if (! $actor->can('projects.tasks.assign')) {
            $validator->errors()->add('assignee_employee_id', __('You can only assign tasks to yourself.'));
        }
    }

    /**
     * Replace the checklist items (keeping done flags of kept items) and watchers.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncTaskExtras(Task $task, array $data): void
    {
        /** @var list<array{id: int|null, title: string}> $items */
        $items = $data['checklist'] ?? [];
        $keep = array_values(array_filter(array_column($items, 'id')));
        $task->checklist()->whereNotIn('id', $keep)->delete();

        foreach ($items as $index => $item) {
            $existing = $item['id'] !== null ? $task->checklist()->find($item['id']) : null;

            $existing !== null
                ? $existing->fill(['title' => $item['title'], 'sort_order' => $index + 1])->save()
                : $task->checklist()->create(['title' => $item['title'], 'sort_order' => $index + 1]);
        }

        $task->watchers()->sync($data['watcher_ids'] ?? []);
    }
}
