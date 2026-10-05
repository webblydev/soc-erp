<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Actions\SaveLocation;
use App\Modules\Foundation\Actions\SetLocationActive;
use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Division → district → thana → area tree (docs/01 §5.9). Children load when a node expands.
 */
#[Title('Locations')]
class Locations extends Component
{
    /** @var list<int> */
    public array $expanded = [];

    public string $search = '';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $parent_id = null;

    public string $name = '';

    public string $name_bn = '';

    public function mount(): void
    {
        $this->authorize('admin.locations.view');
    }

    public function toggle(int $id): void
    {
        $this->authorize('admin.locations.view');

        $this->expanded = in_array($id, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$id]))
            : [...$this->expanded, $id];
    }

    public function addChild(?int $parentId = null): void
    {
        $this->authorize('admin.locations.create');

        $this->reset('editingId', 'name', 'name_bn');
        $this->parent_id = $parentId;
        $this->resetErrorBag();
        $this->dispatch('open-sheet-location');
    }

    public function edit(int $id): void
    {
        $this->authorize('admin.locations.update');

        $location = Location::query()->findOrFail($id);
        $this->editingId = $location->id;
        $this->parent_id = $location->parent_id;
        $this->name = $location->name;
        $this->name_bn = (string) $location->name_bn;
        $this->resetErrorBag();
        $this->dispatch('open-sheet-location');
    }

    public function save(SaveLocation $saveLocation): void
    {
        if ($this->editingId === null) {
            $this->authorize('admin.locations.create');
            $saveLocation->handle(['name' => $this->name, 'name_bn' => $this->name_bn ?: null, 'parent_id' => $this->parent_id]);

            if ($this->parent_id !== null && ! in_array($this->parent_id, $this->expanded, true)) {
                $this->expanded[] = $this->parent_id;
            }
        } else {
            $this->authorize('admin.locations.update');
            $saveLocation->handle(['name' => $this->name, 'name_bn' => $this->name_bn ?: null], Location::query()->findOrFail($this->editingId));
        }

        $this->dispatch('close-sheet-location');
        $this->dispatch('toast', type: 'success', description: __('Location saved.'));
    }

    public function toggleActive(int $id, SetLocationActive $setLocationActive): void
    {
        $this->authorize('admin.locations.deactivate');

        $location = Location::query()->findOrFail($id);
        $setLocationActive->handle($location, ! $location->is_active);
    }

    public function render(): View
    {
        $this->authorize('admin.locations.view');

        $term = trim($this->search);

        return view('livewire.admin.locations', [
            'roots' => Location::query()->whereNull('parent_id')->withCount('children')->orderBy('name')->get(),
            'childrenByParent' => Location::query()->whereIn('parent_id', $this->expanded)->withCount('children')->orderBy('name')->get()->groupBy('parent_id'),
            'results' => mb_strlen($term) >= 2
                ? Location::query()
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($term)).'%'])
                    ->orderBy('full_path')->limit(50)->get()
                : null,
            'parentPath' => $this->parent_id !== null ? Location::query()->whereKey($this->parent_id)->value('full_path') : null,
            'areaLevelId' => LocationLevel::query()->where('code', LocationLevel::AREA)->value('id'),
        ]);
    }
}
