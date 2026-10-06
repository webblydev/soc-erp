<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a customer. Customers converted from a lead or holding merged customers are
 * refused, so lead and merge history keep pointing at a live record.
 */
class DeleteCustomer
{
    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, Customer $customer): void
    {
        Gate::forUser($actor)->authorize('delete', $customer);

        if (Lead::withTrashed()->where('converted_customer_id', $customer->id)->exists()) {
            throw ValidationException::withMessages(['customer' => __('Customers converted from a lead cannot be deleted.')]);
        }

        if (Customer::withTrashed()->where('merged_into_id', $customer->id)->exists()) {
            throw ValidationException::withMessages(['customer' => __('Customers that others were merged into cannot be deleted.')]);
        }

        $customer->delete();
    }
}
