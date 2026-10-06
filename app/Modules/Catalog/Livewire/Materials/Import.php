<?php

namespace App\Modules\Catalog\Livewire\Materials;

use App\Modules\Catalog\Imports\MaterialImport;
use App\Support\Imports\ImportDefinition;
use App\Support\Imports\ImportFile;
use App\Support\Imports\WithImport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Import materials')]
class Import extends Component
{
    use WithImport;

    public function mount(): void
    {
        $this->authorize('catalog.materials.import');
    }

    protected function importDefinition(): ImportDefinition
    {
        return app(MaterialImport::class);
    }

    protected function importPermission(): string
    {
        return 'catalog.materials.import';
    }

    protected function importName(): string
    {
        return 'materials';
    }

    public function render(): View
    {
        return view('livewire.catalog.import', [
            ...$this->previewData(),
            'columns' => $this->importDefinition()->columns(),
            'backUrl' => route('catalog.materials.index'),
            'notes' => [
                __('Rows are matched by code. A known code updates that material; a new code creates one.'),
                __('Category accepts a code or name; unit accepts a code, symbol or name. Both must be active.'),
                __('Blank cells keep the current value of an existing material. A dash (-) in rate clears it.'),
                __('Up to :max rows per file. Rows with errors are skipped and can be downloaded afterwards.', ['max' => ImportFile::MAX_ROWS]),
            ],
        ])->layoutData(['back' => route('catalog.materials.index'), 'bottomNav' => false]);
    }
}
