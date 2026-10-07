<?php

namespace App\Modules\Projects\Livewire\Tasks;

use App\Models\User;
use App\Modules\Projects\Actions\AddTaskComment;
use App\Modules\Projects\Actions\ArchiveTask;
use App\Modules\Projects\Actions\ChangeTaskStatus;
use App\Modules\Projects\Actions\DeleteTask;
use App\Modules\Projects\Actions\DeleteTaskComment;
use App\Modules\Projects\Actions\LogTaskTime;
use App\Modules\Projects\Actions\ToggleChecklistItem;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Services\TaskTransitions;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Task detail (docs/04 §5.7): status buttons, checklist, comments with @mentions, time log,
 * sub-tasks, attachments and history (spec P15, P16).
 */
class Show extends Component
{
    public const TABS = ['details', 'comments', 'time', 'documents', 'history'];

    public Task $task;

    #[Url(except: 'details')]
    public string $tab = 'details';

    public ?int $pendingStatusId = null;

    public string $blockedReason = '';

    public string $comment = '';

    /** @var array{work_date: string, hours: string, note: string} */
    public array $timeForm = ['work_date' => '', 'hours' => '', 'note' => ''];

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);
        $this->task = $task;
        $this->timeForm['work_date'] = today()->toDateString();
    }

    /**
     * A status button: BLOCKED asks for a reason first; open checklist items ask for confirmation.
     */
    public function moveTo(int $statusId, ChangeTaskStatus $changeTaskStatus): void
    {
        $status = TaskStatus::query()->findOrFail($statusId);

        if ($status->code === TaskStatus::BLOCKED) {
            $this->pendingStatusId = $statusId;
            $this->dispatch('open-sheet-task-block');

            return;
        }

        $this->change($changeTaskStatus, $statusId, []);
    }

    public function block(ChangeTaskStatus $changeTaskStatus): void
    {
        $this->change($changeTaskStatus, (int) $this->pendingStatusId, ['blocked_reason' => $this->blockedReason]);
    }

    public function confirmComplete(ChangeTaskStatus $changeTaskStatus): void
    {
        $this->change($changeTaskStatus, (int) $this->pendingStatusId, ['confirm_open_checklist' => true]);
    }

    public function toggleItem(int $itemId, bool $done, ToggleChecklistItem $toggleChecklistItem): void
    {
        $toggleChecklistItem->handle($this->actor(), $this->task->checklist()->findOrFail($itemId), $done);
    }

    public function addComment(AddTaskComment $addTaskComment): void
    {
        $this->resetErrorBag();

        try {
            $addTaskComment->handle($this->actor(), $this->task, $this->comment);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['comment' => collect($exception->errors())->flatten()->all()]);
        }

        $this->comment = '';
    }

    public function deleteComment(int $commentId, DeleteTaskComment $deleteTaskComment): void
    {
        $deleteTaskComment->handle($this->actor(), $this->task->comments()->findOrFail($commentId));
    }

    public function logTime(LogTaskTime $logTaskTime): void
    {
        $this->resetErrorBag();

        try {
            $logTaskTime->handle($this->actor(), $this->task, $this->timeForm);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["timeForm.{$key}" => $messages])->all());
        }

        $this->task->refresh();
        $this->timeForm = ['work_date' => today()->toDateString(), 'hours' => '', 'note' => ''];
        $this->dispatch('close-sheet-task-time');
        $this->dispatch('toast', type: 'success', description: __('Time logged.'));
    }

    public function archive(ArchiveTask $archiveTask): void
    {
        $this->runAction(fn () => $archiveTask->handle($this->actor(), $this->task, $this->task->archived_at === null), $this->task->archived_at === null ? __('Task archived.') : __('Task restored from the archive.'));
    }

    public function deleteTask(DeleteTask $deleteTask): void
    {
        $deleteTask->handle($this->actor(), $this->task);

        session()->flash('success', __('Task deleted.'));
        $this->redirect($this->task->project !== null ? route('projects.projects.show', ['project' => $this->task->project, 'tab' => 'tasks']) : route('projects.tasks.index'), navigate: true);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function change(ChangeTaskStatus $changeTaskStatus, int $statusId, array $input): void
    {
        $this->resetErrorBag();

        try {
            $warnings = $changeTaskStatus->handle($this->actor(), $this->task, $statusId, $input);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            if (array_key_exists('confirm_open_checklist', $errors)) {
                $this->pendingStatusId = $statusId;
                $this->dispatch('open-sheet-task-confirm-complete');

                return;
            }

            if (array_key_exists('blocked_reason', $errors)) {
                throw ValidationException::withMessages(['blockedReason' => $errors['blocked_reason']]);
            }

            $this->dispatch('toast', type: 'error', description: (string) collect($errors)->flatten()->first());

            return;
        }

        $this->task->refresh();
        $this->pendingStatusId = null;
        $this->blockedReason = '';
        $this->dispatch('close-sheet-task-block');
        $this->dispatch('close-sheet-task-confirm-complete');
        $this->dispatch('toast', type: $warnings === [] ? 'success' : 'warning', description: $warnings === [] ? __('Status changed to :status.', ['status' => $this->task->status->name]) : implode(' ', $warnings));
    }

    private function runAction(\Closure $callback, string $message): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->task->refresh();
        $this->dispatch('toast', type: 'success', description: $message);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(TaskTransitions $transitions): View
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'details';
        }

        $this->task->load([
            'project.customer', 'parent', 'type', 'phase', 'status', 'priority', 'assignee', 'supportOfficer', 'reviewer', 'assigner', 'completer',
            'checklist.doer:id,name', 'watchers:id,name', 'predecessors.status',
            'subtasks' => fn ($query) => $query->with(['status', 'assignee:id,full_name']),
        ]);

        $actor = $this->actor();
        $canChange = $actor->can('changeStatus', $this->task);
        $canReview = $actor->can('review', $this->task);
        $inReview = $this->task->status->code === TaskStatus::REVIEW;

        return view('livewire.projects.tasks.show', [
            'targets' => $canChange
                ? $transitions->targets($this->task->status)->reject(fn (TaskStatus $to): bool => $inReview && ! $canReview && in_array($to->code, [TaskStatus::DONE, TaskStatus::IN_PROGRESS], true))->values()
                : collect(),
            'canChange' => $canChange,
            'comments' => $this->tab === 'comments' ? $this->task->comments()->with('user:id,name,username')->get() : collect(),
            'timeLogs' => $this->tab === 'time' ? $this->task->timeLogs()->with('employee:id,full_name')->get() : collect(),
            'unfinishedPredecessors' => $this->task->predecessors->reject(fn (Task $predecessor): bool => (bool) $predecessor->status->is_done)->values(),
        ])
            ->title($this->task->task_number)
            ->layoutData(['back' => $this->task->project !== null ? route('projects.projects.show', ['project' => $this->task->project, 'tab' => 'tasks']) : route('projects.tasks.index')]);
    }
}
