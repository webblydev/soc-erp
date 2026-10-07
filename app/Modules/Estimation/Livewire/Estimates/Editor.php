<?php

namespace App\Modules\Estimation\Livewire\Estimates;

use App\Models\User;
use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Estimation\Actions\ImportEstimateLines;
use App\Modules\Estimation\Actions\SaveEstimate;
use App\Modules\Estimation\Actions\SubmitEstimate;
use App\Modules\Estimation\Models\CostCategory;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Services\EstimateTotals;
use App\Modules\Estimation\Services\QuantityCalculator;
use App\Modules\Projects\Models\Project;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Estimate editor (docs/05 §5.2, spec §5.2): header, the work sheet grouped by sections and the
 * material lines. Desktop gets a spreadsheet-like grid with keyboard shortcuts; mobile gets line
 * cards that open a bottom sheet. Quantities and totals are shown live; SaveEstimate stores them.
 */
class Editor extends Component
{
    use WithFileUploads;

    private const HEADER_FIELDS = ['title', 'site_address', 'estimate_date', 'prepared_by', 'checked_by', 'overhead_pct', 'profit_pct', 'vat_pct', 'notes'];

    private const LINE_FIELDS = ['id', 'line_no', 'work_item_id', 'description', 'level', 'location', 'measurement_formula', 'nos', 'length', 'width', 'height', 'deduction', 'unit_id', 'quantity', 'rate', 'cost_category_id', 'remarks'];

    private const MATERIAL_FIELDS = ['id', 'material_id', 'material_name', 'unit_id', 'estimated_qty', 'wastage_pct', 'rate', 'purpose'];

    public ?Estimate $estimate = null;

    #[Url(as: 'project', except: '')]
    public string $projectNumber = '';

    #[Url(as: 'kind', except: '')]
    public string $kindCode = '';

    public int|string|null $project_id = null;

    public int|string|null $estimate_kind_id = null;

    public string $title = '';

    public string $site_address = '';

    public string $estimate_date = '';

    public int|string|null $prepared_by = null;

    public int|string|null $checked_by = null;

    public string $overhead_pct = '';

    public string $profit_pct = '';

    public string $vat_pct = '';

    public string $notes = '';

    /** @var array<int, array{id: int|null, name: string, lines: array<int, array<string, mixed>>}> */
    public array $sections = [];

    /** @var array<int, array<string, mixed>> */
    public array $materialLines = [];

    public string $tab = 'work';

    /** "section.line" of the line open in the mobile sheet. */
    public ?string $editing = null;

    public string $copyFrom = '';

    /** @var TemporaryUploadedFile|null */
    public $importFile = null;

    public function mount(?Estimate $estimate = null): void
    {
        if ($estimate === null || ! $estimate->exists) {
            $this->authorize('create', Estimate::class);

            $project = $this->projectNumber !== '' ? $this->projects()->firstWhere('project_number', $this->projectNumber) : null;
            $this->project_id = $project?->id;
            $this->site_address = (string) $project?->site_address;
            $this->estimate_kind_id = EstimateKind::query()->where('code', $this->kindCode !== '' ? $this->kindCode : EstimateKind::BOQ)->value('id');
            $this->estimate_date = today()->toDateString();
            $this->prepared_by = $this->actor()->employee_id;
            $this->sections = [$this->blankSection()];

            return;
        }

        $this->authorize('update', $estimate);

        if (! $estimate->hasStatus(EstimateStatus::DRAFT) && ! $estimate->hasStatus(EstimateStatus::REJECTED)) {
            session()->flash('error', __('This estimate is :status; revise it to make changes.', ['status' => $estimate->status->name]));
            $this->redirectRoute('estimation.estimates.show', $estimate, navigate: true);

            return;
        }

        $this->estimate = $estimate;
        $this->project_id = $estimate->project_id;
        $this->estimate_kind_id = $estimate->estimate_kind_id;

        foreach (self::HEADER_FIELDS as $field) {
            $value = $estimate->getAttribute($field);
            $this->{$field} = match (true) {
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                in_array($field, ['prepared_by', 'checked_by'], true) => $value,
                in_array($field, ['overhead_pct', 'profit_pct', 'vat_pct'], true) => self::plain($value),
                default => (string) $value,
            };
        }

        $this->fillLinesFrom(SaveEstimate::linesOf($estimate, withIds: true));
    }

