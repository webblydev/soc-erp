<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Models\User;
use App\Modules\Hrm\Actions\DeleteEmployeeDocument;
use App\Modules\Hrm\Actions\SaveEmployeeDocument;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Documents tab of the employee profile (docs/09 §3.3, spec H13): expiry badges, file download,
 * and add / edit / delete for document managers.
 */
class Documents extends Component
{
    use WithFileUploads;

    #[Locked]
    public Employee $employee;

    #[Locked]
    public ?int $editingId = null;

    /** @var array{employee_document_type_id: int|string|null, document_no: string, issue_date: string, expiry_date: string, notes: string} */
    public array $form = ['employee_document_type_id' => null, 'document_no' => '', 'issue_date' => '', 'expiry_date' => '', 'notes' => ''];

    /** @var UploadedFile|null */
    public $file = null;

    public function mount(Employee $employee): void
    {
        $this->authorize('viewDocuments', $employee);
        $this->employee = $employee;
    }

    public function create(): void
    {
        $this->authorize('manageDocuments', $this->employee);

        $this->reset('form', 'file', 'editingId');
        $this->resetErrorBag();
        $this->dispatch('open-sheet-employee-document');
    }

    public function edit(int $id): void
    {
        $this->authorize('manageDocuments', $this->employee);

        $document = $this->find($id);
        $this->editingId = $document->id;
        $this->form = [
            'employee_document_type_id' => $document->employee_document_type_id,
            'document_no' => (string) $document->document_no,
            'issue_date' => (string) $document->issue_date?->format('Y-m-d'),
            'expiry_date' => (string) $document->expiry_date?->format('Y-m-d'),
            'notes' => (string) $document->notes,
        ];
        $this->file = null;
        $this->resetErrorBag();
        $this->dispatch('open-sheet-employee-document');
    }

    public function save(SaveEmployeeDocument $saveEmployeeDocument): void
    {
        $this->authorize('manageDocuments', $this->employee);

        try {
            $saveEmployeeDocument->handle($this->actor(), $this->employee, $this->form, $this->file, $this->editingId !== null ? $this->find($this->editingId) : null);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn (array $messages, string $key): array => [$key === 'file' ? 'file' : 'form.'.$key => $messages],
            )->all());
        }

        $this->reset('form', 'file', 'editingId');
        $this->dispatch('close-sheet-employee-document');
        $this->dispatch('toast', type: 'success', description: __('Document saved.'));
    }

    public function delete(int $id, DeleteEmployeeDocument $deleteEmployeeDocument): void
    {
        $this->authorize('manageDocuments', $this->employee);

        $deleteEmployeeDocument->handle($this->actor(), $this->find($id));

        $this->dispatch('toast', type: 'success', description: __('Document deleted.'));
    }

    private function find(int $id): EmployeeDocument
    {
        return $this->employee->documents()->findOrFail($id);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.hrm.employees.documents', [
            'documents' => $this->employee->documents()->with(['type:id,name,has_expiry', 'attachment'])
                ->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('id')->get(),
            'canManage' => $this->actor()->can('manageDocuments', $this->employee),
        ]);
    }
}
