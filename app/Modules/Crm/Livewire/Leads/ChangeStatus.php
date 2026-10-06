<?php

namespace App\Modules\Crm\Livewire\Leads;

use App\Models\User;
use App\Modules\Crm\Actions\ChangeLeadStatus;
use App\Modules\Crm\Actions\MarkLeadLost;
use App\Modules\Crm\Actions\ReopenLead;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The change-status sheet (docs/03 §5.6): open statuses with the follow-up rule, Lost with a
 * reason, and Reopen. Mounted once per page; opened with the `crm-change-status` event.
 */
class ChangeStatus extends Component
{
    #[Locked]
    public string $leadNumber = '';

    #[Locked]
    public string $mode = 'status';

    public int|string|null $statusId = null;

    public string $note = '';

    public int|string|null $lostReasonId = null;

    public int|string|null $followUpTypeId = null;

    public string $followUpAt = '';

    public string $reason = '';

    #[On('crm-change-status')]
    public function open(string $lead, string $mode = 'status', ?int $status = null): void
    {
        $this->reset();
        $this->resetErrorBag();

        $model = $this->findLead($lead);
        $this->authorize('changeStatus', $model);

        $this->leadNumber = $model->lead_number;
        $this->mode = in_array($mode, ['status', 'lost', 'reopen'], true) ? $mode : 'status';
        $this->statusId = $status;

        $this->dispatch('open-sheet-change-status');
    }

    /**
     * Whether the chosen status needs a follow-up the lead does not have yet (CRM-BR-07).
     */
    #[Computed]
    public function needsFollowUp(): bool
    {
        if ($this->mode !== 'status' || ! is_numeric($this->statusId) || $this->leadNumber === '') {
            return false;
        }

        $status = LeadStatus::query()->find((int) $this->statusId);

        return $status !== null
            && ChangeLeadStatus::needsFollowUp($status)
            && ! $this->findLead($this->leadNumber)->activities()->whereNull('completed_at')->whereNotNull('scheduled_at')->exists();
    }

    public function save(ChangeLeadStatus $changeLeadStatus, MarkLeadLost $markLeadLost, ReopenLead $reopenLead): void
    {
        $this->resetErrorBag();

        $lead = $this->findLead($this->leadNumber);

        if ($this->mode === 'status' && $this->statusId === 'won') {
            $this->authorize('convert', $lead);
            $this->redirectRoute('crm.leads.convert', $lead, navigate: true);

            return;
        }

        $this->authorize('changeStatus', $lead);

        match ($this->mode) {
            'lost' => $markLeadLost->handle($this->actor(), $lead, $this->lostReasonId === null || $this->lostReasonId === '' ? null : (int) $this->lostReasonId, $this->note !== '' ? $this->note : null),
            'reopen' => $reopenLead->handle($this->actor(), $lead, $this->reason),
            default => $changeLeadStatus->handle($this->actor(), $lead, (int) $this->statusId, $this->note !== '' ? $this->note : null, filled($this->followUpTypeId)
                ? ['activity_type_id' => $this->followUpTypeId, 'scheduled_at' => $this->followUpAt]
                : null),
        };

        $this->dispatch('close-sheet-change-status');
        $this->dispatch('crm-lead-updated');
        $this->dispatch('toast', type: 'success', description: __('Lead updated.'));
    }

    private function findLead(string $number): Lead
    {
        return Lead::query()->where('lead_number', $number)->firstOrFail();
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.crm.leads.change-status', [
            'statuses' => LeadStatus::query()->active()->open()->ordered()->get(['id', 'name']),
            'canConvert' => $this->leadNumber !== '' && $this->actor()->can('convert', $this->findLead($this->leadNumber)),
        ]);
    }
}
