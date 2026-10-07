<?php

namespace App\Modules\Estimation\Notifications;

use App\Models\User;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;

/**
 * site.findings.overdue (docs/05 §9): an open finding is past its due date.
 */
class FindingOverdue extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public SiteInspectionFinding $finding)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'site.findings.overdue';
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Overdue site finding on :project', ['project' => $this->finding->project->project_number]),
            'body' => trim(($this->finding->location ? $this->finding->location.': ' : '').$this->finding->description)
                .' · '.__('due :date', ['date' => $this->finding->due_date?->format('d M Y')]),
            'url' => route('site.inspections.show', $this->finding->inspection),
        ];
    }
}
