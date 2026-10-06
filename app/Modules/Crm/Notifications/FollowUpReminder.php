<?php

namespace App\Modules\Crm\Notifications;

use App\Models\User;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Lead;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * crm.follow_up_reminder (docs/03 §6.2 step 2, CRM-AC-07): a scheduled activity is coming up.
 */
class FollowUpReminder extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public CrmActivity $activity)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'crm.follow_up_reminder';
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Reminder: :title at :time', ['title' => $this->activity->title, 'time' => $this->activity->scheduled_at?->format('h:i A')]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->subjectLine())
            ->action(__('Open'), $this->url() ?? route('crm.activities.index'));
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Reminder: :title', ['title' => $this->activity->title]),
            'body' => __(':type at :time', ['type' => $this->activity->type->name, 'time' => $this->activity->scheduled_at?->format('d-M-Y h:i A')]).' · '.$this->subjectLine(),
            'url' => $this->url(),
        ];
    }

    private function subjectLine(): string
    {
        $subject = $this->activity->subject;

        return match (true) {
            $subject instanceof Lead => "{$subject->name} ({$subject->lead_number})",
            $subject !== null => "{$subject->getAttribute('name')} ({$subject->getAttribute('customer_number')})",
            default => '',
        };
    }

    private function url(): ?string
    {
        $subject = $this->activity->subject;

        return match (true) {
            $subject instanceof Lead => route('crm.leads.show', $subject),
            $subject !== null => route('crm.customers.show', $subject),
            default => null,
        };
    }
}
