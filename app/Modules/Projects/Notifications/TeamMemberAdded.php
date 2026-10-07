<?php

namespace App\Modules\Projects\Notifications;

use App\Models\User;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;

/**
 * projects.team_added (docs/04 §10): the user's employee joined a project team.
 */
class TeamMemberAdded extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public ProjectEmployee $member)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'projects.team_added';
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        $project = $this->member->project;

        return [
            'title' => __('You joined :number as :role', ['number' => $project->project_number, 'role' => $this->member->role->name]),
            'body' => $project->name,
            'url' => route('projects.projects.show', $project),
        ];
    }
}
