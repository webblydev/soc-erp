<?php

namespace App\Modules\Crm\Livewire\Customers;

use App\Models\User;
use App\Modules\Crm\Actions\DeleteActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Customer detail (docs/03 §5.11). No money figures or Projects / Invoices tabs until those
 * modules exist (spec R2).
 */
class Show extends Component
{
    public const TABS = ['overview', 'activities', 'leads', 'documents', 'notes', 'history'];

    public Customer $customer;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    public ?int $deletingActivityId = null;

    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);
        $this->customer = $customer;
    }

    #[On('crm-activity-saved')]
    public function refreshCustomer(): void
    {
        $this->customer->refresh();
    }

    public function confirmDeleteActivity(int $id): void
    {
        $this->deletingActivityId = $this->customer->timeline()->findOrFail($id)->id;
        $this->dispatch('open-sheet-activity-delete');
    }

    public function deleteActivity(DeleteActivity $deleteActivity): void
    {
        $activity = $this->customer->timeline()->findOrFail($this->deletingActivityId);

        $deleteActivity->handle($this->actor(), $activity);

        $this->deletingActivityId = null;
        $this->dispatch('close-sheet-activity-delete');
        $this->dispatch('toast', type: 'success', description: __('Activity deleted.'));
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }

        $this->customer->load(['type', 'status', 'location', 'businessLine', 'accountManager', 'acquiredBy', 'leadSource', 'paymentTerm', 'sourceLead', 'contacts']);

        return view('livewire.crm.customers.show', [
            'activities' => $this->tab === 'activities'
                ? $this->customer->timeline()->with(['subject', 'type', 'outcome', 'owner:id,name'])
                    ->orderByRaw('completed_at IS NOT NULL')->orderBy('scheduled_at')->orderByDesc('completed_at')->get()
                : collect(),
            'leads' => $this->tab === 'leads'
                ? Lead::query()->with('status:id,name,color')
                    ->where(fn (Builder $query) => $query->where('converted_customer_id', $this->customer->id)
                        ->orWhere(fn (Builder $query) => $query->where('referrer_type', 'customer')->where('referrer_id', $this->customer->id)))
                    ->latest('lead_date')->latest('id')->get()
                : collect(),
        ])
            ->title($this->customer->customer_number)
            ->layoutData(['back' => route('crm.customers.index')]);
    }
}
