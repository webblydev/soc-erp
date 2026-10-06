<?php

namespace Tests\Fixtures;

use App\Models\User;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BulkListingFixture extends Component
{
    use WithBulkActions, WithListing;

    /**
     * @return Builder<User>
     */
    protected function listingQuery(): Builder
    {
        return User::query()->orderBy('id');
    }

    protected function searchColumns(): array
    {
        return ['username'];
    }

    protected function sortColumns(): array
    {
        return [];
    }

    protected function applyFilters(Builder $query): void
    {
        if (($this->filters['active'] ?? '') !== '') {
            $query->where('is_active', $this->filters['active'] === '1');
        }
    }

    public function export(): BinaryFileResponse
    {
        return ListingExport::download('fixture', $this->exportQuery(), ['Username' => 'username']);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('admin.users.delete');
    }

    protected function deleteRow(Model $row): void
    {
        if ($row->getAttribute('username') === 'locked') {
            throw ValidationException::withMessages(['row' => 'Locked rows stay.']);
        }

        $row->delete();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
