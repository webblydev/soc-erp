<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Models\User;
use App\Modules\Foundation\Actions\DeleteLookup;
use App\Modules\Foundation\Actions\ReorderLookup;
use App\Modules\Foundation\Actions\SaveLookup;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use App\Support\Lookups\LookupRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Generic editor for every table in config/lookups.php (docs/01 §5.8). Each table is a plain page
 * reached from the sidebar tree and listed as an ERP table on desktop; the index lists the tables
 * the user may open. Rows are reordered by drag only while the list shows the plain sort order.
 */
#[Title('Master data')]
class MasterData extends Component
{
    use WithBulkActions, WithListing;

    #[Locked]
    public ?string $table = null;

    #[Locked]
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

        foreach ($this->registry()->get((string) $this->table)['extra_fields'] as $field => $definition) {
            if ($definition['type'] === 'list') {
                $this->form[$field] = implode(', ', (array) $this->form[$field]);
            }
        }
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

    /**
     * Drag on a desktop page: the position is within the page, so add the rows on earlier pages.
     */
    public function sortOnPage(int $id, int $position, ReorderLookup $reorderLookup): void
    {
        $this->sort($id, $position + ($this->getPage() - 1) * $this->perPage, $reorderLookup);
    }

    public function export(): BinaryFileResponse
    {
        $this->authorizeTable('view');

        $columns = ['Code' => 'code', 'Name' => 'name', 'Description' => 'description'];
        $names = $this->referencedNames(null);

        foreach ($this->entry()['extra_fields'] as $field => $definition) {
            $columns[$definition['label']] = fn (Model $row): string => $this->displayValue($row, $field, $names);
        }

        $columns['Active'] = fn (Model $row): string => $row->getAttribute('is_active') ? 'Yes' : 'No';

        return ListingExport::download(str_replace('_', '-', (string) $this->table), $this->exportQuery(), $columns);
    }

    public function render(): View
    {
        $registry = $this->registry();
        $this->table === null || $this->authorizeTable('view');
        $visible = $registry->visibleTo($this->actor());

        $rows = $this->table !== null ? $this->paginatedRows() : collect();
        $mobileRows = $this->table !== null ? $this->mobileRows() : collect();
        $names = $this->table !== null ? $this->referencedNames(collect($rows->items())->concat($mobileRows)) : [];

        return view('livewire.admin.master-data', [
            'groups' => collect($visible)->groupBy('module', preserveKeys: true),
            'entry' => $this->table !== null ? $this->entry() : null,
            'rows' => $rows,
            'mobileRows' => $mobileRows,
            'colors' => SaveLookup::COLORS,
            'can' => fn (string $action): bool => $this->table !== null && $registry->allows($this->actor(), $this->table, $action),
            'canReorder' => $this->sort === '' && trim($this->search) === '' && array_filter($this->filters, 'filled') === [],
            'editingIsSystem' => $this->table !== null && $this->editingId !== null && (bool) $this->listingQuery()->whereKey($this->editingId)->value('is_system'),
            'display' => fn (Model $row, string $field): string => $this->displayValue($row, $field, $names),
        ])->title($this->table !== null ? __($registry->get($this->table)['label']) : __('Master data'));
    }

    protected function listingQuery(): Builder
    {
        return $this->registry()->modelFor((string) $this->table)->newQuery()->orderBy('sort_order')->orderBy('name');
    }

    protected function searchColumns(): array
    {
        return ['code', 'name', 'description'];
    }

    protected function sortColumns(): array
    {
        return ['code' => 'code', 'name' => 'name'];
    }

    protected function applyFilters(Builder $query): void
    {
        $active = $this->filterString('active');

        if ($active === '1' || $active === '0') {
            $query->where('is_active', $active === '1');
        }
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorizeTable('deactivate');
    }

    protected function deleteRow(Model $row): void
    {
        app(DeleteLookup::class)->handle((string) $this->table, $row);
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(): array
    {
        return $this->registry()->get((string) $this->table);
    }

    /**
     * Names behind the lookup and employee columns, keyed by field then id. Null rows loads every id (export).
     *
     * @param  Collection<int, Model>|null  $rows
     * @return array<string, array<int, string>>
     */
    private function referencedNames(?Collection $rows): array
    {
        $names = [];

        foreach ($this->entry()['extra_fields'] as $field => $definition) {
            if (! in_array($definition['type'], ['lookup', 'employee'], true)) {
                continue;
            }

            [$table, $column] = $definition['type'] === 'employee' ? ['employees', 'full_name'] : [$definition['table'], 'name'];
            $ids = $rows?->pluck($field)->filter()->unique()->values()->all();

            $names[$field] = $ids === [] ? [] : DB::table($table)
                ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
                ->pluck($column, 'id')
                ->all();
        }

        return $names;
    }

    /**
     * An extra field's value as text for a table cell or export.
     *
     * @param  array<string, array<int, string>>  $names
     */
    private function displayValue(Model $row, string $field, array $names): string
    {
        $value = $row->getAttribute($field);

        return match ($this->entry()['extra_fields'][$field]['type']) {
            'bool' => $value ? __('Yes') : __('No'),
            'list' => implode(', ', (array) $value),
            'lookup', 'employee' => $value === null ? '' : ($names[$field][$value] ?? ''),
            default => (string) $value,
        };
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
