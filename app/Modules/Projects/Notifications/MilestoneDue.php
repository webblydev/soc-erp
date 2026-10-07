<?php

namespace App\Modules\Projects\Notifications;

use App\Models\User;
use App\Modules\Projects\Models\PaymentSchedule;
use App\Support\Money;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * projects.milestone_due (docs/04 §10): a payment milestone is due, so an invoice can be raised.
 */
class MilestoneDue extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public PaymentSchedule $schedule)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'projects.milestone_due';
    }

    public function toMail(User $notifiable): MailMessage
    {
        $project = $this->schedule->project;

        return (new MailMessage)
            ->subject(__('Milestone due: :milestone', ['milestone' => $this->schedule->milestone_name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':milestone of :project is due: :amount.', ['milestone' => $this->schedule->milestone_name, 'project' => $project->project_number.' '.$project->name, 'amount' => Money::format($this->schedule->amount)]))
            ->action(__('Open project'), route('projects.projects.show', [$project, 'tab' => 'contract']));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        $project = $this->schedule->project;

        return [
            'title' => __('Milestone due: create invoice'),
            'body' => $project->project_number.' · '.$this->schedule->milestone_name.' · '.Money::format($this->schedule->amount),
            'url' => route('projects.projects.show', [$project, 'tab' => 'contract']),
        ];
    }
}
