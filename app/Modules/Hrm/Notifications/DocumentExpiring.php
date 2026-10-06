<?php

namespace App\Modules\Hrm\Notifications;

use App\Models\User;
use App\Modules\Hrm\Models\EmployeeDocument;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * hrm.document_expiring (docs/09 §7, HR-AC-05): an employee document expires within 30 days.
 */
class DocumentExpiring extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public EmployeeDocument $document)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'hrm.document_expiring';
    }

    public function toMail(User $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($data['body'])
            ->action(__('Open'), $data['url']);
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        $employee = $this->document->employee;

        return [
            'title' => __(':type of :name expires soon', ['type' => $this->document->type->name, 'name' => $employee->full_name]),
            'body' => __('Expires on :date.', ['date' => $this->document->expiry_date?->format('d-M-Y')]),
            'url' => route('hrm.employees.show', [$employee, 'tab' => 'documents']),
        ];
    }
}
