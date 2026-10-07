<?php

namespace App\Modules\Projects\Livewire\Projects;

use App\Models\User;
use App\Modules\Crm\Actions\DeleteActivity;
use App\Modules\Projects\Actions\ChangeProjectStatus;
use App\Modules\Projects\Actions\DeleteProject;
use App\Modules\Projects\Actions\SetServiceStatus;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Services\ProjectTransitions;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The project cockpit (docs/04 §5.3): header, key figures and tabs. The 05–08 tabs and money
 * figures arrive with those modules (spec P1).
 */
class Show extends Component
{
    public const TABS = ['overview', 'contract', 'team', 'tasks', 'approvals', 'activities', 'documents', 'notes', 'history'];

    public Project $project;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    /** @var array{project_status_id: int|string|null, project_phase_id: int|string|null, reason: string, hold_reason_id: int|string|null, cancel_reason: string, handover_date: string, actual_end_date: string, override_reason: string} */
    public array $statusForm = [
        'project_status_id' => null, 'project_phase_id' => null, 'reason' => '', 'hold_reason_id' => null,
        'cancel_reason' => '', 'handover_date' => '', 'actual_end_date' => '', 'override_reason' => '',
    ];

    public ?int $deletingActivityId = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->resetStatusForm();
    }

    #[On('project-updated')]
    #[On('crm-activity-saved')]
    public function refreshProject(): void
    {
        $this->project->refresh();
    }

    public function changeStatus(ChangeProjectStatus $changeStatus): void
    {
        $this->authorize('changeStatus', $this->project);
        $this->resetErrorBag();

        $input = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $this->statusForm);
        $input['project_status_id'] = (int) $input['project_status_id'] === $this->project->project_status_id ? null : $input['project_status_id'];

        try {
            $changeStatus->handle($this->actor(), $this->project, $input);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["statusForm.{$key}" => $messages])->all());
        }

        $this->project->refresh();
        $this->resetStatusForm();
        $this->dispatch('close-sheet-project-status');
        $this->dispatch('toast', type: 'success', description: __('Project updated.'));
    }

    public function setServiceStatus(int $lineId, string $code, SetServiceStatus $setServiceStatus): void
    {
        $line = $this->project->services()->findOrFail($lineId);

        try {
            $setServiceStatus->handle($this->actor(), $line, $code);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->dispatch('toast', type: 'success', description: __('Delivery status saved.'));
    }

    public function confirmDeleteActivity(int $id): void
    {
        $this->deletingActivityId = $this->project->activities()->findOrFail($id)->id;
        $this->dispatch('open-sheet-activity-delete');
    }

    public function deleteActivity(DeleteActivity $deleteActivity): void
    {
        $deleteActivity->handle($this->actor(), $this->project->activities()->findOrFail($this->deletingActivityId));

        $this->deletingActivityId = null;
        $this->dispatch('close-sheet-activity-delete');
        $this->dispatch('toast', type: 'success', description: __('Activity deleted.'));
    }

    public function deleteProject(DeleteProject $deleteProject): void
    {
        $deleteProject->handle($this->actor(), $this->project);

        session()->flash('success', __('Project deleted.'));
        $this->redirectRoute('projects.projects.index', navigate: true);
    }

    private function resetStatusForm(): void
    {
        $this->statusForm = [
            'project_status_id' => $this->project->project_status_id, 'project_phase_id' => $this->project->project_phase_id,
            'reason' => '', 'hold_reason_id' => null, 'cancel_reason' => '', 'handover_date' => today()->toDateString(),
            'actual_end_date' => today()->toDateString(), 'override_reason' => '',
        ];
    }

    /**
     * Tabs the user may open.
     *
     * @return array<string, string>
     */
    private function allowedTabs(): array
    {
        $actor = $this->actor();

        return array_filter([
            'overview' => __('Overview'),
            'contract' => $actor->can('viewContract', $this->project) ? __('Contract & schedule') : null,
            'team' => __('Team'),
            'tasks' => $actor->can('projects.tasks.view') ? __('Tasks') : null,
            'approvals' => $actor->can('viewApprovals', $this->project) ? __('Approvals') : null,
            'activities' => $actor->can('crm.activities.view') ? __('Activities') : null,
            'documents' => __('Documents'),
            'notes' => __('Notes'),
            'history' => __('History'),
        ]);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(ProjectTransitions $transitions): View
    {
        $tabs = $this->allowedTabs();

        if (! array_key_exists($this->tab, $tabs)) {
            $this->tab = 'overview';
        }

        $this->project->load([
            'customer', 'businessLine', 'type', 'status', 'phase', 'branch', 'location', 'landAreaUnit', 'holdReason', 'sourceLead',
            'manager.status', 'supervisor', 'supportOfficer', 'contract.status', 'services.service', 'services.unit', 'services.status',
            'activeTeam.employee', 'activeTeam.role',
        ]);

        $project = $this->project;
        $tasks = $project->tasks();

        return view('livewire.projects.projects.show', [
            'tabs' => $tabs,
            'targets' => $project->status->is_closed
                ? ProjectStatus::query()->where('code', ProjectStatus::IN_PROGRESS)->get()
                : $transitions->targets($project->status),
            'figures' => [
                'tasks_total' => (clone $tasks)->whereHas('status', fn ($query) => $query->where('is_cancelled', false))->count(),
                'tasks_done' => (clone $tasks)->whereHas('status', fn ($query) => $query->where('is_done', true))->count(),
                'tasks_overdue' => (clone $tasks)->overdue()->count(),
                'approvals_pending' => $project->approvals()->pending()->count(),
                'next_milestone' => $project->schedules()->whereIn('schedule_status_id', [ScheduleStatus::idFor(ScheduleStatus::PENDING), ScheduleStatus::idFor(ScheduleStatus::DUE)])
                    ->with('status')->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('sort_order')->first(),
            ],
            'activities' => $this->tab === 'activities'
                ? $project->activities()->with(['type', 'outcome', 'owner:id,name'])->orderByRaw('completed_at IS NOT NULL')->orderBy('scheduled_at')->orderByDesc('completed_at')->get()
                : collect(),
            'approvals' => $this->tab === 'approvals'
                ? $project->approvals()->with(['authority', 'type', 'status', 'responsible'])->latest('id')->get()
                : collect(),
            'statusHistory' => $this->tab === 'history'
                ? $project->statusHistories()->with(['fromStatus:id,name', 'toStatus:id,name', 'fromPhase:id,name', 'toPhase:id,name', 'changer:id,name'])->get()
                : collect(),
        ])
            ->title($project->project_number)
            ->layoutData(['back' => route('projects.projects.index')]);
    }
}
