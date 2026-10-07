<?php

namespace App\Modules\Projects\Notifications;

use App\Models\User;
use App\Modules\Projects\Models\ProjectApproval;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;

/**
 * projects.approvals.status_changed (docs/04 §10): an approval event changed its status.
 */
class ApprovalStatusUpdated extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public ProjectApproval $approval)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'projects.approvals.status_changed';
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __(':type: :status', ['type' => $this->approval->type->name, 'status' => $this->approval->status->name]),
            'body' => $this->approval->project->project_number.' · '.$this->approval->authority->name,
            'url' => route('projects.approvals.show', $this->approval),
        ];
    }
}
