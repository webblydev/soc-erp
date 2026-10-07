<?php

namespace App\Modules\Estimation\Livewire\Site;

use App\Models\User;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Services\MeasurementLimits;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The project page's Site tab (spec E21): BOQ progress from the Measurement Book, recent entries
 * and inspections with their open findings.
 */
class ProjectTab extends Component
{
    private const RECENT = 10;

    public Project $project;

    public function mount(Project $project): void
    {
        $this->authorize('viewSite', $project);
        $this->project = $project;
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(MeasurementLimits $limits): View
    {
        $actor = $this->actor();
        $canSeeMb = $actor->can('site.mb.view');
        $canSeeInspections = $actor->can('site.inspections.view');
        $rejected = MbStatus::query()->where('code', MbStatus::REJECTED)->value('id');

        return view('livewire.estimation.site.project-tab', [
            'canSeeMb' => $canSeeMb,
            'canSeeInspections' => $canSeeInspections,
            'progress' => $canSeeMb ? $limits->boqLines($this->project)->with('unit:id,symbol')->get()
                ->map(fn ($line): array => ['line' => $line, ...$limits->check($line, '0')])
                ->filter(fn (array $row): bool => (float) $row['cumulative'] > 0)->values() : collect(),
            'entries' => $canSeeMb ? MeasurementEntry::query()->where('project_id', $this->project->id)->where('mb_status_id', '!=', $rejected)
                ->with(['status:id,name,color', 'unit:id,symbol'])->latest('measured_on')->latest('id')->limit(self::RECENT)->get() : collect(),
            'inspections' => $canSeeInspections ? SiteInspection::query()->where('project_id', $this->project->id)
                ->with(['type:id,name', 'status:id,name,color'])->withCount(['findings as open_findings_count' => fn ($query) => $query->open()])
                ->latest('inspection_date')->latest('id')->limit(self::RECENT)->get() : collect(),
            'canRecord' => $actor->can('recordMeasurement', $this->project) && $this->project->isOpen(),
            'canInspect' => $actor->can('createInspection', $this->project),
        ]);
    }
}
