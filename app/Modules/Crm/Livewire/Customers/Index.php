<?php

namespace App\Modules\Crm\Livewire\Customers;

use App\Models\User;
use App\Modules\Crm\Actions\DeleteCustomer;
use App\Modules\Crm\Actions\ReassignCustomers;
use App\Modules\Crm\Concerns\FiltersByLocation;
use App\Modules\Crm\Models\Customer;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Customers list (docs/03 §5.9). No money figures until Sales and Accounting exist (spec R2).
 */
#[Title('Customers')]
class Index extends Component
{
    use FiltersByLocation, WithBulkActions, WithListing;

    public string $bulkManagerId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);
    }

    /**
     * @return Builder<Customer>
     */
    protected function listingQuery(): Builder
    {
        return Customer::query()
            ->visibleTo($this->actor(), 'crm.customers')
            ->with(['type:id,name', 'status:id,name,color', 'location:id,full_path', 'accountManager:id,name'])
            ->orderBy('name');
    }

    protected function searchColumns(): array
    {
        return ['customers.customer_number', 'customers.name', 'customers.company_name', 'customers.phone', 'customers.alternate_phone', 'customers.whatsapp', 'customers.email'];
    }

    protected function sortColumns(): array
    {
        return ['number' => 'customers.customer_number', 'name' => 'customers.name', 'created' => 'customers.created_at'];
    }

    /**
     * @param  Builder<Customer>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        foreach (['type' => 'customer_type_id', 'status' => 'customer_status_id', 'manager' => 'account_manager_user_id', 'business_line' => 'business_line_id', 'source' => 'lead_source_id'] as $key => $column) {
            if ($this->filterString($key) !== '') {
                $query->where('customers.'.$column, (int) $this->filterString($key));
            }
        }

        if ($this->filterString('location') !== '') {
            $this->whereInLocation($query, 'customers.location_id', (int) $this->filterString('location'));
        }
    }

    public function bulkChangeManager(ReassignCustomers $reassignCustomers): void
    {
        $this->authorize('crm.customers.update');

        $count = $reassignCustomers->handle($this->actor(), array_map('intval', $this->selected), $this->bulkManagerId === '' ? null : (int) $this->bulkManagerId);

        $this->reset('selected', 'bulkManagerId');
        $this->dispatch('toast', type: 'success', description: trans_choice(':count customer updated.|:count customers updated.', $count));
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('crm.customers.export');

        return ListingExport::download('customers', $this->exportQuery(), [
            'Customer #' => 'customer_number',
            'Name' => 'name',
            'Company' => 'company_name',
            'Type' => 'type.name',
            'Phone' => 'phone',
            'Email' => 'email',
            'Location' => 'location.full_path',
            'Account manager' => 'accountManager.name',
            'Status' => 'status.name',
        ]);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('crm.customers.delete');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var Customer $row */
        app(DeleteCustomer::class)->handle($this->actor(), $row);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.crm.customers.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'managers' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
