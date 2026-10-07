<?php

namespace App\Modules\Estimation\Livewire\Mb;

use App\Models\User;
use App\Modules\Estimation\Actions\DeleteMeasurement;
use App\Modules\Estimation\Actions\RejectMeasurements;
use App\Modules\Estimation\Actions\UnverifyMeasurement;
use App\Modules\Estimation\Actions\VerifyMeasurements;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Services\MeasurementLimits;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * MB entry detail (spec §5.8): fields, BOQ progress, verification and photos.
 */
class Show extends Component
{
    public MeasurementEntry $entry;

    public string $rejectReason = '';

    public function mount(MeasurementEntry $entry): void
    {
        $this->authorize('view', $entry);
        $this->entry = $entry;
    }

    public function verify(VerifyMeasurements $verify): void
    {
        $this->run(fn () => $verify->handle($this->actor(), [$this->entry->id]), __('Entry verified.'));
    }

    public function reject(RejectMeasurements $reject): void
    {
        $this->run(fn () => $reject->handle($this->actor(), [$this->entry->id], $this->rejectReason), __('Entry rejected.'));
        $this->dispatch('close-sheet-mb-entry-reject');
    }

    public function unverify(UnverifyMeasurement $unverify): void
    {
        $this->run(fn () => $unverify->handle($this->actor(), $this->entry), __('Entry moved back to recorded.'));
    }

    public function delete(DeleteMeasurement $delete): void
    {
        try {
            $delete->handle($this->actor(), $this->entry);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        session()->flash('success', __('Entry deleted.'));
        $this->redirectRoute('site.mb.index', navigate: true);
    }

    private function run(\Closure $action, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: collect($exception->errors())->flatten()->implode(' '));

            return;
        }

        $this->entry->refresh();
        $this->reset('rejectReason');
        $this->dispatch('toast', type: 'success', description: $message);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(MeasurementLimits $limits): View
    {
        $entry = $this->entry->load(['project:id,project_number,name,project_manager_id,customer_id,project_status_id', 'status', 'unit:id,symbol', 'measurer:id,full_name', 'verifier:id,name', 'workItem:id,code,name', 'estimateLine.estimate:id,estimate_number', 'estimateLine.unit:id,symbol']);

        return view('livewire.estimation.mb.show', [
            'progress' => $entry->estimateLine !== null ? $limits->check($entry->estimateLine, '0', null) : null,
        ])
            ->title($entry->mb_number)
            ->layoutData(['back' => route('site.mb.index')]);
    }
}