    /**
     * Picking a work item fills the description, unit, rate and formula of a fresh line.
     */
    public function updatedSections(mixed $value, ?string $key = null): void
    {
        if ($key === null || ! str_ends_with($key, '.work_item_id') || ! is_numeric($value)) {
            return;
        }

        [$s, , $l] = explode('.', $key);
        $item = WorkItem::query()->find((int) $value);

        if ($item === null || ! isset($this->sections[(int) $s]['lines'][(int) $l])) {
            return;
        }

        $line = &$this->sections[(int) $s]['lines'][(int) $l];
        $line['description'] = $line['description'] !== '' ? $line['description'] : $item->name;
        $line['unit_id'] = $item->unit_id;
        $line['measurement_formula'] = $item->measurement_formula->value;
        $line['rate'] = self::plain($item->standard_rate);
    }

    public function addSection(): void
    {
        $this->sections[] = [...$this->blankSection(), 'name' => __('Section :n', ['n' => count($this->sections) + 1])];
    }

    public function removeSection(int $section): void
    {
        unset($this->sections[$section]);
        $this->sections = array_values($this->sections);

        if ($this->sections === []) {
            $this->sections = [$this->blankSection()];
        }
    }

    public function addLine(int $section, ?int $after = null): void
    {
        if (! isset($this->sections[$section])) {
            return;
        }

        $lines = $this->sections[$section]['lines'];
        $position = $after === null ? count($lines) : min($after + 1, count($lines));
        array_splice($lines, $position, 0, [$this->blankLine()]);
        $this->sections[$section]['lines'] = $lines;
        $this->editing = $section.'.'.$position;
    }

    /**
     * Open a line in the mobile sheet.
     */
    public function editLine(int $section, int $line): void
    {
        if (isset($this->sections[$section]['lines'][$line])) {
            $this->editing = $section.'.'.$line;
            $this->dispatch('open-sheet-estimate-line');
        }
    }

    public function addLineOnMobile(int $section): void
    {
        $this->addLine($section);
        $this->dispatch('open-sheet-estimate-line');
    }

    public function duplicateLine(int $section, int $line): void
    {
        if (! isset($this->sections[$section]['lines'][$line])) {
            return;
        }

        $copy = [...$this->sections[$section]['lines'][$line], 'id' => null, 'line_no' => ''];
        array_splice($this->sections[$section]['lines'], $line + 1, 0, [$copy]);
    }

    public function removeLine(int $section, int $line): void
    {
        unset($this->sections[$section]['lines'][$line]);
        $this->sections[$section]['lines'] = array_values($this->sections[$section]['lines'] ?? []);
        $this->editing = null;
    }

    public function moveLine(int $section, int $line, int $direction): void
    {
        $target = $line + ($direction < 0 ? -1 : 1);
        $lines = $this->sections[$section]['lines'] ?? [];

        if (! isset($lines[$line], $lines[$target])) {
            return;
        }

        [$lines[$line], $lines[$target]] = [$lines[$target], $lines[$line]];
        $this->sections[$section]['lines'] = $lines;
    }

    public function addMaterial(): void
    {
        $this->materialLines[] = $this->blankMaterial();
    }

    public function removeMaterial(int $index): void
    {
        unset($this->materialLines[$index]);
        $this->materialLines = array_values($this->materialLines);
    }

    /**
     * Append the lines of another estimate the user can see (spec E8).
     */
    public function copyLines(): void
    {
        $source = Estimate::query()->visibleTo($this->actor())->where('estimate_number', trim($this->copyFrom))->first();

        if ($source === null) {
            $this->addError('copyFrom', __('No estimate with that number.'));

            return;
        }

        $this->authorize('view', $source);
        $this->fillLinesFrom(SaveEstimate::linesOf($source), append: true);
        $this->copyFrom = '';
        $this->dispatch('close-sheet-estimate-copy');
    }

    /**
     * Append lines read from an Excel file in the export layout (spec E20).
     */
    public function importLines(ImportEstimateLines $importLines): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']], [], ['importFile' => __('file')]);

        try {
            $this->fillLinesFrom($importLines->read($this->importFile->getRealPath()), append: true);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        }

