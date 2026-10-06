<?php

namespace App\Modules\Crm\Events;

use App\Modules\Crm\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Two customers are being merged (docs/03 §5.12, spec R16). Dispatched inside the merge
 * transaction so 04, 06 and 08 can move projects, invoices, receipts and journal lines.
 */
class CustomersMerging
{
    use Dispatchable;

    public function __construct(public Customer $survivor, public Customer $duplicate) {}
}
