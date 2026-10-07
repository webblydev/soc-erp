<?php

namespace App\Modules\Projects\Notifications;

use App\Models\User;
use App\Modules\Projects\Models\ProjectApproval;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * projects.approvals.overdue (docs/04 §10): an approval is past its expected date.
 */
class ApprovalOverdue extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public ProjectApproval $approval)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'projects.approvals.overdue';
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Approval overdue: :type', ['type' => $this->approval->type->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':project · :authority, expected :date.', ['project' => $this->approval->project->project_number, 'authority' => $this->approval->authority->name, 'date' => $this->approval->expected_on?->format('d-M-Y')]))
            ->action(__('Open approval'), route('projects.approvals.show', $this->approval));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Approval overdue: :type', ['type' => $this->approval->type->name]),
            'body' => $this->approval->project->project_number.' · '.$this->approval->authority->name,
            'url' => route('projects.approvals.show', $this->approval),
        ];
    }
}
