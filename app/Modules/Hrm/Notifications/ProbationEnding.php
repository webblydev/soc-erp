<?php

namespace App\Modules\Hrm\Notifications;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

/**
 * hrm.probation_ending (docs/09 §7): a probationer's confirmation date is within 15 days.
 */
class ProbationEnding extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public Employee $employee)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'hrm.probation_ending';
    }

    public function toMail(User $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($data['body'])
            ->action(__('Open'), $data['url'] ?? url('/'));
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Probation of :name ends soon', ['name' => $this->employee->full_name]),
            'body' => __('Confirmation due on :date.', ['date' => $this->employee->confirmation_date?->format('d-M-Y')]),
            'url' => Route::has('hrm.employees.show') ? route('hrm.employees.show', $this->employee) : null,
        ];
    }
}
