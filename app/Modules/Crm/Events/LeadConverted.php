<?php

namespace App\Modules\Crm\Events;

use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Projects\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A lead was won and converted (docs/03 §8), with its project unless the project step was
 * skipped (Projects spec P21). Dispatched inside the conversion transaction, so a listener that
 * throws rolls the whole conversion back (CRM-AC-06).
 */
class LeadConverted
{
    use Dispatchable;

    public function __construct(public Lead $lead, public Customer $customer, public ?Project $project = null) {}
}
