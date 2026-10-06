<?php

namespace App\Modules\Crm\Livewire\Leads;

use App\Models\User;
use App\Modules\Crm\Actions\AssignLead;
use App\Modules\Crm\Actions\DeleteActivity;
use App\Modules\Crm\Actions\DeleteLead;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Lead detail (docs/03 §5.3): header actions, pipeline bar, tabs and the summary rail.
 */
class Show extends Component
{
    public const TABS = ['overview', 'activities', 'documents', 'notes', 'history'];

    public Lead $lead;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    public string $assignTo = '';

    public string $assignReason = '';

    public ?int $deletingActivityId = null;

    public function mount(Lead $lead): void
    {
        $this->authorize('view', $lead);
        $this->lead = $lead;
    }

    #[On('crm-lead-updated')]
    #[On('crm-activity-saved')]
    public function refreshLead(): void
    {
        $this->lead->refresh();
    }

    public function assign(AssignLead $assignLead): void
    {
        $this->authorize('assign', $this->lead);

        $assignLead->handle($this->actor(), $this->lead, $this->assignTo === '' ? null : (int) $this->assignTo, $this->assignReason !== '' ? $this->assignReason : null);

        $this->reset('assignTo', 'assignReason');
        $this->lead->refresh();
        $this->dispatch('close-sheet-lead-assign');
        $this->dispatch('toast', type: 'success', description: __('Lead assigned.'));
    }

    public function confirmDeleteActivity(int $id): void
    {
        $this->deletingActivityId = $this->lead->activities()->findOrFail($id)->id;
        $this->dispatch('open-sheet-activity-delete');
    }

    public function deleteActivity(DeleteActivity $deleteActivity): void
    {
        $activity = $this->lead->activities()->findOrFail($this->deletingActivityId);

        $deleteActivity->handle($this->actor(), $activity);

        $this->deletingActivityId = null;
        $this->lead->refresh();
        $this->dispatch('close-sheet-activity-delete');
        $this->dispatch('toast', type: 'success', description: __('Activity deleted.'));
    }

    public function deleteLead(DeleteLead $deleteLead): void
    {
        $deleteLead->handle($this->actor(), $this->lead);

        session()->flash('success', __('Lead deleted.'));
        $this->redirectRoute('crm.leads.index', navigate: true);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(AssignLead $assignLead): View
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }

        $this->lead->load([
            'status', 'source', 'priority', 'level', 'businessLine', 'location', 'assignee', 'team', 'lostReason',
            'convertedCustomer', 'referrerCustomer', 'referrerUser', 'services.service',
        ]);

        $activities = $this->lead->activities()->with(['type', 'outcome', 'owner:id,name'])
            ->orderByRaw('completed_at IS NOT NULL')
            ->orderBy('scheduled_at')
            ->orderByDesc('completed_at')
            ->get();

        $enteredStatusAt = $this->lead->statusHistories()->where('to_status_id', $this->lead->lead_status_id)->max('changed_at');

        return view('livewire.crm.leads.show', [
            'activities' => $activities,
            'statuses' => LeadStatus::query()->active()->open()->ordered()->get(['id', 'name', 'code']),
            'ageDays' => (int) $this->lead->lead_date->diffInDays(today()),
            'daysInStatus' => $enteredStatusAt ? (int) Carbon::parse($enteredStatusAt)->diffInDays(now()) : null,
            'assignees' => $this->actor()->can('assign', $this->lead) ? $assignLead->assignableUsers($this->actor()) : collect(),
            'statusHistory' => $this->lead->statusHistories()->with(['fromStatus:id,name', 'toStatus:id,name', 'changer:id,name'])->get(),
            'assignmentHistory' => $this->lead->assignmentHistories()->with(['fromUser:id,name', 'toUser:id,name', 'assigner:id,name'])->get(),
        ])
            ->title($this->lead->lead_number)
            ->layoutData(['back' => url()->previous()]);
    }
}
