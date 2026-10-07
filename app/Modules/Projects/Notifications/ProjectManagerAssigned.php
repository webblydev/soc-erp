<?php

namespace App\Modules\Projects\Notifications;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * projects.assigned_pm (docs/04 §10): the user's employee was made project manager.
 */
class ProjectManagerAssigned extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public Project $project)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'projects.assigned_pm';
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You are project manager of :number', ['number' => $this->project->project_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':project is now yours to manage.', ['project' => $this->project->name]))
            ->action(__('Open project'), route('projects.projects.show', $this->project));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('You are project manager of :number', ['number' => $this->project->project_number]),
            'body' => $this->project->name,
            'url' => route('projects.projects.show', $this->project),
        ];
    }
}
