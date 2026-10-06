<?php

namespace App\Modules\Crm\Notifications;

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;

/**
 * crm.lead_won (docs/03 §9, spec R14): a lead was converted; sent to its team manager and management.
 */
class LeadWon extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public Lead $lead, public Customer $customer)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'crm.lead_won';
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Lead :number won', ['number' => $this->lead->lead_number]),
            'body' => __(':lead converted to :customer', ['lead' => $this->lead->name, 'customer' => $this->customer->name]),
            'url' => route('crm.customers.show', $this->customer),
        ];
    }
}
