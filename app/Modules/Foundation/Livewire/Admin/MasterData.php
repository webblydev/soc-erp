<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Models\User;
use App\Modules\Foundation\Actions\DeleteLookup;
use App\Modules\Foundation\Actions\ReorderLookup;
use App\Modules\Foundation\Actions\SaveLookup;
use App\Support\Lookups\LookupRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Generic editor for every table in config/lookups.php (docs/01 §5.8).
 */
#[Title('Master data')]
class MasterData extends Component
{
    public ?string $table = null;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(?string $table = null): void
    {
        $registry = $this->registry();

        if ($table === null) {
            abort_if($registry->visibleTo($this->actor()) === [], 403);

            return;
        }

        abort_unless(array_key_exists($table, $registry->all()), 404);
        abort_unless($registry->allows($this->actor(), $table, 'view'), 403);

        $this->table = $table;
    }

    public function create(): void
    {
        $this->authorizeTable('create');

        $this->editingId = null;
        $this->form = $this->blankForm();
        $this->resetErrorBag();
        $this->dispatch('open-sheet-lookup-row');
    }

    public function edit(int $id): void
    {
        $this->authorizeTable('view');

        $row = $this->findRow($id);
        $this->editingId = $row->getKey();
        $this->form = array_merge($this->blankForm(), $row->only(array_keys($this->blankForm())));
        $this->resetErrorBag();
        $this->dispatch('open-sheet-lookup-row');
    }

    public function save(SaveLookup $saveLookup): void
    {
        $row = $this->editingId !== null ? $this->findRow($this->editingId) : null;

        $this->authorizeTable($row === null ? 'create' : 'update');

        if ($row !== null && (bool) $row->getAttribute('is_active') !== (bool) ($this->form['is_active'] ?? true)) {
            $this->authorizeTable('deactivate');
        }

        try {
            $saveLookup->handle((string) $this->table, $this->form, $row);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ['form.'.$key => $messages])->all(),
            );
        }

        $this->dispatch('close-sheet-lookup-row');
        $this->dispatch('toast', type: 'success', description: __('Saved.'));
    }

    public function delete(DeleteLookup $deleteLookup): void
    {
        $this->authorizeTable('deactivate');

        try {
            $deleteLookup->handle((string) $this->table, $this->findRow((int) $this->editingId));
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->editingId = null;
        $this->dispatch('close-sheet-lookup-row');
        $this->dispatch('toast', type: 'success', description: __('Deleted.'));
    }

    public function sort(int $id, int $position, ReorderLookup $reorderLookup): void
    {
        $this->authorizeTable('update');
        $this->findRow($id);

        $reorderLookup->handle((string) $this->table, $id, $position);
    }

    public function render(): View
    {
        $registry = $this->registry();
        $visible = $registry->visibleTo($this->actor());

        return view('livewire.admin.master-data', [
            'groups' => collect($visible)->groupBy('module', preserveKeys: true),
            'entry' => $this->table !== null ? $registry->get($this->table) : null,
            'rows' => $this->table !== null ? $registry->modelFor($this->table)->newQuery()->orderBy('sort_order')->orderBy('name')->get() : collect(),
            'colors' => SaveLookup::COLORS,
            'can' => fn (string $action): bool => $this->table !== null && $registry->allows($this->actor(), $this->table, $action),
        ])->layoutData(['back' => $this->table !== null ? route('admin.master-data.index') : null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        $form = ['code' => '', 'name' => '', 'description' => '', 'color' => '', 'is_active' => true];

        foreach ($this->registry()->get((string) $this->table)['extra_fields'] as $field => $definition) {
            $form[$field] = $definition['type'] === 'bool' ? false : '';
        }

        return $form;
    }

    private function findRow(int $id): Model
    {
        $row = $this->registry()->modelFor((string) $this->table)->newQuery()->find($id);
        abort_if($row === null, 404);

        return $row;
    }

    private function authorizeTable(string $action): void
    {
        abort_unless($this->table !== null && $this->registry()->allows($this->actor(), $this->table, $action), 403);
    }

    private function registry(): LookupRegistry
    {
        return app(LookupRegistry::class);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
