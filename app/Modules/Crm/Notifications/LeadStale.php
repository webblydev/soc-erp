<?php

namespace App\Modules\Crm\Notifications;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;

/**
 * crm.lead_stale (docs/03 §9, CRM-BR-11): an open lead has had no activity for the set days.
 */
class LeadStale extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public Lead $lead, public int $days)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'crm.lead_stale';
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Lead :number has gone quiet', ['number' => $this->lead->lead_number]),
            'body' => __('No activity for :days days.', ['days' => $this->days]),
            'url' => route('crm.leads.show', $this->lead),
        ];
    }
}
