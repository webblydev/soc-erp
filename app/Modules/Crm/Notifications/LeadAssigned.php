<?php

namespace App\Modules\Crm\Notifications;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * crm.lead_assigned (docs/03 §9): a lead was assigned to the user by someone else.
 */
class LeadAssigned extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public Lead $lead)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'crm.lead_assigned';
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Lead :number assigned to you', ['number' => $this->lead->lead_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':lead (:phone) is now yours.', ['lead' => $this->lead->name, 'phone' => $this->lead->phone]))
            ->action(__('Open lead'), route('crm.leads.show', $this->lead));
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Lead :number assigned to you', ['number' => $this->lead->lead_number]),
            'body' => $this->lead->name,
            'url' => route('crm.leads.show', $this->lead),
        ];
    }
}
