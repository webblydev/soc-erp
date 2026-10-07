<?php

namespace App\Modules\Estimation\Livewire\Mb;

use App\Models\User;
use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Estimation\Actions\RecordMeasurement;
use App\Modules\Estimation\Actions\UpdateMeasurement;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Services\MeasurementLimits;
use App\Modules\Estimation\Services\QuantityCalculator;
use App\Modules\Foundation\Concerns\SavesFromDetailModal;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * New / edit MB entry (docs/05 §5.6, spec §5.7): BOQ line picker, measurements, a live progress
 * card against the BOQ quantity, and the rate locked to its default without site.mb.edit_rate.
 */
class Form extends Component
{
    use SavesFromDetailModal;

    private const FIELDS = ['estimate_line_id', 'mb_book_no', 'mb_page_no', 'measured_on', 'measured_by', 'work_item_id', 'description', 'location', 'measurement_formula', 'nos', 'length', 'width', 'height', 'unit_id', 'quantity', 'rate', 'remarks'];

    public ?MeasurementEntry $entry = null;

    #[Url(as: 'project', except: '')]
    public string $projectNumber = '';

    public int|string|null $project_id = null;

    public int|string|null $estimate_line_id = null;

    public string $mb_book_no = '';

    public string $mb_page_no = '';

    public string $measured_on = '';

    public int|string|null $measured_by = null;

    public int|string|null $work_item_id = null;

    public string $description = '';

    public string $location = '';

    public string $measurement_formula = 'manual';

    public string $nos = '';

    public string $length = '';

    public string $width = '';

    public string $height = '';

    public int|string|null $unit_id = null;

    public string $quantity = '';

    public string $rate = '';

    public string $remarks = '';

    public function mount(?MeasurementEntry $entry = null): void
    {
        if ($entry === null || ! $entry->exists) {
            $this->authorize('create', MeasurementEntry::class);
            $this->project_id = $this->projectNumber !== '' ? $this->projects()->firstWhere('project_number', $this->projectNumber)?->id : null;
            $this->measured_on = today()->toDateString();
            $this->measured_by = $this->actor()->employee_id;

            return;
        }

        $this->authorize('update', $entry);

        if (! $entry->hasStatus(MbStatus::RECORDED) && ! $entry->hasStatus(MbStatus::REJECTED)) {
            session()->flash('error', __('A :status entry cannot be edited.', ['status' => $entry->status->name]));
            $this->redirectRoute('site.mb.show', $entry, navigate: true);

            return;
        }

        $this->entry = $entry;
        $this->project_id = $entry->project_id;

        foreach (self::FIELDS as $field) {
            $value = $entry->getAttribute($field);
            $this->{$field} = match (true) {
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                $value instanceof MeasurementFormula => $value->value,
                in_array($field, ['estimate_line_id', 'measured_by', 'work_item_id', 'unit_id'], true) => $value,
                is_numeric($value) && str_contains((string) $value, '.') => rtrim(rtrim((string) $value, '0'), '.'),
                default => (string) $value,
            };
        }
    }

    /**
     * Picking a BOQ line fills the item, description, unit, formula and rate.
     */
    public function updatedEstimateLineId(mixed $value): void
    {
        $line = is_numeric($value) ? $this->boqLines()->firstWhere('id', (int) $value) : null;

        if ($line === null) {
            return;
        }

        $this->work_item_id = $line->work_item_id;
        $this->description = $line->description;
        $this->unit_id = $line->unit_id;
        $this->measurement_formula = $line->measurement_formula->value;
        $this->rate = $line->rate !== null ? rtrim(rtrim((string) $line->rate, '0'), '.') : '';
    }

    public function updatedWorkItemId(mixed $value): void
    {
        if ($this->estimate_line_id || ! is_numeric($value) || ($item = WorkItem::query()->find((int) $value)) === null) {
            return;
        }

        $this->description = $this->description !== '' ? $this->description : $item->name;
        $this->unit_id = $item->unit_id;
        $this->measurement_formula = $item->measurement_formula->value;
        $this->rate = $item->standard_rate !== null ? rtrim(rtrim((string) $item->standard_rate, '0'), '.') : '';
    }

    public function save(RecordMeasurement $record, UpdateMeasurement $update): void
    {
        $this->resetErrorBag();
        $input = $this->only(self::FIELDS);

        if ($this->entry !== null) {
            $entry = $update->handle($this->actor(), $this->entry, $input);
            $warnings = $update->warnings;
        } else {
            $project = $this->projects()->firstWhere('id', (int) $this->project_id);

            if ($project === null) {
                $this->addError('project_id', __('Choose the project.'));

                return;
            }

            $entry = $record->handle($this->actor(), $project, $input);
            $warnings = $record->warnings;
        }

        if ($warnings !== []) {
            session()->flash('warning', implode(' ', $warnings));
        }

        $this->redirectAfterSave(__('Measurement :number saved.', ['number' => $entry->mb_number]), 'site.mb.show', $entry);
    }

    /**
     * @return Collection<int, Project>
     */
    private function projects(): Collection
    {
        return Project::query()->visibleTo($this->actor())->open()->orderBy('project_number')->get(['id', 'project_number', 'name', 'project_status_id'])
            ->filter(fn (Project $project): bool => $this->actor()->can('recordMeasurement', $project))->values();
    }

    /**
     * @return Collection<int, EstimateLine>
     */
    private function boqLines(): Collection
    {
        $project = Project::query()->find($this->project_id);

        return $project === null ? collect() : app(MeasurementLimits::class)->boqLines($project)->with(['unit:id,symbol', 'estimate:id,estimate_number'])->get();
    }

    /**
     * @return array{boq: string, previous: string, this: string, cumulative: string, pct: string|null, state: string, limit_pct: int}|null
     */
    private function progress(): ?array
    {
        $line = is_numeric($this->estimate_line_id) ? EstimateLine::query()->find((int) $this->estimate_line_id) : null;
        $formula = MeasurementFormula::tryFrom($this->measurement_formula);

        if ($line === null || $formula === null) {
            return null;
        }

        $clean = fn (string $value): ?string => is_numeric($value = str_replace(',', '', trim($value))) ? $value : null;
        $quantity = QuantityCalculator::quantity($formula, $clean($this->nos), $clean($this->length), $clean($this->width), $clean($this->height), $clean($this->quantity) ?? '0');

        return app(MeasurementLimits::class)->check($line, $quantity, $this->entry);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $project = Project::query()->find($this->project_id);
        $line = is_numeric($this->estimate_line_id) ? EstimateLine::query()->find((int) $this->estimate_line_id) : null;
        $defaultRate = $line->rate ?? WorkItem::query()->whereKey($this->work_item_id)->value('standard_rate');

        return view('livewire.estimation.mb.form', [
            'projects' => $this->entry === null ? $this->projects() : collect(),
            'project' => $project,
            'boqLines' => $this->boqLines(),
            'progress' => $this->progress(),
            'rateLocked' => $defaultRate !== null && ($project === null || ! $this->actor()->can('editMeasurementRate', $project)),
            'formulas' => MeasurementFormula::cases(),
            'units' => Unit::query()->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->unit_id))->ordered()->get(['id', 'symbol']),
            'workItems' => WorkItem::query()->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->work_item_id))->orderBy('code')->get(['id', 'code', 'name']),
        ])
            ->title($this->entry === null ? __('New measurement') : __('Edit :number', ['number' => $this->entry->mb_number]))
            ->layoutData(['back' => $this->entry === null ? route('site.mb.index') : route('site.mb.show', $this->entry), 'bottomNav' => false]);
    }
}
