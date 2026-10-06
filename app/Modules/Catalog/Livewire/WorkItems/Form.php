<?php

namespace App\Modules\Catalog\Livewire\WorkItems;

use App\Modules\Catalog\Actions\SaveWorkItem;
use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\WorkItem;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Form extends Component
{
    public ?WorkItem $workItem = null;

    public string $code = '';

    public string $name = '';

    public int|string|null $work_item_category_id = null;

    public int|string|null $unit_id = null;

    public string $measurement_formula = '';

    public string $standard_rate = '';

    public string $specification = '';

    public bool $is_active = true;

    public function mount(?WorkItem $workItem = null): void
    {
        if ($workItem === null || ! $workItem->exists) {
            $this->authorize('catalog.work_items.create');
            $this->measurement_formula = MeasurementFormula::NosLWH->value;

            return;
        }

        $this->authorize('catalog.work_items.update');

        $this->workItem = $workItem;
        $this->fill($workItem->only(['code', 'name', 'work_item_category_id', 'unit_id', 'is_active']));
        $this->measurement_formula = $workItem->measurement_formula->value;
        $this->standard_rate = $workItem->standard_rate !== null ? rtrim(rtrim($workItem->standard_rate, '0'), '.') : '';
        $this->specification = (string) $workItem->specification;
    }

    public function save(SaveWorkItem $saveWorkItem): void
    {
        $this->authorize($this->workItem === null ? 'catalog.work_items.create' : 'catalog.work_items.update');

        $saveWorkItem->handle($this->only(SaveWorkItem::FIELDS), $this->workItem);

        session()->flash('success', $this->workItem === null ? __('Work item created.') : __('Work item saved.'));

        $this->redirectRoute('catalog.work-items.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.catalog.work-items.form', ['formulas' => MeasurementFormula::cases()])
            ->title($this->workItem === null ? __('New work item') : __('Edit work item'))
            ->layoutData(['back' => route('catalog.work-items.index'), 'bottomNav' => false]);
    }
}
