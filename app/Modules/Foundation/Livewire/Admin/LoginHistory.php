<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Models\LoginHistory as LoginEntry;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Login history')]
class LoginHistory extends Component
{
    use WithListing;

    public function mount(): void
    {
        $this->authorize('admin.login_history.view');
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('admin.login_history.view');

        return ListingExport::download('login-history', $this->filteredQuery(), [
            'When' => fn (LoginEntry $entry): string => $entry->created_at->format('d-M-Y H:i:s'),
            'Username attempted' => 'username_attempted',
            'User' => 'user.name',
            'Result' => fn (LoginEntry $entry): string => $entry->succeeded ? 'Success' : 'Failed',
            'IP' => 'ip_address',
            'User agent' => 'user_agent',
        ]);
    }

    /**
     * @return Builder<LoginEntry>
     */
    protected function listingQuery(): Builder
    {
        return LoginEntry::query()->with('user:id,name')->latest('id');
    }

    protected function searchColumns(): array
    {
        return ['username_attempted', 'ip_address'];
    }

    protected function sortColumns(): array
    {
        return ['created_at' => 'created_at'];
    }

    /**
     * @param  Builder<*>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        $filters = $this->filters;

        if ($this->filterString('user') !== '') {
            $query->where('username_attempted', Str::lower(trim($this->filterString('user'))));
        }

        if (in_array($this->filterString('result'), ['0', '1'], true)) {
            $query->where('succeeded', $this->filterString('result') === '1');
        }

        if (($from = $this->validDate($filters['from'] ?? null)) !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (($to = $this->validDate($filters['to'] ?? null)) !== null) {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    public function render(): View
    {
        return view('livewire.admin.login-history', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
        ]);
    }
}
