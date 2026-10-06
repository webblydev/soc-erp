<?php

namespace App\Support\Listing;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Row selection with "Export selected" and "Delete selected" for desktop list tables.
 * Selected ids are always re-read through filteredQuery(), so an id outside the user's data
 * scope or the current filters is ignored. Components opt into delete by overriding
 * authorizeBulkDelete() and deleteRow(); a row whose delete throws a validation or
 * authorization error is skipped, not fatal.
 */
trait WithBulkActions
{
    /** @var list<string> */
    public array $selected = [];

    private bool $exportingSelection = false;

    /**
     * @return Builder<covariant Model>
     */
    abstract protected function filteredQuery(): Builder;

    /**
     * Download the filtered list; build it from exportQuery() so Export selected narrows it.
     */
    abstract public function export(): BinaryFileResponse;

    /**
     * A new search or filter changes the visible rows, so the selection starts over.
     */
    public function updatedWithBulkActions(string $property): void
    {
        if ($property === 'search' || $property === 'filters' || str_starts_with($property, 'filters.')) {
            $this->selected = [];
        }
    }

    public function exportSelected(): ?BinaryFileResponse
    {
        if ($this->selectedIds() === []) {
            return null;
        }

        $this->exportingSelection = true;

        try {
            return $this->export();
        } finally {
            $this->exportingSelection = false;
        }
    }

    public function deleteSelected(): void
    {
        $this->authorizeBulkDelete();

        $this->runDelete($this->selectedIds());
        $this->selected = [];
    }

    public function deleteRecord(int $id): void
    {
        $this->authorizeBulkDelete();

        $this->runDelete([$id]);
        $this->selected = array_values(array_diff($this->selected, [(string) $id]));
    }

    /**
     * The query an export() should download: the filtered list, or only the selected rows
     * while exportSelected() runs.
     *
     * @return Builder<covariant Model>
     */
    protected function exportQuery(): Builder
    {
        $query = $this->filteredQuery();

        return $this->exportingSelection ? $query->whereKey($this->selectedIds()) : $query;
    }

    /**
     * Throw when the user may not delete rows on this screen. Screens without delete keep the default.
     *
     * @throws AuthorizationException
     */
    protected function authorizeBulkDelete(): void
    {
        abort(403);
    }

    /**
     * Delete one row through the module's Action.
     *
     * @throws ValidationException|AuthorizationException
     */
    protected function deleteRow(Model $row): void
    {
        throw new LogicException(static::class.' does not support deleting rows.');
    }

    /**
     * @return list<int>
     */
    protected function selectedIds(): array
    {
        $ids = array_filter($this->selected, fn (string $id): bool => ctype_digit($id));

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @param  list<int>  $ids
     */
    private function runDelete(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $deleted = 0;
        $reason = null;

        foreach ($this->filteredQuery()->whereKey($ids)->get() as $row) {
            try {
                $this->deleteRow($row);
                $deleted++;
            } catch (ValidationException $exception) {
                $reason ??= (string) collect($exception->errors())->flatten()->first();
            } catch (AuthorizationException) {
                $reason ??= (string) __('You are not allowed to delete some of these records.');
            }
        }

        $skipped = count($ids) - $deleted;

        if ($skipped === 0) {
            $this->dispatch('toast', type: 'success', description: trans_choice(':count record deleted.|:count records deleted.', $deleted));

            return;
        }

        $this->dispatch('toast', type: $deleted > 0 ? 'warning' : 'error', description: trim((string) __(':deleted deleted, :skipped skipped.', ['deleted' => $deleted, 'skipped' => $skipped]).' '.($reason ?? '')));
    }
}
