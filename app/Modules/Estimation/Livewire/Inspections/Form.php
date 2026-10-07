<?php

namespace App\Modules\Estimation\Livewire\Inspections;

use App\Models\User;
use App\Modules\Estimation\Actions\SaveInspection;
use App\Modules\Estimation\Actions\SubmitInspection;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * New / edit site inspection (docs/05 §5.8, spec §5.10): one column for the phone, the header and,
 * while DRAFT, the findings repeater. Photos are added on the inspection page once saved.
 */
class Form extends Component
{
    private const FIELDS = [
        'inspection_type_id', 'inspection_date', 'start_time', 'end_time', 'site_address', 'permittee_name', 'contractor_name',
        'project_engineer_id', 'field_office_phone', 'weather', 'workers_on_site', 'work_progress_summary', 'client_representative',
    ];

    private const FINDING_FIELDS = ['id', 'location', 'description', 'finding', 'finding_category_id', 'finding_severity_id', 'action_required', 'responsible_type', 'responsible_id', 'due_date', 'found_by_name'];

    public ?SiteInspection $inspection = null;

    #[Url(as: 'project', except: '')]
    public string $projectNumber = '';

    public int|string|null $project_id = null;

    public int|string|null $inspection_type_id = null;

    public string $inspection_date = '';

    public string $start_time = '';

    public string $end_time = '';

    public string $site_address = '';

    public string $permittee_name = '';

    public string $contractor_name = '';

    public int|string|null $project_engineer_id = null;

    public string $field_office_phone = '';

    public string $weather = '';

    public string $workers_on_site = '';

    public string $work_progress_summary = '';

    public string $client_representative = '';

    /** @var list<array<string, mixed>> */
    public array $findings = [];

    public function mount(?SiteInspection $inspection = null): void
    {
        if ($inspection === null || ! $inspection->exists) {
            $this->authorize('create', SiteInspection::class);
            $project = $this->projectNumber !== '' ? $this->projects()->firstWhere('project_number', $this->projectNumber) : null;
            $this->project_id = $project?->id;
            $this->site_address = (string) $project?->site_address;
            $this->inspection_type_id = InspectionType::query()->where('code', InspectionType::WEEKLY)->value('id');
            $this->inspection_date = today()->toDateString();
            $this->project_engineer_id = $this->actor()->employee_id;

            return;
        }

        $this->authorize('update', $inspection);

        if ($inspection->hasStatus(InspectionStatus::CLOSED)) {
            session()->flash('error', __('A closed inspection cannot be edited.'));
            $this->redirectRoute('site.inspections.show', $inspection, navigate: true);

            return;
        }

        $this->inspection = $inspection;
        $this->project_id = $inspection->project_id;

        foreach (self::FIELDS as $field) {
            $value = $inspection->getAttribute($field);
            $this->{$field} = match (true) {
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                in_array($field, ['start_time', 'end_time'], true) => $value !== null ? substr((string) $value, 0, 5) : '',
                in_array($field, ['inspection_type_id', 'project_engineer_id'], true) => $value,
                default => (string) $value,
            };
        }

        $this->findings = $inspection->findings()->get()->map(fn ($finding): array => [
            ...collect($finding->only(self::FINDING_FIELDS))->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : ($value ?? ''))->all(),
            'id' => $finding->id,
        ])->values()->all();
    }

    public function addFinding(): void
    {
        $this->findings[] = [
            'id' => null, 'location' => '', 'description' => '', 'finding' => '', 'finding_category_id' => '',
            'finding_severity_id' => FindingSeverity::idFor(FindingSeverity::MEDIUM), 'action_required' => '',
            'responsible_type' => '', 'responsible_id' => '', 'due_date' => '', 'found_by_name' => '',
        ];
    }

    public function removeFinding(int $index): void
    {
        unset($this->findings[$index]);
        $this->findings = array_values($this->findings);
    }

    public function save(SaveInspection $saveInspection): void
    {
        $inspection = $this->persist($saveInspection);

        if ($inspection !== null) {
            session()->flash('success', __('Inspection :number saved.', ['number' => $inspection->inspection_number]));
            $this->redirectRoute('site.inspections.show', $inspection, navigate: true);
        }
    }

    public function saveAndSubmit(SaveInspection $saveInspection, SubmitInspection $submitInspection): void
    {
        $inspection = $this->persist($saveInspection);

        if ($inspection === null) {
            return;
        }

        if ($inspection->hasStatus(InspectionStatus::DRAFT)) {
            $submitInspection->handle($this->actor(), $inspection);
        }

        session()->flash('success', __('Inspection :number submitted.', ['number' => $inspection->inspection_number]));
        $this->redirectRoute('site.inspections.show', $inspection, navigate: true);
    }

    private function persist(SaveInspection $saveInspection): ?SiteInspection
    {
        $this->resetErrorBag();
        $input = [...$this->only(self::FIELDS), 'findings' => $this->findings];

        if ($this->inspection !== null) {
            return $saveInspection->handle($this->actor(), $input, $this->inspection);
        }

        $project = $this->projects()->firstWhere('id', (int) $this->project_id);

        if ($project === null) {
            $this->addError('project_id', __('Choose the project.'));

            return null;
        }

        return $saveInspection->handle($this->actor(), $input, project: $project);
    }

    /**
     * Projects the user may inspect: open ones, and completed ones for handover / snag lists.
     *
     * @return Collection<int, Project>
     */
    private function projects(): Collection
    {
        return Project::query()->visibleTo($this->actor())->whereHas('status', fn ($query) => $query->where('code', '!=', 'CANCELLED'))
            ->orderBy('project_number')->get(['id', 'project_number', 'name', 'site_address', 'customer_id', 'project_status_id'])
            ->filter(fn (Project $project): bool => $this->actor()->can('createInspection', $project))->values();
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $isDraft = $this->inspection === null || $this->inspection->hasStatus(InspectionStatus::DRAFT);

        return view('livewire.estimation.inspections.form', [
            'projects' => $this->inspection === null ? $this->projects() : collect(),
            'isDraft' => $isDraft,
            'followUpSeverities' => FindingSeverity::query()->where('requires_follow_up', true)->pluck('id')->all(),
            'projectHasCustomer' => Project::query()->whereKey($this->project_id)->whereNotNull('customer_id')->exists(),
            'canSubmit' => $this->inspection === null ? $this->actor()->can('site.inspections.update') : $this->actor()->can('update', $this->inspection),
        ])
            ->title($this->inspection === null ? __('New inspection') : __('Edit :number', ['number' => $this->inspection->inspection_number]))
            ->layoutData(['back' => $this->inspection === null ? route('site.inspections.index') : route('site.inspections.show', $this->inspection), 'bottomNav' => false]);
    }
}