        $this->reset('importFile');
        $this->dispatch('close-sheet-estimate-import');
    }

    public function save(SaveEstimate $saveEstimate): void
    {
        $estimate = $this->persist($saveEstimate);

        if ($estimate !== null) {
            session()->flash('success', __('Estimate :number saved.', ['number' => $estimate->estimate_number]));
            $this->redirectRoute('estimation.estimates.show', $estimate, navigate: true);
        }
    }

    public function saveAndSubmit(SaveEstimate $saveEstimate, SubmitEstimate $submitEstimate): void
    {
        $estimate = $this->persist($saveEstimate);

        if ($estimate === null) {
            return;
        }

        try {
            $submitEstimate->handle($this->actor(), $estimate);
        } catch (ValidationException $exception) {
            $this->estimate = $estimate;
            $this->setErrorBag($exception->validator->errors());

            return;
        }

        session()->flash('success', __('Estimate :number submitted for approval.', ['number' => $estimate->estimate_number]));
        $this->redirectRoute('estimation.estimates.show', $estimate, navigate: true);
    }

    private function persist(SaveEstimate $saveEstimate): ?Estimate
    {
        $this->resetErrorBag();
        $input = [
            ...$this->only(self::HEADER_FIELDS),
            'estimate_kind_id' => $this->estimate_kind_id,
            'sections' => array_map(fn (array $section): array => [
                'id' => $section['id'],
                'name' => $section['name'],
                'lines' => array_map(fn (array $line): array => collect($line)->only(self::LINE_FIELDS)->all(), $section['lines']),
            ], $this->sections),
            'material_lines' => array_map(fn (array $line): array => collect($line)->only(self::MATERIAL_FIELDS)->all(), $this->materialLines),
        ];

        if ($this->estimate !== null) {
            return $saveEstimate->handle($this->actor(), $input, $this->estimate);
        }

        $project = $this->projects()->firstWhere('id', (int) $this->project_id);

        if ($project === null) {
            $this->addError('project_id', __('Choose the project.'));

            return null;
        }

        return $saveEstimate->handle($this->actor(), $input, project: $project);
    }

    /**
     * Load lines in SaveEstimate's input shape into the form, replacing or appending.
     *
     * @param  array{sections: list<array<string, mixed>>, material_lines: list<array<string, mixed>>}  $data
     */
    private function fillLinesFrom(array $data, bool $append = false): void
    {
        $text = fn (mixed $value): mixed => is_bool($value) || $value === null || is_int($value) ? $value : self::plain($value);

        $sections = array_map(fn (array $section): array => [
            'id' => $section['id'] ?? null,
            'name' => (string) ($section['name'] ?? ''),
            'lines' => array_map(fn (array $line): array => [
                ...$this->blankLine(),
                ...array_map($text, $line),
                'id' => $line['id'] ?? null,
                'line_no' => (string) ($line['line_no'] ?? ''),
                'description' => (string) ($line['description'] ?? ''),
                'level' => (string) ($line['level'] ?? ''),
                'location' => (string) ($line['location'] ?? ''),
                'remarks' => (string) ($line['remarks'] ?? ''),
            ], $section['lines']),
        ], $data['sections']);

        $materials = array_map(fn (array $line): array => [
            ...$this->blankMaterial(),
            ...array_map($text, $line),
            'id' => $line['id'] ?? null,
            'material_name' => (string) ($line['material_name'] ?? ''),
            'purpose' => (string) ($line['purpose'] ?? ''),
        ], $data['material_lines']);

        if ($append) {
            $current = array_values(array_filter($this->sections, fn (array $section): bool => $section['id'] !== null || $section['name'] !== ''
                || collect($section['lines'])->contains(fn (array $line): bool => $line['id'] !== null || $line['description'] !== '')));
            $sections = [...$current, ...$sections];
            $materials = [...$this->materialLines, ...$materials];
        }

        $this->sections = $sections ?: [$this->blankSection()];
        $this->materialLines = $materials;
    }

    /**
     * Live quantities, amounts and totals from the unsaved form.
     *
     * @return array{lines: array<string, array{quantity: string|null, amount: string|null}>, sections: list<string>, materials: list<array{total_qty: string|null, amount: string|null}>, totals: array<string, string>}
     */
    private function preview(?EstimateKind $kind): array
    {
        $number = fn (mixed $value): ?string => is_numeric($clean = str_replace(',', '', trim((string) $value))) ? $clean : null;
        $lines = [];
        $sectionTotals = [];
        $work = BigDecimal::zero();

        foreach ($this->sections as $s => $section) {
            $sectionTotal = BigDecimal::zero();

            foreach ($section['lines'] as $l => $line) {
                $formula = MeasurementFormula::tryFrom((string) ($line['measurement_formula'] ?? '')) ?? MeasurementFormula::Manual;
                $manual = $number($line['quantity'] ?? null);
                $quantity = $formula === MeasurementFormula::Manual && $manual === null
                    ? null
                    : QuantityCalculator::quantity($formula, $number($line['nos'] ?? null), $number($line['length'] ?? null), $number($line['width'] ?? null), $number($line['height'] ?? null), $manual);
                $amount = $quantity !== null ? QuantityCalculator::amount($quantity, $number($line['rate'] ?? null), (bool) ($line['deduction'] ?? false)) : null;
                $lines[$s.'.'.$l] = ['quantity' => $quantity, 'amount' => $amount];
                $sectionTotal = $sectionTotal->plus($amount ?? '0');
            }

            $sectionTotals[] = (string) $sectionTotal->toScale(2);
            $work = $work->plus($sectionTotal);
        }

        $materials = [];
        $materialTotal = BigDecimal::zero();

        foreach ($this->materialLines as $line) {
            $estimated = $number($line['estimated_qty'] ?? null);
            $total = $estimated !== null ? QuantityCalculator::withWastage($estimated, $number($line['wastage_pct'] ?? null)) : null;
            $amount = $total !== null ? QuantityCalculator::amount($total, $number($line['rate'] ?? null)) : null;
            $materials[] = ['total_qty' => $total, 'amount' => $amount];
            $materialTotal = $materialTotal->plus($amount ?? '0');
        }

        $subtotal = $kind !== null && ! $kind->has_work_lines ? $materialTotal : $work;

        return [
            'lines' => $lines,
            'sections' => $sectionTotals,
            'materials' => $materials,
            'totals' => app(EstimateTotals::class)->calculate((string) $subtotal, $number($this->overhead_pct), $number($this->profit_pct), $number($this->vat_pct)),
        ];
    }

    /**
     * @return Collection<int, Project>
     */
    private function projects(): Collection
    {
        return Project::query()->visibleTo($this->actor())->open()->orderBy('project_number')->get(['id', 'project_number', 'name', 'site_address'])
            ->filter(fn (Project $project): bool => $this->actor()->can('createEstimate', $project))->values();
    }

    /**
     * @return array{id: null, name: string, lines: list<array<string, mixed>>}
     */
    private function blankSection(): array
    {
        return ['id' => null, 'name' => '', 'lines' => [$this->blankLine()]];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankLine(): array
    {
        return [
            'id' => null, 'line_no' => '', 'work_item_id' => null, 'description' => '', 'level' => '', 'location' => '',
            'measurement_formula' => MeasurementFormula::NosLWH->value, 'nos' => '1', 'length' => '', 'width' => '', 'height' => '',
            'deduction' => false, 'unit_id' => null, 'quantity' => '', 'rate' => '', 'cost_category_id' => null, 'remarks' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankMaterial(): array
    {
        return ['id' => null, 'material_id' => null, 'material_name' => '', 'unit_id' => null, 'estimated_qty' => '', 'wastage_pct' => '', 'rate' => '', 'purpose' => ''];
    }

    /**
     * A stored decimal as typed text: "12.5000" → "12.5", null → "".
     */
    private static function plain(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        $text = (string) $value;

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $kind = EstimateKind::query()->find($this->estimate_kind_id);
        $lineValues = collect($this->sections)->pluck('lines')->flatten(1);
        [$editSection, $editLine] = $this->editing !== null ? array_map('intval', explode('.', $this->editing)) : [null, null];

        return view('livewire.estimation.estimates.editor', [
            'kind' => $kind,
            'kinds' => EstimateKind::query()->active()->ordered()->get(['id', 'name', 'code']),
            'projects' => $this->estimate === null ? $this->projects() : collect(),
            'preview' => $this->preview($kind),
            'formulas' => MeasurementFormula::cases(),
            'units' => Unit::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $lineValues->pluck('unit_id')->merge(collect($this->materialLines)->pluck('unit_id'))->filter()))->ordered()->get(['id', 'name', 'symbol']),
            'workItems' => WorkItem::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $lineValues->pluck('work_item_id')->filter()))->orderBy('code')->get(['id', 'code', 'name']),
            'materials' => Material::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', collect($this->materialLines)->pluck('material_id')->filter()))->orderBy('name')->get(['id', 'name']),
            'costCategories' => CostCategory::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $lineValues->pluck('cost_category_id')->filter()))->ordered()->get(['id', 'name']),
            'editSection' => $editSection,
            'editLine' => $editLine,
            'canSubmit' => $this->estimate === null ? $this->actor()->can('estimation.estimates.submit') : $this->actor()->can('submit', $this->estimate),
        ])
            ->title($this->estimate === null ? __('New estimate') : __('Edit :number', ['number' => $this->estimate->estimate_number]))
            ->layoutData(['back' => $this->estimate === null ? route('estimation.estimates.index') : route('estimation.estimates.show', $this->estimate), 'bottomNav' => false]);
    }
}
