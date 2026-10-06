<?php

namespace App\Modules\Hrm\Livewire\Documents;

use App\Models\User;
use App\Modules\Hrm\Actions\DeleteEmployeeDocument;
use App\Modules\Hrm\Models\EmployeeDocument;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Documents of current employees that have expired or expire within 30 / 60 / 90 days
 * (docs/09 §4.6, HR-AC-05).
 */
#[Title('Expiring documents')]
class Expiring extends Component
{
    use WithBulkActions;

    public const WINDOWS = ['expired', '30', '60', '90'];

    #[Url(except: '30')]
    public string $window = '30';

    public function mount(): void
    {
        $this->authorize('hrm.documents.manage');
    }

    public function updatedWindow(): void
    {
        $this->selected = [];
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('hrm.documents.manage');

        return ListingExport::download('expiring-documents', $this->exportQuery(), [
            'Employee code' => 'employee.employee_code',
            'Employee' => 'employee.full_name',
            'Document' => 'type.name',
            'Number' => 'document_no',
            'Expiry date' => fn (EmployeeDocument $document): string => $document->expiry_date->format('d-M-Y'),
            'Days left' => fn (EmployeeDocument $document): int => (int) today()->diffInDays($document->expiry_date, false),
        ]);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('hrm.documents.manage');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var EmployeeDocument $row */
        /** @var User $actor */
        $actor = auth()->user();

        app(DeleteEmployeeDocument::class)->handle($actor, $row);
    }

    /**
     * @return Builder<EmployeeDocument>
     */
    protected function filteredQuery(): Builder
    {
        if (! in_array($this->window, self::WINDOWS, true)) {
            $this->window = '30';
        }

        $query = EmployeeDocument::query()
            ->whereNotNull('expiry_date')
            ->whereHas('employee', fn (Builder $query) => $query->assignable())
            ->with(['employee:id,employee_code,full_name', 'type:id,name'])
            ->orderBy('expiry_date');

        $this->window === 'expired'
            ? $query->whereDate('expiry_date', '<', today())
            : $query->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', today()->addDays((int) $this->window));

        return $query;
    }

    public function render(): View
    {
        return view('livewire.hrm.documents.expiring', ['documents' => $this->filteredQuery()->limit(500)->get()]);
    }
}
