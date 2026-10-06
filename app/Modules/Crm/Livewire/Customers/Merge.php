<?php

namespace App\Modules\Crm\Livewire\Customers;

use App\Models\User;
use App\Modules\Crm\Actions\MergeCustomers;
use App\Modules\Crm\Models\Customer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Customer merge (docs/03 §5.12, spec R16): pick the duplicate, preview what moves, confirm with a reason.
 */
class Merge extends Component
{
    #[Locked]
    public int $survivorId;

    public string $duplicateId = '';

    public string $search = '';

    public string $reason = '';

    /** @var array<string, int> */
    public array $preview = [];

    public function mount(Customer $customer): void
    {
        $this->authorize('merge', $customer);
        $this->survivorId = $customer->id;
    }

    public function updatedDuplicateId(): void
    {
        $duplicate = $this->duplicate();
        $this->preview = $duplicate !== null ? app(MergeCustomers::class)->preview($duplicate) : [];
    }

    public function swap(): void
    {
        $duplicate = $this->duplicate();

        if ($duplicate === null) {
            return;
        }

        $this->authorize('merge', $duplicate);

        [$this->survivorId, $this->duplicateId] = [$duplicate->id, (string) $this->survivorId];
        $this->updatedDuplicateId();
    }

    public function merge(MergeCustomers $merge): void
    {
        $this->resetErrorBag();

        $survivor = Customer::query()->findOrFail($this->survivorId);
        $duplicate = $this->duplicate() ?? abort(422);

        $merge->handle($this->actor(), $survivor, $duplicate, $this->reason);

        session()->flash('success', __('Customers merged.'));
        $this->redirectRoute('crm.customers.show', $survivor, navigate: true);
    }

    private function duplicate(): ?Customer
    {
        return ctype_digit($this->duplicateId)
            ? Customer::query()->visibleTo($this->actor(), 'crm.customers')->whereNull('merged_into_id')->whereKeyNot($this->survivorId)->find((int) $this->duplicateId)
            : null;
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $survivor = Customer::query()->with(['status', 'accountManager'])->findOrFail($this->survivorId);
        $term = trim($this->search);

        return view('livewire.crm.customers.merge', [
            'survivor' => $survivor,
            'duplicate' => $this->duplicate()?->load(['status', 'accountManager']),
            'results' => mb_strlen($term) >= 2
                ? Customer::query()->visibleTo($this->actor(), 'crm.customers')->whereNull('merged_into_id')->whereKeyNot($this->survivorId)
                    ->where(fn ($query) => $query->where('customer_number', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
                    ->orderBy('name')->limit(10)->get(['id', 'customer_number', 'name', 'phone'])
                : collect(),
        ])
            ->title(__('Merge customers'))
            ->layoutData(['back' => route('crm.customers.show', $survivor), 'bottomNav' => false]);
    }
}
