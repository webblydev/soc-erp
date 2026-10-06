<?php

namespace App\Modules\Crm\Events;

use App\Modules\Crm\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer exists (docs/03 §8): Accounting (08) may treat it as an AR party.
 */
class CustomerCreated
{
    use Dispatchable;

    public function __construct(public Customer $customer) {}
}
