<?php

namespace App\Modules\Crm\Events;

use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A lead was won and converted (docs/03 §8). Dispatched inside the conversion transaction, so a
 * listener that throws (04's project creation) rolls the whole conversion back (CRM-AC-06).
 */
class LeadConverted
{
    use Dispatchable;

    public function __construct(public Lead $lead, public Customer $customer) {}
}
