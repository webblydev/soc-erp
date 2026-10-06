<?php

namespace App\Modules\Catalog\Livewire\WorkItems;

use App\Modules\Catalog\Imports\WorkItemImport;
use App\Support\Imports\ImportDefinition;
use App\Support\Imports\ImportFile;
use App\Support\Imports\WithImport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Import work items')]
class Import extends Component
{
    use WithImport;

    public function mount(): void
    {
        $this->authorize('catalog.work_items.import');
    }

    protected function importDefinition(): ImportDefinition
    {
        return app(WorkItemImport::class);
    }

    protected function importPermission(): string
    {
        return 'catalog.work_items.import';
    }

    protected function importName(): string
    {
        return 'work-items';
    }

    public function render(): View
    {
        return view('livewire.catalog.import', [
            ...$this->previewData(),
            'columns' => $this->importDefinition()->columns(),
            'backUrl' => route('catalog.work-items.index'),
            'notes' => [
                __('Rows are matched by code. A known code updates that work item; a new code creates one.'),
                __('Category accepts a code or name; unit accepts a code, symbol or name. Both must be active.'),
                __('Blank cells keep the current value of an existing work item. A dash (-) in rate clears it.'),
                __('Up to :max rows per file. Rows with errors are skipped and can be downloaded afterwards.', ['max' => ImportFile::MAX_ROWS]),
            ],
        ])->layoutData(['back' => route('catalog.work-items.index'), 'bottomNav' => false]);
    }
}
