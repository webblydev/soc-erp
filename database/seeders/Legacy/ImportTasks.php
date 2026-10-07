<?php

namespace Database\Seeders\Legacy;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskComment;
use App\Modules\Projects\Models\TaskPriority;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskType;
use App\Support\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * v1 tasks (tbl_task) → tasks on their projects, or general tasks (legacy seed spec L15,
 * docs/11 §4.5). Money columns are not copied. Imported rows are not touched again.
 */
class ImportTasks
{
    /** @var array<int, int> employee id → user id */
    private array $userOfEmployee = [];

    /** @var array<int, int|null> project id → created_by */
    private array $projectOwners = [];

    /** @var array<int, string> v1 type id → name */
    private array $typeNames = [];

    public function __construct(private NumberSequenceService $numbers) {}

    public function run(LegacyContext $context): int
    {
        $created = 0;
        $legacy = $context->legacy();

        $this->userOfEmployee = User::query()->whereNotNull('employee_id')->pluck('id', 'employee_id')->map(fn ($id): int => (int) $id)->all();
        $this->projectOwners = Project::withTrashed()->whereIn('id', array_values($context->projects))->pluck('created_by', 'id')->all();
        $this->typeNames = $legacy->table('tbl_type')->pluck('name', 'id')->map(fn ($name): string => trim((string) $name))->all();

        foreach ($legacy->table('tbl_task')->orderBy('id')->get() as $row) {
            if (Task::withTrashed()->where('legacy_task_ref', $row->id)->exists()) {
                continue;
            }

            DB::transaction(fn () => $this->import($context, $row));
            $created++;
        }

        return $created;
    }

    private function import(LegacyContext $context, object $row): void
    {
        $projectId = $context->projects[(int) $row->projectId] ?? null;
        $assignee = $context->employees[(int) $row->assign_to] ?? null;
        $support = $context->employees[(int) $row->support_id] ?? null;
        $assignedBy = ($projectId !== null ? $this->projectOwners[$projectId] ?? null : null) ?? $context->fallbackUserId();
        $start = LegacyMap::date($row->entry_date);
        $due = LegacyMap::date($row->deadline);
        $due = $due !== null && $start !== null && $due < $start ? $start : $due;
        $createdAt = Carbon::parse(($start ?? now()->toDateString()).' 09:00:00');
        $completedOn = LegacyMap::date($row->completed_date);
        $important = Str::lower(trim((string) $row->is_important)) === 'true';

        $statusCode = match ($row->status) {
            'c' => TaskStatus::DONE,
            'o' => TaskStatus::IN_PROGRESS,
            'a' => $completedOn !== null ? TaskStatus::DONE : TaskStatus::TODO,
            'd' => TaskStatus::CANCELLED,
            default => TaskStatus::TODO,
        };
        $done = $statusCode === TaskStatus::DONE;
        $completedAt = $done ? Carbon::parse(($completedOn ?? $due ?? $createdAt->toDateString()).' 17:00:00') : null;
        $completedBy = $done ? ($assignee !== null ? ($this->userOfEmployee[$assignee] ?? null) : null) ?? $context->fallbackUserId() : null;

        $task = new Task;
        $task->forceFill([
            'task_number' => $this->numbers->next('task', ['date' => $createdAt]),
            'project_id' => $projectId,
            'task_type_id' => $context->idFor(TaskType::class, (LegacyMap::TYPES[(int) $row->type_id] ?? LegacyMap::DEFAULT_TYPE)[2])
                ?? $context->idFor(TaskType::class, TaskType::OTHER),
            'title' => $this->title($row),
            'description' => $this->text($row->task_detail),
            'file_number' => ($file = $this->text($row->file_number)) !== null ? mb_substr($file, 0, 60) : null,
            'assigned_by' => $assignedBy,
            'assignee_employee_id' => $assignee,
            'support_officer_id' => $support !== $assignee ? $support : null,
            'task_priority_id' => $context->idFor(TaskPriority::class, $important ? 'HIGH' : TaskPriority::NORMAL),
            'is_important' => $important,
            'task_status_id' => $context->idFor(TaskStatus::class, $statusCode),
            'start_date' => $start,
            'due_date' => $due,
            'completed_at' => $completedAt,
            'completed_by' => $completedBy,
            'progress_pct' => $done ? 100 : 0,
            'archived_at' => $row->status === 'd' ? $createdAt : null,
            'sort_order' => (int) $row->id,
            'legacy_task_ref' => (int) $row->id,
            'created_by' => $assignedBy,
            'updated_by' => $assignedBy,
            'created_at' => $createdAt,
            'updated_at' => $completedAt ?? $createdAt,
        ])->save();

        $comment = $this->text($row->completed_by_comment);

        if ($comment !== null) {
            (new TaskComment(['body' => $comment]))->forceFill([
                'task_id' => $task->id,
                'user_id' => $completedBy ?? (($assignee !== null ? $this->userOfEmployee[$assignee] ?? null : null) ?? $context->fallbackUserId()),
                'created_at' => $completedAt ?? $createdAt,
                'updated_at' => $completedAt ?? $createdAt,
            ])->save();
        }
    }

    /**
     * The first line of the v1 detail, else "{type} — {project or client name}" (spec L15).
     */
    private function title(object $row): string
    {
        $firstLine = trim((string) Str::of((string) $row->task_detail)->explode("\n")->map(fn (string $line): string => trim($line))->first(fn (string $line): bool => $line !== ''));

        if ($firstLine !== '') {
            return Str::limit($firstLine, 120, '…');
        }

        $type = $this->typeNames[(int) $row->type_id] ?? 'Task';
        $about = $this->text($row->project_name) ?? $this->text($row->client_name);

        return Str::limit($about !== null ? "{$type} — {$about}" : $type, 120, '…');
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
