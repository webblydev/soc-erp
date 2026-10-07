<?php

namespace Database\Factories\Projects;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskPriority;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    use ResolvesLookups;

    protected $model = Task::class;

    /**
     * is_done / is_cancelled per seeded status.
     */
    private const STATUS_FLAGS = [
        TaskStatus::DONE => ['is_done' => true],
        TaskStatus::CANCELLED => ['is_cancelled' => true],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_number' => 'T-'.fake()->unique()->numerify('#######'),
            'title' => fake()->sentence(4),
            'task_type_id' => fn (): int => self::lookupId(TaskType::class, TaskType::OTHER),
            'assigned_by' => User::factory(),
            'task_priority_id' => fn (): int => self::lookupId(TaskPriority::class, TaskPriority::NORMAL),
            'task_status_id' => fn (): int => self::statusId(TaskStatus::TODO),
        ];
    }

    public function onProject(Project $project): static
    {
        return $this->state(fn (): array => ['project_id' => $project->id]);
    }

    public function assignedTo(Employee $employee): static
    {
        return $this->state(fn (): array => ['assignee_employee_id' => $employee->id]);
    }

    public function withStatus(string $code): static
    {
        return $this->state(fn (): array => [
            'task_status_id' => self::statusId($code),
            ...($code === TaskStatus::DONE ? ['completed_at' => now(), 'progress_pct' => 100] : []),
        ]);
    }

    private static function statusId(string $code): int
    {
        return self::lookupId(TaskStatus::class, $code, self::STATUS_FLAGS[$code] ?? []);
    }
}
