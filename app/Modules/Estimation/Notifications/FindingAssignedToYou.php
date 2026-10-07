<?php

namespace App\Modules\Estimation\Notifications;

use App\Models\User;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;

/**
 * site.findings.assigned (docs/05 §9): the responsible employee hears about a finding.
 */
class FindingAssignedToYou extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public SiteInspectionFinding $finding)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'site.findings.assigned';
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        $inspection = $this->finding->inspection;

        return [
            'title' => __('Site finding on :project', ['project' => $inspection->project->project_number]),
            'body' => trim(($this->finding->location ? $this->finding->location.': ' : '').$this->finding->description)
                .($this->finding->due_date ? ' · '.__('due :date', ['date' => $this->finding->due_date->format('d M Y')]) : ''),
            'url' => route('site.inspections.show', $inspection),
        ];
    }
}
