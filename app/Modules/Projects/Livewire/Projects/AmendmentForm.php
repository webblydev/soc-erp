<?php

namespace App\Modules\Projects\Livewire\Projects;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Projects\Actions\SaveAmendment;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectContractAmendment;
use App\Modules\Projects\Models\ProjectServiceStatus;
use App\Modules\Projects\Services\ServiceLines;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * New / edit draft amendment (spec P11): the services grid prefilled from the project; lines
 * removed here become CANCELLED when the amendment is approved.
 */
class AmendmentForm extends Component
{
    public Project $project;

    public ?ProjectContractAmendment $amendment = null;

    public string $amendment_date = '';

    public string $reason = '';

    /** @var list<array<string, mixed>> */
    public array $services = [];

    public function mount(Project $project, ?ProjectContractAmendment $amendment = null): void
    {
        $this->authorize('manageContract', $project);
        $this->project = $project;

        if ($amendment !== null && $amendment->exists) {
            abort_unless($amendment->project_contract_id === $project->contract?->id && $amendment->isDraft(), 404);

            $this->amendment = $amendment;
            $this->amendment_date = $amendment->amendment_date->toDateString();
            $this->reason = $amendment->reason;
            $this->services = $amendment->lines()->get()->map(fn ($line): array => $this->row($line->project_service_id, $line->service_id, $line->description, $line->quantity, $line->unit_id, $line->rate, $line->discount_amount, $line->project_service_status_id))->all();

            return;
        }

        $this->amendment_date = today()->toDateString();
        $this->services = $project->services()->get()->map(fn ($line): array => $this->row($line->id, $line->service_id, $line->description, $line->quantity, $line->unit_id, $line->rate, $line->discount_amount, $line->project_service_status_id))->all();
    }

    public function addService(): void
    {
        $this->services[] = $this->row(null, null, null, '1', null, '', '0', ProjectServiceStatus::idFor(ProjectServiceStatus::NOT_STARTED));
    }

    public function removeService(int $index): void
    {
        unset($this->services[$index]);
        $this->services = array_values($this->services);
    }

    public function save(SaveAmendment $saveAmendment): void
    {
        $this->authorize('manageContract', $this->project);

        $saveAmendment->handle($this->actor(), $this->project, [
            'amendment_date' => $this->amendment_date,
            'reason' => $this->reason,
            'services' => $this->services,
        ], $this->amendment);

        session()->flash('success', __('Amendment saved as a draft. Approve it on the Contract tab.'));
        $this->redirectRoute('projects.projects.show', ['project' => $this->project, 'tab' => 'contract'], navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(?int $id, ?int $serviceId, ?string $description, string $quantity, ?int $unitId, string $rate, string $discount, int $statusId): array
    {
        $clean = fn (string $value): string => $value === '' ? '' : rtrim(rtrim($value, '0'), '.');

        return [
            'id' => $id, 'service_id' => $serviceId, 'description' => (string) $description, 'quantity' => $clean($quantity),
            'unit_id' => $unitId, 'rate' => $clean($rate), 'discount_amount' => $clean($discount), 'project_service_status_id' => $statusId,
        ];
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $cancelled = ProjectServiceStatus::idFor(ProjectServiceStatus::CANCELLED);
        $total = BigDecimal::zero();

        foreach ($this->services as $line) {
            $quantity = str_replace(',', '', (string) $line['quantity']);
            $rate = str_replace(',', '', (string) $line['rate']);
            $discount = str_replace(',', '', (string) $line['discount_amount']) ?: '0';

            if (is_numeric($quantity) && is_numeric($rate) && is_numeric($discount) && (int) $line['project_service_status_id'] !== $cancelled) {
                $total = $total->plus(ServiceLines::amount($quantity, $rate, $discount));
            }
        }

        return view('livewire.projects.projects.amendment-form', [
            'serviceOptions' => Service::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', collect($this->services)->pluck('service_id')->filter()))->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->active()->ordered()->get(['id', 'symbol']),
            'statuses' => ProjectServiceStatus::query()->ordered()->get(['id', 'name']),
            'newValue' => (string) $total->toScale(2),
        ])
            ->title($this->amendment === null ? __('New amendment') : __('Edit amendment'))
            ->layoutData(['back' => route('projects.projects.show', ['project' => $this->project, 'tab' => 'contract']), 'bottomNav' => false]);
    }
}
