<?php

namespace App\Modules\Crm\Policies;

use App\Models\User;
use App\Modules\Crm\Models\Customer;

/**
 * Single-customer access (docs/03 §2, spec R6).
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return Customer::query()->whereKey($customer->id)->visibleTo($user, 'crm.customers')->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('crm.customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('crm.customers.update') && $this->view($user, $customer);
    }

    public function merge(User $user, Customer $customer): bool
    {
        return $user->can('crm.customers.merge') && $this->view($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('crm.customers.delete') && $this->view($user, $customer);
    }
}
