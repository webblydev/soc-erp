<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Actions\IncreaseSequenceNumber;
use App\Modules\Foundation\Actions\UpdateSequenceFormat;
use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use App\Support\NumberSequenceService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Number sequences')]
class Sequences extends Component
{
    #[Locked]
    public ?int $formatId = null;

    public string $format = '';

    #[Locked]
    public ?int $counterId = null;

    public ?int $nextNumber = null;

    public function mount(): void
    {
        $this->authorize('admin.sequences.view');
    }

    public function editFormat(int $id): void
    {
        $this->authorize('admin.sequences.update');

        $definition = NumberSequenceFormat::query()->findOrFail($id);
        $this->formatId = $definition->id;
        $this->format = $definition->format;
        $this->resetErrorBag();
        $this->dispatch('open-sheet-sequence-format');
    }

    public function saveFormat(UpdateSequenceFormat $updateSequenceFormat): void
    {
        $this->authorize('admin.sequences.update');

        $updateSequenceFormat->handle(NumberSequenceFormat::query()->findOrFail($this->formatId), $this->format);

        $this->dispatch('close-sheet-sequence-format');
        $this->dispatch('toast', type: 'success', description: __('Format saved.'));
    }

    public function editCounter(int $id): void
    {
        $this->authorize('admin.sequences.update');

        $counter = NumberSequence::query()->findOrFail($id);
        $this->counterId = $counter->id;
        $this->nextNumber = $counter->next_number;
        $this->resetErrorBag();
        $this->dispatch('open-sheet-sequence-counter');
    }

    public function saveCounter(IncreaseSequenceNumber $increaseSequenceNumber): void
    {
        $this->authorize('admin.sequences.update');
        $this->validate(['nextNumber' => ['required', 'integer', 'min:1']]);

        try {
            $increaseSequenceNumber->handle(NumberSequence::query()->findOrFail($this->counterId), (int) $this->nextNumber);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['nextNumber' => $exception->errors()['next_number'] ?? []]);
        }

        $this->dispatch('close-sheet-sequence-counter');
        $this->dispatch('toast', type: 'success', description: __('Next number updated.'));
    }

    public function render(NumberSequenceService $numbers): View
    {
        return view('livewire.admin.sequences', [
            'definitions' => NumberSequenceFormat::query()->with('sequences')->orderBy('document_type')->get(),
            'sample' => fn (string $format, int $number): string => $numbers->preview($format, $number, ['bl_prefix' => 'BL', 'branch' => 'HO']),
            'counter' => $this->counterId !== null ? NumberSequence::query()->find($this->counterId) : null,
        ]);
    }
}
