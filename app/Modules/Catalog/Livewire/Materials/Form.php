<?php

namespace App\Modules\Catalog\Livewire\Materials;

use App\Modules\Catalog\Actions\SaveMaterial;
use App\Modules\Catalog\Models\Material;
use App\Modules\Foundation\Concerns\SavesFromDetailModal;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Form extends Component
{
    use SavesFromDetailModal;

    public ?Material $material = null;

    public string $code = '';

    public string $name = '';

    public int|string|null $material_category_id = null;

    public int|string|null $unit_id = null;

    public string $standard_rate = '';

    public bool $is_active = true;

    public function mount(?Material $material = null): void
    {
        if ($material === null || ! $material->exists) {
            $this->authorize('catalog.materials.create');

            return;
        }

        $this->authorize('catalog.materials.update');

        $this->material = $material;
        $this->fill($material->only(['code', 'name', 'material_category_id', 'unit_id', 'is_active']));
        $this->standard_rate = $material->standard_rate !== null ? rtrim(rtrim($material->standard_rate, '0'), '.') : '';
    }

    public function save(SaveMaterial $saveMaterial): void
    {
        $this->authorize($this->material === null ? 'catalog.materials.create' : 'catalog.materials.update');

        $saveMaterial->handle($this->only(SaveMaterial::FIELDS), $this->material);

        $this->redirectAfterSave($this->material === null ? __('Material created.') : __('Material saved.'), 'catalog.materials.index');
    }

    public function render(): View
    {
        return view('livewire.catalog.materials.form')
            ->title($this->material === null ? __('New material') : __('Edit material'))
            ->layoutData(['back' => route('catalog.materials.index'), 'bottomNav' => false]);
    }
}
