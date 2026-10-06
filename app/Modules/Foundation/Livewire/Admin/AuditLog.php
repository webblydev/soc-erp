<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Models\AuditLog as AuditEntry;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Audit log')]
class AuditLog extends Component
{
    use WithListing;

    #[Locked]
    public ?int $selectedId = null;

    public function mount(): void
    {
        $this->authorize('admin.audit.view');
    }

    public function show(int $id): void
    {
        $this->authorize('admin.audit.view');

        $this->selectedId = AuditEntry::query()->findOrFail($id)->id;
        $this->dispatch('open-sheet-audit-entry');
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('admin.audit.export');

        return ListingExport::download('audit-log', $this->filteredQuery(), [
            'When' => fn (AuditEntry $entry): string => $entry->created_at->format('d-M-Y H:i:s'),
            'User' => 'user.username',
            'Event' => 'event',
            'Record type' => 'auditable_type',
            'Record id' => 'auditable_id',
            'Old values' => fn (AuditEntry $entry): string => (string) json_encode($entry->old_values, JSON_UNESCAPED_UNICODE),
            'New values' => fn (AuditEntry $entry): string => (string) json_encode($entry->new_values, JSON_UNESCAPED_UNICODE),
            'IP' => 'ip_address',
        ]);
    }

    /**
     * @return Builder<AuditEntry>
     */
    protected function listingQuery(): Builder
    {
        return AuditEntry::query()->with(['user:id,name,username', 'impersonator:id,username'])->latest('id');
    }

    protected function searchColumns(): array
    {
        return ['auditable_type', 'event'];
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
            $query->whereHas('user', fn (Builder $users) => $users->where('username', Str::lower(trim($this->filterString('user')))));
        }

        if ($this->filterString('type') !== '') {
            $query->where('auditable_type', $this->filterString('type'));
        }

        if (ctype_digit($this->filterString('record'))) {
            $query->where('auditable_id', (int) $this->filterString('record'));
        }

        if ($this->filterString('event') !== '') {
            $query->where('event', $this->filterString('event'));
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
        return view('livewire.admin.audit-log', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'types' => AuditEntry::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
            'events' => AuditEntry::query()->distinct()->orderBy('event')->pluck('event'),
            'selected' => $this->selectedId !== null ? AuditEntry::query()->with(['user', 'impersonator'])->find($this->selectedId) : null,
        ]);
    }
}
