<?php

namespace App\Modules\Projects\Livewire\Approvals;

use App\Models\User;
use App\Modules\Projects\Actions\AddApprovalEvent;
use App\Modules\Projects\Actions\ToggleApprovalChecklistItem;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\ProjectApproval;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Approval card (docs/04 §5.9): dates against the typical duration, event trail, checklist and
 * the Add event sheet (spec P18).
 */
class Show extends Component
{
    use WithFileUploads;

    public ProjectApproval $approval;

    /** @var array{approval_status_id: int|string|null, event_date: string, note: string} */
    public array $eventForm = ['approval_status_id' => null, 'event_date' => '', 'note' => ''];

    /** @var UploadedFile|null */
    public $file = null;

    public function mount(ProjectApproval $approval): void
    {
        $this->authorize('view', $approval);
        $this->approval = $approval;
        $this->eventForm['event_date'] = today()->toDateString();
    }

    public function addEvent(AddApprovalEvent $addApprovalEvent): void
    {
        $this->resetErrorBag();

        try {
            $addApprovalEvent->handle($this->actor(), $this->approval, $this->eventForm, $this->file instanceof UploadedFile ? $this->file : null);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [$key === 'file' ? 'file' : "eventForm.{$key}" => $messages])->all());
        }

        $this->approval->refresh();
        $this->eventForm = ['approval_status_id' => null, 'event_date' => today()->toDateString(), 'note' => ''];
        $this->file = null;
        $this->dispatch('close-sheet-approval-event');
        $this->dispatch('toast', type: 'success', description: __('Event added.'));
    }

    public function toggleItem(int $itemId, bool $done, ToggleApprovalChecklistItem $toggleApprovalChecklistItem): void
    {
        $toggleApprovalChecklistItem->handle($this->actor(), $this->approval, $this->approval->checklist()->findOrFail($itemId), $done);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $this->approval->load(['project', 'authority', 'type', 'status', 'responsible', 'checklist', 'events' => fn ($query) => $query->with(['status', 'attachment', 'creator:id,name'])]);

        return view('livewire.projects.approvals.show', [
            'statuses' => ApprovalStatus::query()->active()->ordered()->get(['id', 'name']),
            'canManage' => $this->actor()->can('update', $this->approval),
        ])
            ->title($this->approval->type->name)
            ->layoutData(['back' => route('projects.projects.show', ['project' => $this->approval->project, 'tab' => 'approvals'])]);
    }
}
