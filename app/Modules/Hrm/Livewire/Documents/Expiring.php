<?php

namespace App\Modules\Hrm\Livewire\Documents;

use App\Modules\Hrm\Models\EmployeeDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Documents of current employees that have expired or expire within 30 / 60 / 90 days
 * (docs/09 §4.6, HR-AC-05).
 */
#[Title('Expiring documents')]
class Expiring extends Component
{
    public const WINDOWS = ['expired', '30', '60', '90'];

    #[Url(except: '30')]
    public string $window = '30';

    public function mount(): void
    {
        $this->authorize('hrm.documents.manage');
    }

    public function render(): View
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

        return view('livewire.hrm.documents.expiring', ['documents' => $query->limit(500)->get()]);
    }
}
